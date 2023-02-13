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

	/** Returns an array of forum_ids (key), where the user is allowed to
	 * post to. (And then usually to read also)
	 * Example for the array structure:
	 * Array
	 *(
	 * [43] => Array  //forum_id
	 * (
	 *   [f_post] => Array
	 *	(
	 *	 [0] => 636 //user-id
	 *	 ... More users if user_id was not set
	 *	)
	 *   ... //More permissions if requested
	 *  )
	 * ... //next forum
	 * )
	 *
	 * The array has the following structure: forum-id -> array of permissions -> array of users
	 */
	private function getAllowedForums($user_id)
	{
		// See http://www.lithotalk.de/docs/auth_api.html for details
		$permissions = array('f_post', 'f_read');
		$acls = $this->auth->acl_get_list($user_id, $permissions, false);
		return $acls;
	}

	public function getForumName($user_id, $forum_id)
	{
		$forums = $this->selectAllForums($user_id, $forum_id);
		$first = reset($forums);
		$forumName = $first['title'] ?? false;
		return $forumName;
	}

	/** Read all forums the user has permission for.
	 *  The result array has the fields
	 *	- id
	 *	- title
	 *	- lastTopicTitle
	 *	- lastTopicDate
	 *	- lastTopicAuthor
	 */
	public function selectAllForums($user_id, $forum_id = 0)
	{
		$allowed_forums = $this->getAllowedForums($user_id);

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
			$permission = $allowed_forums[$row['forum_id']] ?? false;
			if ($permission)
			{
				$readonly =  (!isset($permission['f_post']));
				$forums[] = array( 'id' => $row['forum_id'],
								'title' => $row['forum_name'],
								'lastTopicTitle' => $row['forum_last_post_subject'],
								'lastTopicDate' => $row['forum_last_post_time'],
								'lastTopicAuthor' => $row['forum_last_poster_name'],
								'readonly' => $readonly
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
	public function selectForumTopics($forum_id)
	{
		$db = $this->db;
		$topics = array();

		$sql = 'SELECT topic_id, topic_title, topic_time, topic_type FROM '. TOPICS_TABLE;
		$sql .= " WHERE forum_id = $forum_id";
		$sql .= ' AND topic_delete_user = 0';
		$sql .= ' ORDER BY topic_type DESC,'; //Announcements first
		$sql .= ' topic_last_post_time DESC'; //latest active Topic first
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result))
		{
			$topics[$row['topic_id']] =
				array( 'title' => $row['topic_title'],
					   'date' => $row['topic_time'],
					   'type' => $row['topic_type']);
		}
		$db->sql_freeresult($result);
		return $topics;
	}

	/** Read all posts of a given topic.
	 * Result is an array which maps the post_id to an array with text, username and time.
	 * Only the first entry contains in addition the title and the readonly-flag.
	 */
	public function selectTopicPosts($user_id, $topic_id)
	{
		$db = $this->db;
		$posts = array();

		$sql = 'SELECT t1.post_id, t1.forum_id, t1.post_text, t1.post_time, t1.poster_id, t2.username, t3.topic_title ';
		$sql .= ' FROM '. POSTS_TABLE . ' as t1';
		$sql .= ' LEFT JOIN '. USERS_TABLE . ' as t2 ON t1.poster_id = t2.user_id';
		$sql .= ' LEFT JOIN '. TOPICS_TABLE . ' as t3 ON t1.topic_id = t3.topic_id';
		$sql .= " WHERE t1.topic_id = $topic_id";
		$sql .= ' AND t1.poster_id <> 0';  //0 = deleted permantly
		$sql .= ' AND t1.post_delete_user = 0';  //0 = deleted (marked as deleted)
		$sql .= ' ORDER BY t1.post_id';  //Old to new
		$result = $db->sql_query($sql);
		$forum_id = 0;
		$title = '';
		while ($row = $db->sql_fetchrow($result))
		{
			$forum_id = $row['forum_id'];
			$title = $row['topic_title'];
			$posts[] = array('text' => $row['post_text'],
							'username' => $row['username'],
							'time' => $row['post_time']);
		}
		$db->sql_freeresult($result);
		//Check permission given by folder
		$permissions = $this->getAllowedForums($user_id);
		$permission = $permissions[$forum_id] ?? false;
		if (!$permission)
		{
			return array();
		}
		$posts[0]['title'] = $title;
		$posts[0]['readonly'] = !isset($permission['f_post']);
		return $posts;
	}

	public function store_message_id($chat_id, $message_id = 0)
	{
		if (!$chat_id)
		{
			return;
		}
		$sql = 'INSERT INTO phpbb_eb_telegram_chat' ;
		$sql .= " (chat_id, message_id) VALUES('$chat_id', '$message_id')";
		$sql .= ' ON DUPLICATE KEY UPDATE ';
		$sql .= " message_id = '$message_id'";
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
		$this->db->sql_query($sql);
	}

	public function store_telegram_chat_state($chat_id, $topic_id = 0, $state = 0, $title = '')
	{
		if (!$chat_id)
		{
			return;
		}
		$sql = 'INSERT INTO phpbb_eb_telegram_chat';
		$sql .= ' (chat_id, message_id, forum_id, topic_id, state, title)';
		$sql .= " VALUES('$chat_id', 0, 0, '$topic_id', '$state', '$title')";
		$sql .= ' ON DUPLICATE KEY UPDATE';
		$sql .= " topic_id = '$topic_id'";
		$sql .= ", state = '$state'";
		$sql .= ", title = '$title'";
		$this->db->sql_query($sql);
	}

	public function select_telegram_chat_state($chat_id = false)
	{
		$telegram_data = array();
		$db = $this->db;
		$sql = 'SELECT chat_id, message_id, forum_id, topic_id, state, title FROM phpbb_eb_telegram_chat';
		if ($chat_id)
		{
			$sql .= " WHERE chat_id = $chat_id";
		}
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result))
		{
			$telegram_data[] = array( 'chat_id' => $row['chat_id'],
							 'message_id' => $row['message_id'],
							 'state' => $row['state'],
							 'forum_id' => $row['forum_id'],
							 'topic_id' => $row['topic_id'],
							 'title' => $row['title'] );
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

		$sql = 'SELECT user_id, username, user_email, user_telegram_id FROM '. USERS_TABLE ;
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

	public function send_email($user)
	{
		$bytes = random_bytes(6);
		$code = substr(strtr(base64_encode($bytes), '+/', '-_'), 0, 6);
		if (!class_exists('messenger'))
		{
			include($this->phpbb_root_path . 'includes/functions_messenger.' . $this->php_ext);
		}
		$messenger = new \messenger();
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
			$topic_title = $topic_id_or_title;
		} else
		{
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
		if (!$forum || $forum['readonly'])
		{
			return false;
		}

		// Login is needed, when a new post is sent.
		$userName = $this->config['eb_telegram_admin_user'];
		$pw = $this->config['eb_telegram_admin_pw'];

		$login_result = $auth->login($userName, $pw, false);

		//Check for successful login
		if ($login_result['status'] != LOGIN_SUCCESS) {
			$author = $author['username'];
			$error = $login_result['error_msg'];
			return "Check configuration of telegram bridge." . 
					" Login of admin user failed, when $author tried to send a new post or topic." . 
					" Error-Message: $error";
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
			'force_approved_state' => true,
			'force_visibility'  => true,
		);
		if ($topic_id)
		{
			$data['topic_id'] = $topic_id;
		}

		//To achieve, that the post occurs under the correct user,
		//we need to temporarily replace user_id and username.
		$userOrigId = $user->data['user_id'];
		$userOrigName = $user->data['username'];
		$user->data['user_id'] = $author['user_id'];
		$user->data['username'] = $author['username'];
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
		$user->data['user_id'] = $userOrigId;
		$user->data['username'] = $userOrigName;
		return $url ? true : false;
	}

	private function print_formatted($obj) {
		$tmp = print_r($obj, true);
		$tmp = \str_replace("\n", "\n<br>", $tmp);
		echo "\n<br>$tmp\n<br>";
	}
}
