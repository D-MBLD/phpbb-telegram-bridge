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

/** Create the text and buttons response depending on the state and the users
 * input.
 * If an action like saving a post or saving the state is involved, this is
 * also done here.*/
class commands
{

	/** Defining the variables her, helps in VSC navigation! */
	/** @var \eb\telegram\core\forum_api */
	private $forum_api;

	/** @var \eb\telegram\core\formatters */
	private $formatters;

	/**
	* Constructor
	*
	* @param \phpbb\config\config $config
	* @param \language\language $language
	* @param \eb\telegram\core\forum_api $forum_api,
	*/
	public function __construct(\phpbb\config\config $config,
								\phpbb\language\language $language,
								\eb\telegram\core\forum_api $forum_api
								)
	{
		$this->config = $config;
		$this->language = $language;
		$this->forum_api = $forum_api;
		$this->formatters = new \eb\telegram\core\formatters(/*$config, $language*/);
	}

	public function onButtonOutdated($command)
	{
		// "Please use only buttons of the last message"
		$text = $this->language->lang('EBT_BUTTON_OUTDATED');
		return [$text, [$this->language->lang('EBT_OK') => 'back']];
	}

	public function onHowToRegister(&$command)
	{
		$chat_id = $command['chat_id'];
		// "This service can be used only by members of (%s for link and %s for sitename)
		// Register your telegram id %s "
		$home_url = $this->config['site_home_url'];
		if (!$home_url)
		{
			$home_url = generate_board_url();
		}
		$text = $this->language->lang('EBT_HELP_SCREEN_NON_MEMBER', $this->config['sitename'], $home_url, $chat_id);
		unset($command['message_id']);
		return [$text];
	}

	public function onRegistrationEmailed($command)
	{
		$user = $command['user'];
		$chat_id = $command['chat_id'];
		$code = $this->forum_api->send_email($user);
		$text = $this->language->lang('EBT_HELP_SCREEN_EMAILED');
		$this->forum_api->store_telegram_chat_state($chat_id, 0, 'V', $code);
		return [$text];
	}

	public function onRegistrationRequired($command)
	{
		$chat_id = $command['chat_id'];
		$this->forum_api->store_telegram_chat_state($chat_id, 0, 'V');
		$text = $this->language->lang('EBT_HELP_SCREEN_REGISTRATION_FAILED');
		$buttons[$this->language->lang('EBT_REQUEST_EMAIL')] = 'requestEmail';
		return [$text, $buttons];
	}

	public function onRegistrationOk($command)
	{
		$chat_id = $command['chat_id'];
		$this->forum_api->store_telegram_chat_state($chat_id);
		$text = $this->language->lang('EBT_HELP_SCREEN_REGISTERED');
		//Add the permissions information, using the output of the onShowPermission command.
		unset($command['text']);
		[$permissions_text, $ignored_buttons] = $this->onShowPermissions($command);
		$text .= PHP_EOL . $permissions_text;
		$buttons[$this->language->lang('EBT_OK')] = 'allForums';
		return [$text, $buttons];
	}

	public function onRegistrationFailed($command)
	{
		$chat_id = $command['chat_id'];
		$this->forum_api->store_telegram_chat_state($chat_id, 0, 'V');
		$text = $this->language->lang('EBT_ILLEGAL_CODE') . PHP_EOL;
		$text .= $this->language->lang('EBT_HELP_SCREEN_REGISTRATION_FAILED');
		$buttons[$this->language->lang('EBT_REQUEST_EMAIL')] = 'requestEmail';
		return [$text, $buttons];
	}

