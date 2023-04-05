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

/** Access to the forum tables, and forum APIs */
class forum_api {
	/* @var \phpbb\config\config $config */
	protected $config;
	/* @var \phpbb\db\driver\driver_interface */
	protected $db;
	/* @var \phpbb\user */
	protected $user;
	/* @var \phpbb\auth\auth  */
	protected $auth;
	protected $phpbb_root_path;
	protected $php_ext;

	/**
	* Constructor
	*
	* @param \phpbb\config\config	$config
	*/
	public function __construct(\phpbb\config\config $config,
								\phpbb\db\driver\driver_interface $db,
								\phpbb\user $user,
								\phpbb\auth\auth $auth,
								$phpbb_root_path,
								$php_ext
								)
	{
		$this->config = $config;
		$this->db = $db;
		$this->user = $user;
		$this->auth = $auth;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	/** Simplified permission reader, used to check if notification should show reply button. */
	public function has_reply_permission($user_id, $forum_id)
	{
		$acls = $this->auth->acl_get_list($user_id, 'f_reply', $forum_id);
		return isset($acls[$forum_id]['f_reply']);
	}

	/** Returns an array of forum_ids (key) and assigned arrays with values
	 * for read, post, reply and moderated.
	 * Only forums with at least read-permission are returned.
	 * If a parent-forum has no read or list permission, the children forums are
	 * not listed (even if they would have more permissions).
	 *
	 * Example:
	 * Array
	 * (
	 *	[43] => ['post' = 'false', 'reply' = true, 'moderated' = false],
	 *	[11] => ['post' => false], 'reply' = false ] //moderated does not matter for this forum
	 *	... all forums with at least read permission
	 * )
	 */
	private function getForumsPermissions($user_id)
	{
		//Note: There are 2 permissions: f_post (for new topics)
		//and f_reply for posts to existing topics !!!
		$acls = $this->auth->acl_get_list($user_id, ['f_read', 'f_post', 'f_reply', 'f_noapprove'], false);
		$acls_read_or_list = $this->auth->acl_get_list($user_id, ['f_read', 'f_list'], false);
		/* The acl-arrays have the following structure: forum-id -> array of permissions -> array of users
		 * See http://www.lithotalk.de/docs/auth_api.html for details
		 */

		$db = $this->db;
		$parent_relation = array();
		$sql = 'SELECT forum_id, parent_id FROM '. FORUMS_TABLE;
		$sql .= ' WHERE parent_id != 0';
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result))
		{
			$parent_relation[$row['parent_id']][] = $row['forum_id'];
		}
		$db->sql_freeresult($result);
		//Remove the permission, if the parent does not have at least the read or list permission also
		foreach ($parent_relation as $parent_id => $children)
		{
			if (!isset($acls_read_or_list[$parent_id]))
			{
				// No permission for parent. Remove permission of all children
				foreach ($children as $child)
				{
					unset($acls[$child]);
				}
			}
		}
		//Convert into an easier to use array
		foreach ($acls as $forum_id => $rights)
		{
			if (!isset($rights['f_read']))
			{
				continue;
			}
			$permissions[$forum_id]['post'] = isset($rights['f_post']);
			$permissions[$forum_id]['reply'] = isset($rights['f_reply']);
			$permissions[$forum_id]['moderated'] = !isset($rights['f_noapprove']);
		}
		return $permissions;
	}

	public function getForumName($user_id, $forum_id)
	{
		$forums = $this->selectAllForums($user_id, $forum_id);
		$first = reset($forums);
		$forumName = $first['title'] ?? false;
		return $forumName;
	}

	/** Read all forums the user has at least reading permission for.
	 *  The result array has the fields
	 *	- id
	 *	- title
	 *	- lastTopicTitle
	 *	- lastTopicDate
	 *	- lastTopicAuthor
	 *	- post (true/false for post permission)
	 *	- moderated (true/false whether posts must be approved)
	 *
	 * @param int $user_id
	 * @param int $forum_id
	 */
	public function selectAllForums($user_id, $forum_id = 0)
	{
		$forum_permissions = $this->getForumsPermissions($user_id);

		$db = $this->db;
		$forums = array();

		$sql = 'SELECT forum_id, forum_name, forum_last_post_subject, forum_last_post_time, forum_last_poster_name FROM '. FORUMS_TABLE;
		$sql .= ' WHERE forum_type = 1'; //real forum, no parent group
		$sql .= ' AND forum_status = 0'; //not deleted or locked
		if ($forum_id)
		{
			$sql .= " AND forum_id = $forum_id"; //single selection
		}
		$sql .= ' ORDER BY forum_last_post_id DESC'; //Most current first
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result))
		{
			$forum_id = $row['forum_id'];
			if (isset($forum_permissions[$forum_id]))
			{
				$forums[] = array( 'id' => $forum_id,
								'title' => $row['forum_name'],
								'lastTopicTitle' => $row['forum_last_post_subject'],
								'lastTopicDate' => $row['forum_last_post_time'],
								'lastTopicAuthor' => $row['forum_last_poster_name'],
								'post' => $forum_permissions[$forum_id]['post'],
								'reply' => $forum_permissions[$forum_id]['reply'],
								'moderated' => $forum_permissions[$forum_id]['moderated']
				);
			}
		}
		$db->sql_freeresult($result);
		return $forums;
	}

	/** Read all topics from a given forum.
	 * Result is an array which maps the forum_id to an array with title and date.
	 * Whether the user has permission to read the forum, must have been checked
	 * before !
	 * (Currently implemented in webhook->onAllForumTopics)
	 */
	public function selectForumTopics($user_id, $forum_id)
	{
		$db = $this->db;
		$topics = array();

		$sql = 'SELECT topic_id, topic_title, topic_time, topic_type, topic_visibility FROM '. TOPICS_TABLE;
		$sql .= " WHERE forum_id = $forum_id";
		$sql .= ' AND topic_visibility != ' . ITEM_DELETED;
		$sql .= ' AND (topic_visibility = ' . ITEM_APPROVED . " OR topic_poster = $user_id)";
		$sql .= ' ORDER BY topic_type DESC,'; //Announcements first
		$sql .= ' topic_last_post_time DESC'; //latest active Topic first
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result))
		{
			$topics[] =
				array( 'topic_id' => $row['topic_id'],
					   'title' => $row['topic_title'],
					   'date' => $row['topic_time'],
					   'type' => $row['topic_type'],
					   'approved' => $row['topic_visibility'] == ITEM_APPROVED
					);
		}
		$db->sql_freeresult($result);
		return $topics;
	}

	/** Read all posts of a given topic.
	 * Result is an array which maps the post_id to an array with text, username and time.
	 * Only the first entry contains in addition the title and the reply(permission)-flag.
	 */
	public function selectTopicPosts($user_id, $topic_id)
	{
		$db = $this->db;
		$posts = array();

		$sql = 'SELECT t1.post_id, t1.forum_id, t1.post_text, t1.post_time, t1.poster_id, t1.post_visibility, t2.username, t3.topic_title, t3.topic_status ';
		$sql .= ' FROM '. POSTS_TABLE . ' as t1';
		$sql .= ' LEFT JOIN '. USERS_TABLE . ' as t2 ON t1.poster_id = t2.user_id';
		$sql .= ' LEFT JOIN '. TOPICS_TABLE . ' as t3 ON t1.topic_id = t3.topic_id';
		$sql .= " WHERE t1.topic_id = $topic_id";
		$sql .= ' AND t1.post_visibility != ' . ITEM_DELETED;
		$sql .= ' AND (t1.post_visibility = ' . ITEM_APPROVED . " OR t1.poster_id = $user_id)";
		$sql .= ' ORDER BY t1.post_id';  //Old to new
		$result = $db->sql_query($sql);
		$forum_id = 0;
		$title = '';
		while ($row = $db->sql_fetchrow($result))
		{
			$forum_id = $row['forum_id']; //Same for all rows
			$title = $row['topic_title']; //Same for all rows
			$locked = $row['topic_status'] == ITEM_LOCKED; //Same for all rows
			$posts[] = array('text' => $row['post_text'],
							'username' => $row['username'],
							'time' => $row['post_time'],
							'approved' => $row['post_visibility'] == ITEM_APPROVED);
		}
		$db->sql_freeresult($result);
		//Check permission for the forum
		$permissions = $this->getForumsPermissions($user_id);

		if (!isset($permissions[$forum_id]))
		{
			return array();
		}
		$posts[0]['title'] = $title;
		$posts[0]['reply'] = $permissions[$forum_id]['reply'] && !$locked;
		return $posts;
	}

	public function update_message_id($chat_id, $message_id = 0)
	{
		if (!$chat_id)
		{
			return;
		}
		$sql = 'UPDATE phpbb_eb_telegram_chat' ;
		$sql .= ' SET message_id = \'' . $this->db->sql_escape($message_id) . '\'';
		$sql .= ' WHERE chat_id = \'' . $this->db->sql_escape($chat_id) . '\'';
		$this->db->sql_query($sql);
	}

	public function store_forum_id($chat_id, $forum_id)
	{
		if (!$chat_id)
		{
			return;
		}
		$sql = 'INSERT INTO phpbb_eb_telegram_chat' ;
		$sql .= " (chat_id, forum_id) VALUES('$chat_id', '$forum_id')";
		$sql .= ' ON DUPLICATE KEY UPDATE ';
		$sql .= " forum_id = '$forum_id'";
		$sql .= ', page = 0'; //New forum starts with first page
		$this->db->sql_query($sql);
	}

	/** Following states are used:
	 * V: User is not yet verified. Expected Code is stored in title-field.
	 * F: List of forums is displayed.
	 * T: List of topics is displayed.
	 * P: Display the topic (or post) given by topic_id
	 * 1: Wait for new reply for given topic_id
	 * 2: Wait for title of new topic in current forum
	 * 3: Wait for text of new topic. Previously sent title is stored in title field
	 */
	public function store_telegram_chat_state($chat_id, $topic_id = 0, $state = 0, $title = '', $page = -1)
	{
		if (!$chat_id)
		{
			return;
		}
		$title_escaped = $this->db->sql_escape($title);
		while (strlen($title_escaped) > 120)
		{
			$title = substr($title, 0, -1);
			$title_escaped = $this->db->sql_escape($title);
		}

		$sql = 'INSERT INTO phpbb_eb_telegram_chat';
		$sql .= ' (chat_id, message_id, forum_id, topic_id, state, title, page)';
		$sql .= " VALUES('$chat_id', 0, 0, '$topic_id', '$state', '$title', 0)";
		$sql .= ' ON DUPLICATE KEY UPDATE';
		$sql .= " topic_id = '$topic_id'";
		$sql .= ", state = '$state'";
		$sql .= ", title = '$title_escaped'";
		if ($page >= 0)
		{
			$sql .= ", page = '$page'";
		}
		$this->db->sql_query($sql);
	}

	public function delete_telegram_chat_state($chat_id)
	{
		$sql = 'DELETE FROM phpbb_eb_telegram_chat';
		$sql .= ' WHERE chat_id = \'' . $this->db->sql_escape($chat_id) . '\'';
		$this->db->sql_query($sql);
	}

	public function select_telegram_chat_state($chat_id = false)
	{
		$telegram_data = array();
		$db = $this->db;
		$sql = 'SELECT chat_id, message_id, forum_id, topic_id, state, title, page FROM phpbb_eb_telegram_chat';
		if ($chat_id)
		{
			$sql .= " WHERE chat_id = $chat_id";
		}
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result))
		{
			$telegram_data[] = $row;
		}
		$db->sql_freeresult($result);
		if (isset($telegram_data))
		{
			return $chat_id ? $telegram_data[0] : $telegram_data;
		} else
		{
			return false;
		}
	}

	/** Read an array of users where the key is the telegram_id and
	 * the value is the username.
	 * Needed to map user-id to user_name e.g. in existing posts.
	 */
	public function read_all_users()
	{
		$db = $this->db;
		$users = array();

		$sql = 'SELECT user_id, username, user_telegram_id FROM '. USERS_TABLE;
			$sql = $sql . ' WHERE ( user_type = 0 OR user_type = 3)';
			$result = $db->sql_query ( $sql );
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result))
		{
			$users[] = $row;
		}
		$db->sql_freeresult($result);
		return $users;
	}

	/** Find the user(s) with a given telegram-id.
	 */
	public function find_telegram_user($telegram_id)
	{
		$db = $this->db;
		$users = array();

		$sql = 'SELECT user_id, username, user_colour, user_email, user_lang, user_telegram_id FROM '. USERS_TABLE ;
		$sql .= ' WHERE ( user_type = 0 OR user_type = 3)';
		$sql .= " AND user_telegram_id = '$telegram_id'";
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result))
		{
			$users[] = $row;
		}
		$db->sql_freeresult($result);
		return $users;
	}

	/** Get the telegram specific permissions for the user.
	 * Returns an array containing all telegram specific permissions assigned to true or false.
	 */
	public function read_telegram_permissions($user_id)
	{
		$permissions = array('u_ebt_notify' => false, 'u_ebt_browse' => false, 'u_ebt_post' => false);
		$acls = $this->auth->acl_get_list($user_id, array_keys($permissions), false);
		foreach ($permissions as $key => $has_permission)
		{
			$permissions[$key] = isset($acls[0][$key]);
		}
		return $permissions;
	}


	public function send_email($user)
	{
		$bytes = random_bytes(6);
		$code = substr(strtr(base64_encode($bytes), '+/', '-_'), 0, 6);
		if (!class_exists('messenger'))
		{
			include($this->phpbb_root_path . 'includes/functions_messenger.' . $this->php_ext);
		}
		//Parameter false: Don't queue the message
		$messenger = new \messenger(false);
		$messenger->template('@eb_telegram/verify_telegram_id', $user['user_lang'], '', '');

		$messenger->set_addresses($user);

		$messenger->assign_vars(array(
			'USERNAME' => $user['username'],
			'CODE' => $code,
			'FORUM_NAME' => $this->config['sitename'],
		));

		$messenger->send(NOTIFY_EMAIL);
		return $code;
	}


	/* Insert a new post either as new topic, or as answer to an existing topic.
	In case of a new topic, parameter new_topic must be set to true.
	In this case, the topic_id_or_title contains the title for the new topic,
	otherwise, the topic-id is contained in this parameter. */
	public function insertNewPost($new_topic, $forum_id, $topic_id_or_title, $text, $author)
	{
		global $phpbb_root_path, $phpEx;
		$user = $this->user;
		$auth = $this->auth;

		//In case of adding to an existing topic, the topic must exist
		if ($new_topic)
		{
			$required_permission = 'post';
			$topic_title = $topic_id_or_title;
		} else
		{
			$required_permission = 'reply';
			$topic_id  = $topic_id_or_title;
			$existingPosts = $this->selectTopicPosts($author['user_id'], $topic_id);
			if (count($existingPosts) == 0)
			{
				return false;
			}
			//Fetch the title from the first post
			$topic_title = reset($existingPosts)['title'];
		}
		$forums = $this->selectAllForums($author['user_id'], $forum_id);
		$forum = reset($forums);
		if (!$forum || !$forum[$required_permission])
		{
			//Usually this should not happen, because the corresponding
			//buttons are not offered, if no permission exists.
			return false;
		}

		// Now submit the post
		if (!function_exists('submit_post'))
		{
			include("{$phpbb_root_path}includes/functions_posting.{$phpEx}");
		}
		// variables to hold the parameters for submit_post
		$poll = $uid = $bitfield = $options = '';
		// Append info, that post was sent via telegram
		if (isset($this->config['eb_telegram_footer']))
		{
			$text .= PHP_EOL . PHP_EOL . $this->config['eb_telegram_footer'];
		}
		generate_text_for_storage($text, $uid, $bitfield, $options, true, true, true);

		$data = array(
			'forum_id'      => $forum_id,
			'icon_id'       => false,
			//'poster_id'     => nn,

			'enable_bbcode'     => true,
			'enable_smilies'    => true,
			'enable_urls'       => true,
			'enable_sig'        => true,

			'message'       => $text,
			'message_md5'   => md5($text),

			'bbcode_bitfield'   => $bitfield,
			'bbcode_uid'        => $uid,

			'post_edit_locked'  => 0,
			'topic_title'       => $topic_title, //Only used in email notification
			'notify_set'        => false,
			'notify'            => false,
			'post_time'         => 0,
			'forum_name'        => $forum['title'], //For email notification
			'enable_indexing'   => true,
			'force_approved_state' => $forum['moderated'] ? ITEM_UNAPPROVED : ITEM_APPROVED,
			//'force_visibility'  => false, //Same as force_approved
		);
		if ($topic_id)
		{
			$data['topic_id'] = $topic_id;
		}

		//To achieve, that the post occurs under the correct user,
		//we need to temporarily replace user_id, username and even the colour.
		$userOrigData = $user->data;
		$relevantUserProps = array('user_id', 'username' , 'user_colour');
		foreach ($relevantUserProps as $prop)
		{
			$user->data[$prop] = $author[$prop];
		}
		//See https://wiki.phpbb.com/Function.submit_post
		//and https://wiki.phpbb.com/Using_phpBB3%27s_Basic_Functions
		//topic_title from here will be used for the title of the POST
		if ($new_topic)
		{
			$action = 'post';
		} else
		{
			$action = 'reply';
		}
		$url = submit_post($action, $topic_title, $author['username'], POST_NORMAL, $poll, $data);
		//Reset the original user information
		foreach ($relevantUserProps as $prop)
		{
			$user->data[$prop] = $userOrigData[$prop];
		}
		//For new topics, topic_id was set in submit_post.
		return $url ? $data['topic_id'] : false;
	}

	private function print_formatted($obj)
	{
		$tmp = print_r($obj, true);
		$tmp = \str_replace("\n", "\n<br>", $tmp);
		echo "\n<br>$tmp\n<br>";
	}
}
