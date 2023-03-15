<?php
/**
 *
 * Telegram Bridge. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Edgar Bolender
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

 namespace eb\telegram\core;

/** Webhook where the telgram bot sends its message updates to.
 * The admin page provides the URL for registering the webhook.
 */
class webhook {

	/** @var \phpbb\config\config */
	protected $config;
	/** @var \phpbb\controller\helper $helper */
	protected $helper;
	/** @var \phpbb\language\language  */
	protected $language;
	/** @var \phpbb\request\request   */
	protected $request;
	/** @var \eb\telegram\core\telegram_api  */
	protected $telegram_api;
	/** @var \eb\telegram\core\forum_api  */
	protected $forum_api;

	/** Telegram-ID of the administrator, where all unidentfied calls are returned to. */
	private $admin_telegram_id;

	public $debug_output = array();


	/**
	* Constructor
	*
	* @param \phpbb\config\config $config
	* @param \language\language $language
	* @param \phpbb\controller\helper $helper
	* @param \phpbb\request\request $request,
	* @param \eb\telegram\core\telegram_api $telegram_api,
	* @param \eb\telegram\core\forum_api $forum_api,
	* @param \eb\telegram\core\commands $commands,
	*/
	public function __construct(\phpbb\config\config $config,
								\phpbb\language\language $language,
								\phpbb\controller\helper $helper,
								\phpbb\request\request $request,
								\eb\telegram\core\telegram_api $telegram_api,
								\eb\telegram\core\forum_api $forum_api,
								\eb\telegram\core\commands $commands
								)
	{
		$this->config = $config;
		$this->language = $language;
		$this->helper = $helper;
		$this->request = $request;
		$this->telegram_api = $telegram_api;
		$this->forum_api = $forum_api;
		$this->commands = $commands;
		$this->admin_telegram_id = $this->config['eb_telegram_admin_telegram_id'];
		$this->echo_to_admin =  $this->config['eb_telegram_admin_echo'];
	}

	private $test_call; //Called via URL rather than via webhook.
	//Following calls are supported:
	//https://<base-URL>/forum/telegram/webhook?text=some_text  for simulating Text-Input
	//https://<base-URL>/forum/telegram/webhook?command=showAllTopics for simulating button press

	public function handle()
	{
		//header('HTTP/1.1 200 EBT_OK');
		//header('Content-Type: text/plain; charset=utf-8');
		try {
			$payload = $this->parse_request();
			$this->debug_output[] = 'Request:';
			$this->log_obj($payload);
			$this->process_input($payload);
		} catch (Throwable $e)
		{
			$this->debug_output[] = 'Something went wrong';
			$this->log_obj($e);
		}
		$this->debug_output[] = 'Done';
		return $this->helper->render('blank.html', 'Telegram Webhook');
	}

	private function parse_request()
	{
		//Simulate text-input or the data of an inline button with the url-parameter 'text' or 'command'
		$this->secret_token = $this->request->header('X-Telegram-Bot-Api-Secret-Token', $default = '');
		//From https://stackoverflow.com/questions/2445276/how-to-post-data-in-php-using-file-get-contents
		$obj =  json_decode(file_get_contents('php://input'));
		return $obj;
	}

	/** public, because also used from test-page. */
	public function process_input($payload, $test_call = false)
	{
		$this->test_call = $test_call;
		$this->debug_output[] = 'Payload:';
		$this->log_obj($payload);
		$command = $this->validate_input($payload);
		$this->debug_output[] = 'Command after validation:';
		$this->log_obj($command);
		$command = $this->create_command($command);
		$this->debug_output[] = 'Command after process:';
		$this->log_obj($command);
		$postdata = $this->execute_command($command);
		$this->debug_output[] = 'Sending:';
		$this->log_obj($postdata);
		if ($postdata)
		{
			$result = $this->telegram_api->sendOrEditMessage($postdata);
			$this->debug_output = array_merge($this->debug_output, $this->telegram_api->debug_output);
			//Save the message id, such that it can be used for an update.
			if ($result)
			{
				$result_obj = json_decode($result);
				if ($result_obj->ok)
				{
					$message_id = $result_obj->result->message_id;
					$this->forum_api->update_message_id($postdata['chat_id'], $message_id);
				}
			}
		}
		//There may be also an admin-info contained, which is send to the admin
		$this->send_admin_info($payload, $command);
	}