	/** Show all topics in the forum given by its ID.
	 */
	public function onAllForumTopics($command)
	{
		$user_id = $command['user']['user_id'];
		$chat_id =$command['chat_id'];
		$forum_id = $command['forum_id'] ?? 0;
		$forum_index = $command['index'] ?? -1;
		$page = $command['page'];
		$warning = $command['warning'] ?? false;

		if (!$command['permissions']['u_ebt_browse'])
		{
			return $this->onShowPermissions($command);
		}
		//Permission check inside API
		if ($forum_index >= 0)
		{
			$forums = $this->forum_api->selectAllForums($user_id);
			$forum = $forums[$forum_index - 1] ?? false;
		} else
		{
			$forums = $this->forum_api->selectAllForums($user_id, $forum_id);
			$forum = reset($forums);
		}

		if ($forum)
		{
			$forumName = $forum['title'];
			//Store the selected forum.
			$this->forum_api->store_forum_id($chat_id, $forum_id);
			//Permission check needed
			$topics = $this->forum_api->selectForumTopics($user_id, $forum_id );
			$total_count = count($topics);
			$topics = $this->select_page($topics, $page, $chat_id, 'T');

			$buttons = array();
			$viewforum_url = generate_board_url() . '/viewforum.php?f=' . $forum_id;
			if ($total_count > 0)
			{
				// There are %2$s topics in the forum %1$s.
				$text = $this->language->lang('EBT_TOPIC_LIST_TITLE', $forumName, $total_count, $viewforum_url) . PHP_EOL . PHP_EOL;
			} else
			{
				// 'Currently there are no topics in the forum <b>%s</b>'
				$text = $this->language->lang('EBT_TOPIC_LIST_TITLE_EMPTY', $forumName, $viewforum_url) . PHP_EOL;
			}
			$i = 1;
			foreach ($topics as $topic)
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
				$id = $topic['topic_id'];
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
			if ($total_count > 0)
			{
				// Use one of the buttons to select a topic;
				$text .= PHP_EOL . $this->language->lang('EBT_SELECT_TOPIC') . PHP_EOL;
			}
			if ($total_count > 6)
			{
				// Send '+' or '-' to show next or previous page of topics;
				$text .= $this->language->lang('EBT_SELECT_NEXT_PAGE') . PHP_EOL;
			}
			if ($command['permissions']['u_ebt_post'] && $forum['post']) //User has general and forum specific post permission
			{
				$buttons['NEW_LINE1'] = 'NEXT_LINE';
				$buttons[$this->language->lang('EBT_ADD_TOPIC')] = 'newTopicTitle';
			}
			$buttons['NEW_LINE2'] = 'NEXT_LINE';
			$buttons[$this->language->lang('EBT_SHOW_FORUMS')] = 'allForums~p0';
		} else
		{
			return $this->errorInvalidForum();
		}
		return [$text, $buttons];
	}

	/** Show the selected page (6 entries) of all forums.  */
	public function onAllForums($command)
	{
		$user_id = $command['user']['user_id'];
		$chat_id = $command['chat_id'];
		$page = $command['page'] ?? 0;
		$warning = $command['warning'] ?? false;

		if (!$command['permissions']['u_ebt_browse'])
		{
			return $this->onShowPermissions($command);
		}
		//Permission check needed
		$forums = $this->forum_api->selectAllForums( $user_id );
		$total_count = count($forums);
		$forums = $this->select_page($forums, $page, $chat_id, 'F');

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
		return [$text, $buttons];
	}

