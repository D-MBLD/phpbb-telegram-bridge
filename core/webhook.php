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

	/* @var \phpbb\config\config */
	protected $config;
	/* @var \phpbb\user */
	protected $user;
	/* @var \phpbb\controller\helper $helper */
	protected $helper;
	/* @var \phpbb\language\language  */
	protected $language;
	/* @var \phpbb\request\request   */
	protected $request;
	/* @var \eb\telegram\core\telegram_api  */
	protected $telegram_api;
	/* @var \eb\telegram\core\forum_api  */
	protected $forum_api;

	/** Telegram-ID of the administrator, where all unidentfied calls are returned to. */
	private $admin_telegram_id;

	public $debug_output = array();


	/**
	* Constructor
	*
	* @param \phpbb\config\config $config
	* @param \phpbb\user $user
	* @param \language\language $language
	* @param \phpbb\controller\helper $helper
	* @param \phpbb\request\request $request,
	* @param \eb\telegram\core\telegram_api $telegram_api,
	* @param \eb\telegram\core\forum_api $forum_api,
	*/
	public function __construct(\phpbb\config\config $config,
								\phpbb\user $user,
								\phpbb\language\language $language,
								\phpbb\controller\helper $helper,
								\phpbb\request\request $request,
								\eb\telegram\core\telegram_api $telegram_api,
								\eb\telegram\core\forum_api $forum_api
								)
	{
		$this->config = $config;
		$this->user = $user;
		$this->language = $language;
		$this->helper = $helper;
		$this->request = $request;
		$this->telegram_api = $telegram_api;
		$this->forum_api = $forum_api;
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
		$this->language->set_user_language($users[0]['user_lang'], true);
		$telegram_data = $this->forum_api->select_telegram_chat_state($caller);
		if ($telegram_data)
		{
			//Always clear the previous chat state. If a new state is needed,
			//it will be set below.
			$this->forum_api->store_telegram_chat_state($caller);

			$message_id = $telegram_data['message_id']; //Message to be updated
			if (isset($buttonData) && $message_id && $message_id != $reply_to_msgid)
			{
				if (!$this->test_call)
				{
					$command['action'] = 'buttonOutdated';
					return $command;
				}
			}
			$command['message_id'] = $message_id;
			if (isset($telegram_data['forum_id']) && $telegram_data['forum_id'] != 0)
			{
				$command['forum_id'] = $telegram_data['forum_id'];
			}
			if (isset($telegram_data['topic_id']))
			{
				$command['topic_id'] = $telegram_data['topic_id'];
			}
			$command['chatState'] = $telegram_data['state'];
			$command['title'] = $telegram_data['title'];
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
		if (isset($command['forum_id']))
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
				$command['action'] = 'registrationFailed';
			}
		} else if (stripos($buttonCallback, 'allForums') === 0 )
		{
			$command['action'] = 'allForums';
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
		} else if ($buttonCallback == 'initial')
		{
			$command['action'] = 'initial';
		} else
		{
			//The user herself should just receive the initial screen again.
			$command['action'] = 'initial';
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
		/* When a text was sent by the user, the answer should always appear below
		the text, that means no message_id (from previous message) must be provided,
		such that sendMessage instead of editMessage will be called. */
		unset($command['message_id']);
		switch ($chat_state)
		{
			case 'V':
				//Chat-ID not yet verified
				if (isset($command['title']) && $text == $command['title'])
				{
					$command['action'] = 'registrationOk';
				} else
				{
					$command['action'] = 'registrationFailed';
				}
				break;
			case '0':
				$command['action'] = 'initial';
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
				//Last page number is stored in the title-field.
				$command['action'] = $chat_state == 'T' ? 'allForumTopics' : 'allForums';
				if (strlen(trim(str_replace('+', '', $text, $count))) == 0)
				{
					//String contains only plus-signs and blanks
					$command['page'] = $command['title'] + $count;
				} else if (strlen(trim(str_replace('-', '', $text, $count))) == 0)
				{
					$command['page'] = $command['title'] - $count;
				} else
				{
					//Stay at the same page
					$command['page'] = $command['title'];
					$command['warning'] = $this->language->lang('EBT_ILLEGAL_INPUT', $text);
				}
				break;
			default:
				$command['action'] = 'initial';
				$command['admin_info'] = "Unexpected chat state on text-input: $chat_state\n/";
		}
		return $command;
	}

	/** Note: Command is passed by reference because admin_info may be added. */
	private function execute_command(&$command)
	{
		$action = $command['action'];
		switch ($action)
		{
			case 'adminInfo':
				//Response will be send to admin only
				return false;
			case 'howToRegister':
				$postdata = $this->onHowToRegister($command['chat_id']);
				unset($command['message_id']);
				break;
			case 'registrationEmailed':
				$postdata = $this->onRegistration($command['user'], $command['chat_id'], true, true);
				break;
			case 'registrationOk':
				$postdata = $this->onRegistration($command['user'], $command['chat_id'], true, false);
				break;
			case 'registrationFailed':
				//If an email was sent before, the title contains a code.
				$postdata = $this->onRegistration($command['user'], $command['chat_id'], false, $command['title']);
				break;
			case 'buttonOutdated':
				$postdata = $this->onButtonOutdated();
				break;
			case 'invalidForum':
				$postdata = $this->onInvalidForum();
				break;
			case 'initial':
				if (($command['forum_id'] ?? 0) != 0)
				{
					/* The initial screen depends on whether a forum was
					* already selected or not.
					*/
					$postdata = $this->onAllForumTopics($command['user']['user_id'], $command['chat_id'], $command['forum_id']);
					break; //break only here, else continue with same as 'allForums'
				}
			case 'allForums':
				$postdata = $this->onAllForums($command['user']['user_id'], $command['chat_id'], $command['forum_id'] ?? 0, $command['page'] ?? 0, $command['warning'] ?? false);
				break;
			case 'allForumTopics':
				$postdata = $this->onAllForumTopics($command['user']['user_id'], $command['chat_id'], $command['forum_id'], $command['page'] ?? 0, $command['warning'] ?? false);
				break;
			case 'showTopic':
				//If we are called from a paged topic display, we add the page into the
				//back button.
				$page = 0;
				if ($command['chatState'] == 'T')
				{
					$page = $command['title'] ?? 0;
				}
				$postdata = $this->onShowTopic($command['user']['user_id'], $command['topic_id'], $page);
				break;
			case 'newPost':
				$postdata = $this->onNewPost($command['topic_id']);
				//Save topic id and status "Waiting for Post-Text" in chat table
				$this->forum_api->store_telegram_chat_state($command['chat_id'], $command['topic_id'], 1);
				break;
			case 'saveNewPost':
				$saved = $this->forum_api->insertNewPost(false, $command['forum_id'], $command['topic_id'], $command['text'], $command['user']);
				if ($saved === true)
				{
					$postdata = $this->onShowTopic($command['user']['user_id'], $command['topic_id']);
				} else
				{
					$postdata = $this->onSaveFailed();
					$command['admin_info'] = $saved;
				}
				break;
			case 'newTopicTitle':
				$postdata = $this->onNewTopicTitle();
				//Save topic id and status "Waiting for Titel" in chat table
				$this->forum_api->store_telegram_chat_state($command['chat_id'], 0, 2);
				break;
			case 'newTopicText':
				$postdata = $this->onNewTopicText($command['text']);
				//Save topic id and status "Waiting for new Text" in chat table
				$this->forum_api->store_telegram_chat_state($command['chat_id'], 0, 3, $command['text']);
				break;
			case 'saveNewTopic':
				$postdata = $this->onNewTopicSaved($command['title'],$command['text'],$command['user'], $command['forum_id']);
				break;
			case 'callFromGroupOrChannel':
				$postdata = $this->onCallFromGroupOrChannel();
				break;
			default:
				$command_info = print_r($command, true);
				$command['admin_info'] = "Internal error, no action set. Command: \n$command_info";
				return false;
		}
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
			$prop_text = &$this->find_obj_with_prop($input, 'text');
			if ($prop_text && strlen($prop_text) > 50)
			{
				$prop_text = \substr($prop_text, 0, 50) . ' ...(shortened)...';
			}
			//Warning: Don't assign prop_text again. It's a reference and would overwrite
			//the content of the text-property.
			$prop_entities = &$this->find_obj_with_prop($input, 'entities');
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
	private function &find_obj_with_prop(&$obj, $property)
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
					$result = &$this->find_obj_with_prop($obj->{$name},$property);
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

	private function onInvalidForum()
	{
		// "You don\'t have access to the selected forum."
		$text = $this->language->lang('EBT_ILLEGAL_FORUM');
		return $this->telegram_api->prepareMessage($text, [$this->language->lang('EBT_OK') => 'allForums']);
	}

	private function onButtonOutdated()
	{
		// "Please use only buttons of the last message"
		$text = $this->language->lang('EBT_BUTTON_OUTDATED');
		return $this->telegram_api->prepareMessage($text, [$this->language->lang('EBT_OK') => 'initial']);
	}

	private function onHowToRegister($chat_id)
	{
		// "This service can be used only by members of (%s for link and %s for sitename)
		// Register your telegram id %s "
		$home_url = $this->config['site_home_url'];
		if (!$home_url)
		{
			$home_url = generate_board_url();
		}
		$text = $this->language->lang('EBT_HELP_SCREEN_NON_MEMBER', $this->config['sitename'], $home_url, $chat_id);
		return $this->telegram_api->prepareMessage($text);
	}

	/** Various registration situations.
	 * $ok = true and $email = true: Email was sent
	 * $ok = true and $email = false: Registration ok
	 * $ok = false and $email = true: Registration failed after email was sent
	 * $ok = false and $email = false: Registration failed, no email was sent immediately before
	 */
	private function onRegistration($user, $chat_id, $ok, $email)
	{
		$buttons = array();
		if ($ok && $email)
		{
			$code = $this->forum_api->send_email($user);
			$this->forum_api->store_telegram_chat_state($chat_id, 0, 'V', $code);
			$text = $this->language->lang('EBT_HELP_SCREEN_EMAILED');
		} else if ($ok)
		{
			$this->forum_api->store_telegram_chat_state($chat_id);
			$text = $this->language->lang('EBT_HELP_SCREEN_REGISTERED');
			$buttons[$this->language->lang('EBT_OK')] = 'initial';
		} else
		{
			$this->forum_api->store_telegram_chat_state($chat_id, 0, 'V');
			$text = $this->language->lang('EBT_HELP_SCREEN_REGISTRATION_FAILED');
			if ($email)
			{
				$text = $this->language->lang('EBT_ILLEGAL_CODE') . '<br>' . $text;
			}
			$buttons[$this->language->lang('EBT_REQUEST_EMAIL')] = 'requestEmail';
		}
		return $this->telegram_api->prepareMessage($text, $buttons);
	}

	/** Show all topics in the forum given by its ID.
	 */
	private function onAllForumTopics($user_id, $chat_id, $forum_id, $page = 0, $warning = false)
	{
		$forums = $this->forum_api->selectAllForums($user_id, $forum_id);
		$forum = reset($forums);
		$forumName = $forum['title'];

		if ($forum)
		{
			//Store the selected forum.
			$this->forum_api->store_forum_id($chat_id, $forum_id);

			$topics = $this->forum_api->selectForumTopics($user_id, $forum_id );
			$total_count = count($topics);
			while ($page * 6 >= $total_count)
			{
				$page--;
			}
			if ($page < 0)
			{
				$page = 0;
			}
			$topics = array_slice($topics, $page * 6, null, true);

			$buttons = array();
			$count = count($topics);
			$viewforum_url = generate_board_url() . '/viewforum.php?f=' . $forum_id;
			if ($count > 0)
			{
				// There are %2$s topics in the forum %1$s.
				$text = $this->language->lang('EBT_TOPIC_LIST_TITLE', $forumName, $total_count, $viewforum_url) . PHP_EOL . PHP_EOL;
			} else
			{
				// 'Currently there are no topics in the forum <b>%s</b>'
				$text = $this->language->lang('EBT_TOPIC_LIST_TITLE_EMPTY', $forumName, $viewforum_url) . PHP_EOL;
			}
			$i = 1;
			foreach ($topics as $id => $topic)
			{
				$not_approved = '';
				if (!$topic['approved'])
				{
					$not_approved = $this->language->lang('TOPIC_UNAPPROVED');
					$not_approved = "(<i><b>$not_approved</b></i>)" . PHP_EOL;
				}
				$title = $topic['title'];
				$date = date('d.m.y', $topic['date']);
				$num = $i + $page * 6;
				if ($topic['type'] == 2)
				{
					//announcement
					$text .= " $num: <i><u>$title ($date)</u></i>\n";
				} else if ($topic['type'] == 1)
				{
					//important topic
					$text .= " $num: <i>$title ($date)</i>\n";
				} else
				{
					$text .= " $num: $title ($date)\n";
				}
				$text .= $not_approved;
				$buttonText = "$num: $title";
				$buttons[$buttonText] = "showTopic~t$id";
				$i++;
				if ($i > 6)
				{
					break; //Not more than 8 buttons allowed.
				}
			}
			if ($warning)
			{
				$text .= PHP_EOL . $warning;
			}
			if ($count > 0)
			{
				// Use one of the buttons to select a topic;
				$text .= PHP_EOL . $this->language->lang('EBT_SELECT_TOPIC') . PHP_EOL;
			}
			if ($total_count > 6)
			{
				// Send '+' or '-' to show next or previous page of topics;
				$text .= $this->language->lang('EBT_SELECT_NEXT_PAGE') . PHP_EOL;
			}
			if ($forum['post']) //User has post permission
			{
				$buttons['NEW_LINE1'] = 'NEXT_LINE';
				$buttons[$this->language->lang('EBT_ADD_TOPIC')] = 'newTopicTitle';
			}
			$buttons['NEW_LINE2'] = 'NEXT_LINE';
			$buttons[$this->language->lang('EBT_SHOW_FORUMS')] = 'allForums';
		} else
		{
			// Could not read the forum. Please try again;
			$text .= $this->language->lang('EBT_FORUM_NOT_FOUND') . PHP_EOL;
			$buttons[$this->language->lang('EBT_BACK')] = 'initial';
		}
		//Save a chat state, that allows to page through the entries
		$this->forum_api->store_telegram_chat_state($chat_id, 0, 'T', $page);
		return $this->telegram_api->prepareMessage($text, $buttons);
	}

	/** Show the given page of all forums.  */
	private function onAllForums($user_id, $chat_id, $back_to_forum_id, $page = 0, $warning = false)
	{
		$forums = $this->forum_api->selectAllForums( $user_id );
		$total_count = count($forums);
		while ($page * 6 >= $total_count)
		{
			$page--;
		}
		if ($page < 0)
		{
			$page = 0;
		}
		$forums = array_slice($forums, $page * 6, null, true);

		$buttons = array();
		//List of forums:\n\n
		$text = $this->language->lang('EBT_FORUM_LIST_TITLE', $this->config['sitename'], $total_count) . PHP_EOL . PHP_EOL;
		$i = 1;
		foreach ($forums as $forum)
		{
			$id = $forum['id'];
			$title = $forum['title'];
			$lastTopicDate = date('d.m.y', $forum['lastTopicDate']);
			$lastTopicTitle = $forum['lastTopicTitle'];
			$lastTopicAuthor = $forum['lastTopicAuthor'];
			$num = $i + $page * 6;
			$readonly = ($forum['post'] || $forum['reply']) ? '' : $this->language->lang('EBT_READ_ONLY');
			$text .= " $num: <b>$title</b>$readonly" . PHP_EOL;
			//Last post at %s by <b>%s</b>
			$text .= $this->language->lang('EBT_LAST_POST', $lastTopicDate, $lastTopicAuthor) . PHP_EOL;
			$text .= $lastTopicTitle . PHP_EOL;
			$text .= '<u>___________________________________</u>' . PHP_EOL;

			$buttonText = "$num: $title";
			$buttons[$buttonText] = "allForumTopics~f$id";
			$i++;
			if ($i > 6)
			{
				break; //6 per page, such that the button list stays short.
			}
		}
		if ($warning)
		{
			$text .=  $warning . PHP_EOL;
		}
		// "Use one of the buttons to select a forum";
		$text .= $this->language->lang('EBT_SELECT_A_FORUM');
		if ($total_count > 6)
		{
			// Send '+' or '-' to show next or previous page;
			$text .= PHP_EOL . $this->language->lang('EBT_SELECT_NEXT_PAGE') . PHP_EOL;
		}
		if ($back_to_forum_id)
		{
			$buttons[$this->language->lang('EBT_BACK')] = "allForumTopics~f$back_to_forum_id";
		}
		//Save a chat state, that allows to page through the entries
		$this->forum_api->store_telegram_chat_state($chat_id, 0, 'F', $page);

		return $this->telegram_api->prepareMessage($text, $buttons);
	}

	/** Display the topic with the given topic_id. Page refers to the page, of
	 * the topics-list, such that the return-button can go back to the same page.
	 */
	private function onShowTopic($user_id, $topic, $page = 0)
	{
		$text = '';
		$posts = $this->forum_api->selectTopicPosts($user_id, $topic);
		$first = true;
		$readonly = true;
		$viewtopic_url = generate_board_url() . '/viewtopic.php?t=';
		foreach ($posts as $post)
		{
			$time = date('d.m.y H:i', $post['time']);
			$user = $post['username'];
			$not_approved = '';
			if (!$post['approved'])
			{
				$not_approved = $this->language->lang('POST_UNAPPROVED_EXPLAIN');
				$not_approved = "<i><b>$not_approved</b></i>" . PHP_EOL;
			}
			if ($first)
			{
				$title = $post['title'];
				// "<b>$time:</b> Topic created by <b>$user</b>\n";
				$text .= $this->language->lang('EBT_TOPIC_AT_BY', $time, $user) . PHP_EOL;
				// "Titel: <b>$title</b>\n";
				$text .= $this->language->lang('EBT_TOPIC_TITLE', $title, $viewtopic_url . $topic) . PHP_EOL;
				$text .= $not_approved;
				$text .= $post['text'];
				$readonly = !$post['reply'];
				$first = false;
			} else
			{
				// "<b>$time:</b> Reply from <b>$user</b>\n";
				$text .= $this->language->lang('EBT_REPLY_AT_BY', $time, $user) . PHP_EOL;
				$text .= $not_approved;
				$text .= $post['text'];
			}
			$text .= PHP_EOL . '<u>___________________________________</u>' . PHP_EOL;
		}
		if (count($posts) == 0)
		{
			// "Illegal attempt to read topic with ID $topic";
			$text = $this->language->lang('EBT_ILLEGAL_TOPIC_ID', $topic);
		}

		$buttons = array();
		if (!$readonly)
		{
			$buttons[$this->language->lang('EBT_NEW_REPLY')] = "newPost~t$topic";
		}
		$buttons[$this->language->lang('EBT_BACK')] = "allForumTopics~p$page";
		return $this->telegram_api->prepareMessage($text, $buttons);
	}

	private function onNewPost($topic)
	{
		// Send your reply or use the cancel button
		$text = $this->language->lang('EBT_REQUEST_POST');
		$buttons = array($this->language->lang('EBT_CANCEL') => 'initial');
		return $this->telegram_api->prepareMessage($text, $buttons);
	}

	private function onNewTopicTitle()
	{
		// Send the title for your new post or use the cancel button
		$text = $this->language->lang('EBT_REQUEST_TITLE');
		$buttons = array($this->language->lang('EBT_CANCEL') => 'initial');
		return $this->telegram_api->prepareMessage($text, $buttons);
	}

	private function onNewTopicText($title)
	{
		// Send the text for your new post with title <b>$title</b> or use the cancel button.
		$text = $this->language->lang('EBT_REQUEST_TEXT_FOR_TITLE', $title);
		$buttons = array($this->language->lang('EBT_CANCEL') => 'initial');
		return $this->telegram_api->prepareMessage($text, $buttons);
	}

	private function onNewTopicSaved($title, $content, $user, $forum_id)
	{
		$saved = $this->forum_api->insertNewPost(true, $forum_id, $title, $content, $user);
		if ($saved)
		{
			// The following post was saved.
			$text = $this->language->lang('EBT_TOPIC_SAVED') . PHP_EOL;
			// Title: <a href="%2$s"><b>%1$s</b></a>
			// Empty link here (2nd Parameter).
			$text .= $this->language->lang('EBT_TOPIC_TITLE', $title, '') . PHP_EOL;
			$text .= $content;
			$buttons = array($this->language->lang('EBT_BACK') => 'initial');
			return $this->telegram_api->prepareMessage($text, $buttons);
		} else
		{
			return $this->onSaveFailed();
		}
	}

	private function onSaveFailed()
	{
		// For some unknown reason, saving your new entry failed.
		$text = $this->language->lang('EBT_TOPIC_SAVE_FAILED');
		$buttons = array($this->language->lang('EBT_BACK') => 'initial');
		return $this->telegram_api->prepareMessage($text, $buttons);
	}

	private function onCallFromGroupOrChannel()
	{
		// The forum cannot be called via groups or channels.
		$text = $this->language->lang('EBT_GROUP_OR_CHANNEL_CALL') . PHP_EOL;
		return $this->telegram_api->prepareMessage($text);
	}

	private function log_obj($obj)
	{
		$lines = explode(PHP_EOL, print_r($obj, true));
		$indented_lines = str_replace(' ', '&nbsp;', $lines);
		$this->debug_output = array_merge($this->debug_output, $indented_lines);
	}

}