	/** Analyze the request and react with returning the requested new message.
	 * The message is only prepared here, but not sent back to telegram.
	 * Returns a command array with fields 'action'.
	 * Validate the input for known sender and known format.
	 * The returned array has the following fields set:
	 * - chat_id (sender and chat-id, both must be the same)
	 * - text, if a text input was sent
	 * - buttonCallback, if a button was pressed
	 * - forum_id, If a forum was already selected and stored in the DB
	 * - user, if the chat_id belongs to a registered user
	 * - message_id, id of the previous message, if one was stored.
	 *   This allowes to update the previous message rather than to send a new one with every button press.
	 * - chatstate, stored in DB and holds e.g. the info, which text-input is expected.
	 * - title, topic title from previous input when expecting next the text of the topic.
	 * - action, if the validation failed, and the response is already determined here.
	 * - admin_info, a text which should be send to the admin, in case the validation failed.
	*/
	private function validate_input($input)
	{
		$command = array();
		// Note: A new text message, has a from-id and a chat-id.
		// If both are the same, the message is sent from the user to the bot.
		// Otherwise it was sent to a group or to a channel, where the bot is member of.
		// In case of requests from inline-buttons, the original message is returned, where
		// the from-id (of the original message) is the id of the bot, but the chat_id is
		// still the id of the user.
		if (isset($input->message))
		{
			$caller = $input->message->from->id;
			$chat_id = $input->message->chat->id;
			$text = $input->message->text;
			$command['text'] = $text;
		} else if (isset($input->callback_query))
		{
			//This is the message format, sent from an inline button
			$caller = $input->callback_query->from->id;
			$chat_id = $input->callback_query->message->chat->id;
			$buttonData = $input->callback_query->data;
			$command['buttonCallback'] = $buttonData;
			$reply_to_msgid = $input->callback_query->message->message_id;
		} else if (isset($input->channel_post))
		{
			//Text send from a channel
			$chat_id = $input->channel_post->chat->id;
			$caller = 'not_set_in_channel';
		}
		if (!$this->test_call && $this->secret_token != $this->config['eb_telegram_secret'] )
		{
			$token = $this->secret_token;
			$admin_info = "<b>Unexpected secret token. Token = $token</b><br>".json_encode($input, JSON_PRETTY_PRINT);
			$command['action'] = 'adminInfo';
		} else if (!$caller)
		{
			$admin_info = '<b>Chat_id could not be identified in following request:</b><br>'.json_encode($input, JSON_PRETTY_PRINT);
			$command['action'] = 'adminInfo';
		} else if ($chat_id == $this->admin_telegram_id && $text == 'echo on')
		{
			$this->config->set('eb_telegram_admin_echo', 1);
			$admin_info = 'Echo to admin switched on';
			$command['action'] = 'adminInfo';
		} else if ($chat_id == $this->admin_telegram_id && $text == 'echo off')
		{
			$this->config->set('eb_telegram_admin_echo', 0);
			$admin_info = 'Echo to admin switched off';
			$command['action'] = 'adminInfo';
		} else if ($caller != $chat_id)
		{
			$admin_info = '<b>Request (most probably) from group or channel:</b><br>'.json_encode($input, JSON_PRETTY_PRINT);
			$command['action'] = 'callFromGroupOrChannel';
			$command['chat_id'] = $chat_id;
		}
		if (isset($admin_info))
		{
			$command['admin_info'] = $admin_info;
			//Admin-Info will be send to the admin. No response to caller, if chat_id not yet set.
			return $command;
		}
		$command['chat_id'] = $caller;
		$users = $this->forum_api->find_telegram_user($caller);
		if (count($users) == 0)
		{
			$command['action'] = 'howToRegister';
			$command['admin_info'] = '<b>Request from unregistered user:</b><br>'.json_encode($input, JSON_PRETTY_PRINT);
			return $command;
		}
		$command['user'] = $users[0];
		$command['permissions'] = $this->forum_api->read_telegram_permissions($users[0]['user_id']);
		$this->language->set_user_language($users[0]['user_lang'], true);
		$telegram_data = $this->forum_api->select_telegram_chat_state($caller);
		if ($telegram_data)
		{
			$command['message_id'] = $telegram_data['message_id'];
			$command['forum_id'] = $telegram_data['forum_id'];
			$command['topic_id'] = $telegram_data['topic_id'];
			$command['chatState'] = $telegram_data['state'];
			$command['title'] = $telegram_data['title'];
			$command['page'] = $telegram_data['page'];

			if (isset($buttonData) && $command['message_id'] && $command['message_id'] != $reply_to_msgid)
			{
				if (!$this->test_call)
				{
					$command['action'] = 'buttonOutdated';
					return $command;
				}
			}
		} else
		{
			$command['chatState'] = 'V'; //Verification pending
		}
		return $command;
	}