	/** Display the topic with the given topic_id.
	 */
	public function onShowTopic($command)
	{
		$chat_id = $command['chat_id'];
		$user_id = $command['user']['user_id'];
		$topic_id = $command['topic_id'];
		$topic_index = $command['index'] ?? 0;
		$topic_index--;
		$text = '';
		if ($topic_index >= 0)
		{
			$topics = $this->forum_api->selectForumTopics($user_id, $command['forum_id']);
			$topic_id = $topics[$topic_index]['topic_id'] ?? 0;
		}
		//Permission check needed
		$posts = $this->forum_api->selectTopicPosts($user_id, $topic_id);
		// Page refers to the page, of the topics-list.
		$page = $command['page'];
		if (count($posts) > 0 && $topic_index >= 0)
		{
			$page = floor($topic_index / 6); //Adapt the page to the selection
		}
		$this->forum_api->store_telegram_chat_state($chat_id, $topic_id, 'P', '', $page);
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
				// "Title: <b>$title</b>\n";
				$text .= $this->language->lang('EBT_TOPIC_TITLE', $title, $viewtopic_url . $topic_id) . PHP_EOL;
				$text .= $not_approved;
				//Add an invisible marker, where the text could be split, if the
				//full post is too long, to be displayed.
				$text .= "\u{200B}\u{200B}";
				$text .= $this->formatters->format_post_for_telegram($post['text']);
				$readonly = !$post['reply'];
				$first = false;
			} else
			{
				// "<b>$time:</b> Reply from <b>$user</b>\n";
				$text .= $this->language->lang('EBT_REPLY_AT_BY', $time, $user) . PHP_EOL;
				$text .= $not_approved;
				$text .= $this->formatters->format_post_for_telegram($post['text']);
			}
			$text .= PHP_EOL . '<u>___________________________________</u>' . PHP_EOL;
		}
		if (count($posts) == 0)
		{
			// "Illegal attempt to read topic with ID $topic_id";
			$text = $this->language->lang('EBT_ILLEGAL_TOPIC_ID', $topic_id);
		}

		$buttons = array();
		if (!$readonly && $command['permissions']['u_ebt_post'])
		{
			$buttons[$this->language->lang('EBT_NEW_REPLY')] = "newPost~t$topic_id";
		}
		$buttons[$this->language->lang('EBT_BACK')] = "allForumTopics";
		return [$text, $buttons];
	}

	public function onNewPost($command)
	{
		if (!$command['permissions']['u_ebt_post'])
		{
			return $this->onShowPermissions($command);
		}
		$topic_id = $command['topic_id'];
		$chat_id = $command['chat_id'];
		//set state "Waiting for reply-text"
		$this->forum_api->store_telegram_chat_state($chat_id, $topic_id, 1);
		// Send your reply or use the cancel button
		$text = $this->language->lang('EBT_REQUEST_POST');
		$buttons = array($this->language->lang('EBT_CANCEL') => 'back');

		return [$text, $buttons];
	}

	public function onNewTopicTitle($command)
	{
		if (!$command['permissions']['u_ebt_post'])
		{
			return $this->onShowPermissions($command);
		}
		//Set state "Waiting for Title" (without changing forum_id)
		$this->forum_api->store_telegram_chat_state($command['chat_id'], 0, 2);
		// Send the title for your new post or use the cancel button
		$text = $this->language->lang('EBT_REQUEST_TITLE');
		$buttons = array($this->language->lang('EBT_CANCEL') => 'allForumTopics');
		return [$text, $buttons];
	}

	public function onNewTopicText($command)
	{
		if (!$command['permissions']['u_ebt_post'])
		{
			return $this->onShowPermissions($command);
		}
		//Note: title does not support BBCode.
		//$title = $this->format_text($command['text'], $command['entities'] ?? array());
		$title = $command['text'];
		//Save the title and set state to "Waiting for new Text"
		$this->forum_api->store_telegram_chat_state($command['chat_id'], 0, 3, $title);

		// Send the text for your new post with title <b>$title</b> or use the cancel button.
		$text = $this->language->lang('EBT_REQUEST_TEXT_FOR_TITLE', $title);
		$buttons = array($this->language->lang('EBT_CANCEL') => 'allForumTopics');
		return [$text, $buttons];
	}

	public function onSaveNewTopic($command)
	{
		if (!$command['permissions']['u_ebt_post'])
		{
			return $this->onShowPermissions($command);
		}
		$title = $command['title'];
		$content = $this->formatters->format_input($command['text'], $command['entities'] ?? array());
		$user = $command['user'];
		$forum_id = $command['forum_id'];
		$topic_id = $this->forum_api->insertNewPost(true, $forum_id, $title, $content, $user);
		//Reset chat_state to topic-display (for back commands)
		$this->forum_api->store_telegram_chat_state($command['chat_id'], 0, 'T');

		if ($topic_id)
		{
			// The following post was saved.
			$text = $this->language->lang('EBT_TOPIC_SAVED') . PHP_EOL;
			// Title: <a href="%2$s"><b>%1$s</b></a>
			// Empty link here (2nd Parameter).
			$text .= $this->language->lang('EBT_TOPIC_TITLE', $title, '') . PHP_EOL;
			$text .= $content;
			$buttons = array($this->language->lang('EBT_BACK') => 'allForumTopics');

			$command['topic_id'] = $topic_id;
			return $this->onShowTopic($command);
		} else
		{
			return $this->errorOnSave();
		}
	}

	public function onSaveNewPost(&$command)
	{
		//Save button should not be available, if user has no permission,
		//but just to be sure, we check again.
		if (!$command['permissions']['u_ebt_post'])
		{
			return $this->onShowPermissions($command);
		}
		$command['text'] = $this->formatters->format_input($command['text'], $command['entities'] ?? array());
		$saved = $this->forum_api->insertNewPost(false, $command['forum_id'], $command['topic_id'], $command['text'], $command['user']);
		//Reset chat_state to topic-display (for back commands)
		$this->forum_api->store_telegram_chat_state($command['chat_id'], 0, 'T');
		if ($saved)
		{
			return $this->onShowTopic($command);
		} else
		{
			$topic_id = $command['topic_id'];
			$username = $command['user']['username'];
			$command['admin_info'] = "New post for topic $topic_id could not be saved, by user $username";
			return $this->errorOnSave();
		}
	}

	public function onShowPermissions($command)
	{
		$text = '';
		if ($command['text'] ?? false)
		{
			$text = $this->language->lang('EBT_ILLEGAL_INPUT', $command['text']) . PHP_EOL;
		}
		$text .= $this->language->lang('EBT_PERMISSION_TITLE');
		$text .= PHP_EOL;
		$text .= $command['permissions']['u_ebt_browse'] ?
					$this->language->lang('EBT_PERMISSION_BROWSE_YES') :
					$this->language->lang('EBT_PERMISSION_BROWSE_NO');
		$text .= PHP_EOL;
		$text .= $command['permissions']['u_ebt_post'] ?
					$this->language->lang('EBT_PERMISSION_POST_YES') :
					$this->language->lang('EBT_PERMISSION_POST_NO');
		$text .= PHP_EOL;
		$text .= $command['permissions']['u_ebt_notify'] ?
					$this->language->lang('EBT_PERMISSION_NOTIFY_YES') :
					$this->language->lang('EBT_PERMISSION_NOTIFY_NO');
		if ($command['permissions']['u_ebt_notify'])
		{
			$text .= PHP_EOL;
			$text .= $this->language->lang('EBT_SELECT_NOTIFICATIONS');
		}
		$buttons = array($this->language->lang('EBT_BACK') => 'back');
		return [$text, $buttons];
	}

	public function onCallFromGroupOrChannel($command)
	{
		// The forum cannot be called via groups or channels.
		$text = $this->language->lang('EBT_GROUP_OR_CHANNEL_CALL') . PHP_EOL;
		return [$text];
	}

	public function onAdminInfo($command)
	{
		return false; //Send info only to admin
	}

	/** Handle the paging of a list of forums (state = F)
	 *  or topics (state = T)
	 */
	private function select_page($list, &$page, $chat_id, $state)
	{
		$total_count = count($list);
		while ($page * 6 >= $total_count)
		{
			$page--;
		}
		if ($page < 0)
		{
			$page = 0;
		}
		$this->forum_api->store_telegram_chat_state($chat_id, 0, $state, '', $page);
		return array_slice($list, $page * 6, null, true);
	}

	private function errorOnSave()
	{
		// For some unknown reason, saving your new entry failed.
		$text = $this->language->lang('EBT_TOPIC_SAVE_FAILED');
		$buttons = array($this->language->lang('EBT_BACK') => 'back');
		return [$text, $buttons];
	}

	private function errorInvalidForum()
	{
		// "You don\'t have access to the selected forum."
		$text = $this->language->lang('EBT_ILLEGAL_FORUM');
		return [$text, [$this->language->lang('EBT_OK') => 'allForums']];
	}

	/** Format the text by adding bbCodes according to the formatting information,
	 * which telegram sends as so called entities.
	 */
	public function format_text($text, $entities)
	{
		/* Split the text, at every point where a formatting starts or ends into an array.
		 * Therefore we collect at first all splitpoints, and remove duplicates.
		 */
		$split_points[] = 0;
		foreach ($entities as $entity)
		{
			$split_points[] = $entity->offset;
			$split_points[] = $entity->offset + $entity->length;
		}
		$split_points = array_unique($split_points);
		rsort($split_points);
		$chunks = array();
		foreach ($split_points as $point)
		{
			$chunks[$point] = mb_substr($text, $point);
			$text = mb_substr($text, 0, $point);
		}
		ksort($chunks);
		//Sort by end of formatting, such that in case of overlapping formats, the opening tag
		//for the format, that gets closed last is placed at first.
		usort($entities, function($a, $b)
						{
							return (($a->offset + $a->length) < ($b->offset + $b->length)) ? -1 : 1;
						});
		foreach ($entities as $entity)
		{
			$bbcode = $this->get_bbcode($entity->type);
			if (!$bbcode)
			{
				continue;
			}
			$chunks[$entity->offset] = $bbcode . $chunks[$entity->offset];
		}
		for ($i = count($entities) - 1; $i >= 0; $i--)
		{
			$entity = $entities[$i];
			$bbcode = $this->get_bbcode($entity->type, false);
			if (!$bbcode)
			{
				continue;
			}
			$bbcode_start = $this->get_bbcode($entity->type);
			$end = $entity->offset + $entity->length;
			if (strpos($chunks[$end], $bbcode_start) === 0)
			{
				//Remove ending tag immediatly followed by starting tag
				$chunks[$end] = substr($chunks[$end], strlen($bbcode_start));
			} else
			{
				$chunks[$end] = $bbcode . $chunks[$end];
			}
		}
		//Remove non printable whitespace, which may have been included, when user copies
		//a part of a post, where the whitespace was added. (See telegrami_api->htmlentitiesForTelegram)
		$text = implode('', $chunks);
		$text = str_replace("/\u{200B}", "/", $text);
		return $text;
	}

	private function get_bbcode($format_type, $start = true)
	{
		switch ($format_type)
		{
			case 'bold': return $start ? '[b]' : '[/b]';
			case 'italic': return $start ? '[i]' : '[/i]';
			case 'underline': return $start ? '[u]' : '[/u]';
			default: return false;
		}
	}

}
