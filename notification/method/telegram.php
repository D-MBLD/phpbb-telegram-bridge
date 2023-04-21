<?php
/**
 *
 * Telegram Bridge. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Edgar Bolender
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

 namespace eb\telegram\notification\method;

/**
* Send telegram notifications.
*
*/
class telegram extends \phpbb\notification\method\messenger_base
{

	/** @var \phpbb\user */
	protected $user;
	/** @var \phpbb\config\config */
	protected $config;
	/** @var \phpbb\language\language */
	protected $language;
	/** @var \eb\telegram\core\telegram_api */
	protected $telegram_api;
	/** @var \eb\telegram\core\forum_api */
	protected $forum_api;

	public function __construct(
		\phpbb\user_loader $user_loader,
		\phpbb\user $user,
		\phpbb\language\language $language,
		\phpbb\config\config $config,
		$phpbb_root_path,
		$php_ext,
		\eb\telegram\core\telegram_api $telegram_api,
		\eb\telegram\core\forum_api $forum_api
		)
	{
		parent::__construct($user_loader, $phpbb_root_path, $php_ext);
		$this->user 	= $user;
		$this->config 	= $config;
		$this->language = $language;
		$this->telegram_api = $telegram_api;
		$this->forum_api = $forum_api;
		$this->language->add_lang('common', 'eb/telegram');
	}

	/**
	* Get notification method name
	*
	* @return string
	*/
	public function get_type()
	{
		return 'eb.telegram.notification.method.telegram';
	}

	/**
	* Is this method available for the user?
	* This is checked on the notifications options.
	* The telegram column is only shown, if this returns true.
	*/
	public function is_available(\phpbb\notification\type\type_interface $notification_type = null)
	{
		return ($this->bot_is_configured() && (strlen($this->user->data['user_telegram_id']) > 2));
	}

	/* Overwritten:
	 * Return the users, which where already notified.
	 * As we want to send a notification for every post, even if the user was
	 * already notified about the previous one, we return an empty array here.
	 */
	public function get_notified_users($notification_type_id, $options)
	{
		return array();
	}

	/**
	* Is this method available at all?
	* This is checked before notifications are sent
	*/
	private function bot_is_configured()
	{
		return !(empty($this->config['eb_telegram_bot_token']));
	}