	/** Determine based on the information from input validation, how to
	 * react on the input. The result is the input array, with at least the
	 * additional field "action", which determines the action to be executed.
	 */
	private function create_command($command)
	{
		if (isset($command['action']))
		{
			//action was already set during (failed) input validation.
			return $command;
		}
		if (isset($command['buttonCallback']))
		{
			$command = $this->create_command_for_button_callback($command);
		} else
		{
			$command = $this->create_command_for_text_input($command);
		}
		//In all cases where a forum is preselected, check that the user
		//has access to that forum.
		if (isset($command['forum_id']) && $command['permissions']['u_ebt_browse'])
		{
			if (!$this->has_permission($command['user']['user_id'], $command['forum_id']))
			{
				$command['action'] = 'invalidForum';
				$command['admin_info'] = "Illegal attempt to read forum {$command['forum_id']} by {$command['chat_id']}";
			}
		}
		return $command;
	}

	private function create_command_for_button_callback($command)
	{
		$buttonCallback = $command['buttonCallback'];
		if (($command['chatState'] ?? 'V') == 'V') //Chat-ID not yet verified
		{
			if ($buttonCallback == 'requestEmail')
			{
				$command['action'] = 'registrationEmailed';
			} else
			{
				$command['action'] = 'registrationRequired';
			}
		} else if (stripos($buttonCallback, 'allForums') === 0 )
		{
			$command['action'] = 'allForums';
			$page = $this->parse_id_from_button($buttonCallback, null, true);
			$command['page'] = $page !== false ? $page : $command['page'];
		} else if (stripos($buttonCallback, 'allForumTopics') === 0 )
		{
			$command['action'] = 'allForumTopics';
			$forum_id = $this->parse_id_from_button($buttonCallback, false);
			//Back from a topic, the page can be included in the back-command
			$page = $this->parse_id_from_button($buttonCallback, null, true);
			if ($forum_id)
			{
				//Change forum only if coded in the button
				$command['forum_id'] = $forum_id;
				//In that case, start with page 0
				$command['page'] = 0;
			} else if ($page)
			{
				$command['page'] = $page;
			}
		} else if (stripos($buttonCallback, 'showTopic~t') === 0 )
		{
			$command['topic_id'] = $this->parse_id_from_button($buttonCallback);
			$command['action'] = 'showTopic';
		} else if (stripos($buttonCallback, 'newPost~t') === 0 )
		{
			$command['topic_id'] = $this->parse_id_from_button($buttonCallback);
			$command['action'] = 'newPost';
			//No update of telegram message, but request answer below of previous post
			unset($command['message_id']);
		} else if (stripos($buttonCallback, 'newTopicTitle') === 0 )
		{
			$command['action'] = 'newTopicTitle';
			//No update of telegram message, but request title below of display with forum content
			unset($command['message_id']);
		} else if ($buttonCallback == 'back')
		{
			switch ($command['chatState'])
			{
				case 'F': $command['action'] = 'allForums';
					break;
				case 'T': $command['action'] = 'allForumTopics';
					break;
				case '1': 
					//New reply for a topic was cancelled
				case 'P':
					$command['action'] = 'showTopic';
					break;
				default:
					$command['action'] = 'allForums';
			}
		} else
		{
			//Unexpected callback. Show the permissions screen and inform the admin.
			$command['action'] = 'showPermissions';
			$command['admin_info'] = 'Unexpected input via button callback: '. $command['buttonCallback'];
		}
		return $command;
	}

	private function create_command_for_text_input($command)
	{
		/* If a text message was send, the action depends on the
		current chat state.
		*/
		$chat_state = $command['chatState'] ?? false;
		$text = $command['text'];
		/* When a text was sent by the user, the answer in telegram should always appear below
		the text, that means no message_id (from previous message) must be provided,
		such that sendMessage instead of editMessage of the telegram API will be called. */
		unset($command['message_id']);
		switch ($chat_state)
		{
			case 'V':
				//Chat-ID not yet verified
				if (isset($command['title']) && $text == $command['title'])
				{
					$command['action'] = 'registrationOk';
				} else if (isset($command['title']) && $command['title'])
				{
					$command['action'] = 'registrationFailed';
				} else
				{
					$command['action'] = 'registrationRequired';
				}
				break;
			case '1':
				//Entered text is a new post for an existing topic
				$command['action'] = 'saveNewPost';
				break;
			case '2':
				//Entered text is the titel for a new topic
				$command['action'] = 'newTopicText';
				break;
			case '3':
				//Entered text is the text for a new topic
				$command['action'] = 'saveNewTopic';
				break;
			case 'T':
			case 'F':
				//Entered text should be a paging command for a list of topics or forums
				$command['action'] = $chat_state == 'T' ? 'allForumTopics' : 'allForums';
				if (strlen(trim(str_replace('+', '', $text, $count))) == 0)
				{
					//String contains only plus-signs and blanks
					$command['page'] += $count;
					break;
				} else if (strlen(trim(str_replace('-', '', $text, $count))) == 0)
				{
					$command['page'] -= $count;
					break;
				} 
				//No break in case of other text input
			default:
				$command['action'] = 'showPermissions';
				if (strstr('0TFP', $chat_state) == false) 
				{
					$command['admin_info'] = "Unexpected chat state on text-input: $chat_state\n";
				}
		}
		return $command;
	}

	/** Note: Command is passed by reference because some methods may add something, e.g. an admin_info. */
	private function execute_command(&$command)
	{
		$action = $command['action'];

		//Execute the function, which is named like the action, starting uppercase and prefixed with on.
		$function = 'on' . ucfirst($action);
		if (method_exists($this->commands, $function))
		{
			$text_and_buttons = $this->commands->{$function}($command);
		} else
		{
			$command_info = print_r($command, true);
			$command['admin_info'] = "Internal error, illegal action set. Command: \n$command_info";
		}
		if (!$text_and_buttons)
		{
			return false; //Nothing to send to the user, but maybe to the admin
		}
		$postdata = $this->telegram_api->prepareMessage($text_and_buttons[0], $text_and_buttons[1] ?? array());
		$postdata['chat_id'] = $command['chat_id'];
		if (isset($command['message_id']))
		{
			$postdata['message_id'] = $command['message_id'];
		}
		return $postdata;
	}

	private function send_admin_info($input, $command)
	{
		if ($this->echo_to_admin && !isset($command['admin_info']))
		{
			//Modify the input by shortening the text-property and the
			//entities-property if necessary.
			$prop_text = &$this->get_ref_to_prop($input, 'text');
			if ($prop_text && strlen($prop_text) > 50)
			{
				$prop_text = \substr($prop_text, 0, 50) . ' ...(shortened)...';
			}
			//Warning: Don't assign prop_text again. It's a reference and would overwrite
			//the content of the text-property.
			$prop_entities = &$this->get_ref_to_prop($input, 'entities');
			if ($prop_entities && count($prop_entities) > 2)
			{
				$prop_entities = 'array too long (' . count($prop_entities) . ') for display';
			}
			$originalCommand = $command;
			$command['admin_info'] = '<b><u>Telegram request echo</u></b><br>';
			$command['admin_info'] .= '<i>(Can be switched off in the admin page of the forum)</i><br>';
			$command['admin_info'] .= '<b>Input:</b><br>';
			$command['admin_info'] .= json_encode($input, JSON_PRETTY_PRINT);
			$command['admin_info'] .= '<br><b>Command:</b><br>';
			$command['admin_info'] .= print_r($originalCommand, true);
		}
		if (!isset($command['admin_info']))
		{
			return;
		}
		$postdata = $this->telegram_api->prepareMessage($command['admin_info']);
		$postdata['chat_id'] = $this->admin_telegram_id;
		$this->telegram_api->sendOrEditMessage($postdata);
	}

	/** Return a reference to the given property (even if deep nested) */
	private function &get_ref_to_prop(&$obj, $property)
	{
		if (is_object($obj))
		{
			$obvar = get_object_vars($obj);
			if (array_key_exists($property,$obvar))
			{
				return $obj->{$property};
			} else
			{
				foreach ($obvar as $name => $var)
				{
					$result = &$this->get_ref_to_prop($obj->{$name},$property);
					if ($result)
					{
						return $result;
					}
				}
			}
		}
		return $null; //Need to return a variable reference to avoid warnings
	}

	private function parse_id_from_button($buttonData, $topic = true, $page = false)
	{
		if ($page)
		{
			$regex = '/.*~p([0-9]*)[^0-9]*/sm';
		} else if ($topic)
		{
			$regex = '/.*~t([0-9]*)[^0-9]*/sm';
		} else 	//Forum-ID
		{
			$regex = '/.*~f([0-9]*)[^0-9]*/sm';
		}
		preg_match($regex, $buttonData, $matches);
		if ($matches)
		{
			return $matches[1];
		}
		return false;
	}

	/** Check for permission.
	 * Returns true, if user has at least permission to read the forum.
	 */
	private function has_permission($user_id, $forum_id)
	{
		$forums = $this->forum_api->selectAllForums($user_id, $forum_id);
		$first = reset($forums);
		return $first ? true : false;
	}

	private function log_obj($obj)
	{
		$lines = explode(PHP_EOL, print_r($obj, true));
		$indented_lines = str_replace(' ', '&nbsp;', $lines);
		$this->debug_output = array_merge($this->debug_output, $indented_lines);
	}

}