	/** Copied mainly from messenger_base, but changed in the send-method and
	 *  when fetching the templates.
	 */
	public function notify()
	{
		$template_dir_prefix = '';

		if (!$this->bot_is_configured())
		{
			return;
		}

		if (empty($this->queue))
		{
			 return;
		}

		// Load all users we want to notify (we need their telegram IDs)
		$user_ids = $users = array();
		foreach ($this->queue as $notification)
		{
			$user_ids[] = $notification->user_id;
		}

		// We do not send telegram to banned users
		if (!function_exists('phpbb_get_banned_user_ids'))
		{
			include($this->phpbb_root_path . 'includes/functions_user.' . $this->php_ext);
		}
		$banned_users = phpbb_get_banned_user_ids($user_ids);

		// Load all the users we need
		$this->user_loader->load_users($user_ids);

		//Load all telegram chat states. Only users with verified telegram ids
		//are receiving a notification
		$verified_telegram_users = $this->forum_api->select_telegram_chat_state();
		$verified_telegram_users = array_filter($verified_telegram_users,
												function($val)
												{
													return $val['state'] != 'V';
												});
		//Make chat_id the index of the array
		$verified_chat_ids = array_column($verified_telegram_users, null, 'chat_id');

		if (!class_exists('messenger'))
		{
			include($this->phpbb_root_path . 'includes/functions_messenger.' . $this->php_ext);
		}
		$messenger = new \messenger();

		// Go through the queue and send messages
		foreach ($this->queue as $notification)
		{

			if ($notification->get_email_template() === false)
			{
				continue;
			}

			$user = $this->user_loader->get_user($notification->user_id);
			$telegram_id = $user['user_telegram_id'];
			if (!$telegram_id || !isset($verified_chat_ids[$telegram_id]))
			{
				continue;
			}

			if ($user['user_type'] == USER_IGNORE || in_array($notification->user_id, $banned_users))
			{
				continue;
			}

			$permissions = $this->forum_api->read_telegram_permissions($notification->user_id);
			if (!$permissions['u_ebt_notify'])
			{
				continue;
			}

			if (method_exists($notification, 'get_telegram_template'))
			{
				$email_template = $notification->get_telegram_template();
			} else
			{
				$email_template = $notification->get_email_template();
			}

			$template_variables = $notification->get_email_template_variables();

			$forum_id = $template_variables['FORUM_ID'] ?? false;
			if ($forum_id)
			{
				// Overwrite the post permissions, if the user has no reply permission.
				$permissions['u_ebt_post'] = $permissions['u_ebt_post'] && $this->forum_api->has_reply_permission($notification->user_id, $forum_id);
				// Store the corresponding forum as currently selected forum for the users telegram communication
				$this->forum_api->store_forum_id($telegram_id, $forum_id);
			}
			//Also store the topic display as chat-state, such that the back buttons of the further
			//communication works correctly.
			$topic_id = $template_variables['TOPIC_ID'] ?? false;
			if ($topic_id)
			{
				$this->forum_api->store_telegram_chat_state($telegram_id, $topic_id, 'P');
			}

			if (isset($template_variables['POSTER_ID']))
			{
				if ($template_variables['POSTER_ID'] == $notification->user_id)
				{
					//If another extension (like eb/postbymail) included the author herself into the notification
					//recipients, we exclude her again here.
					continue;
				}
			}

			$messenger->template($email_template, $user['user_lang'], '', $template_dir_prefix);

			$messenger->set_addresses($user);

			$messenger->assign_vars(array_merge(array(
				'NOTIFICATION_TYPE'       => $notification->get_type(),
				'USERNAME'                => $user['username'],
				'U_NOTIFICATION_SETTINGS' => generate_board_url() . '/ucp.' . $this->php_ext . '?i=ucp_notifications&mode=notification_options',
			), $template_variables));

			/* Send with break=true only prepares the text, but does not send the message */
			$messenger->send(NOTIFY_EMAIL, true);

			$splitmarker = "\u{200B}\u{200B}";
			//The splitmarker makes sure, that the subject is shown, even if the message
			//is shortended due to the length limit of telegram.
			//(See telegram_api->prepareText)
			$this->msg = $messenger->subject . PHP_EOL . $splitmarker . PHP_EOL . $messenger->msg;

			$this->language->set_user_language($user['user_lang'], true);
			// Lets send to Telegram
			$this->send($telegram_id, $this->msg, $topic_id, $permissions);

		}
		$this->empty_queue();
	}

	/*
	 * Send a message to a telegram user
	 *
	 * @param	string	$telegram_id
	 * @param	string	$msg
	 * @param	string|false	$topic_id
	 * @param	array	$permissions
	 */
	public function send($telegram_id, $msg, $topic_id, $permissions)
	{
		if (!$telegram_id)
		{
		   error_log('Error, Telegram ID is needed',0);
		   return;
		}

		if (empty($msg))
		{
		   error_log('Error, No message to send',0);
		   return;
		}
		if ($topic_id)
		{
			//The full topic itself can always be viewed.
			$buttons[$this->language->lang('EBT_FULL_TOPIC')] = "showTopic~t$topic_id";
			if ($permissions['u_ebt_post'])
			{
				$buttons[$this->language->lang('EBT_NEW_REPLY')] = "newPost~t$topic_id";
			}
			if ($permissions['u_ebt_browse'])
			{
				$buttons[$this->language->lang('EBT_SHOW_FORUM')] = "allForumTopics";
			};
		} else
		{
			//This was another notification event, no new post or new topic
			$buttons = array(
				$this->language->lang('EBT_BACK') => 'back',
			);
		}

		$messageObject = $this->telegram_api->prepareMessage($msg, $buttons);
		$messageObject['chat_id'] = $telegram_id;
		$this->telegram_api->sendOrEditMessage($messageObject);
		//Clear the message-id, such that using a button, results in
		//a new answer rather than overwriting this notification.
		$this->forum_api->update_message_id($telegram_id);
	}

}
