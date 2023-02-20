<?php
/**
 *
 * Telegram Bridge. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Edgar Bolender
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace eb\telegram\event;

/**
 * @ignore
 */
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Telegram Bridge Event listener.
 */
class main_listener implements EventSubscriberInterface
{
	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup'						 => 'load_language_on_setup',
			'core.ucp_profile_modify_profile_info'	 => 'ucp_profile_modify_profile_info',
			'core.ucp_profile_info_modify_sql_ary'	 => 'ucp_profile_info_modify_sql_ary',
			'core.acp_users_modify_profile'			 => 'acp_users_modify_profile',
			'core.acp_users_profile_modify_sql_ary'	 => 'acp_users_profile_modify_sql_ary',
			'core.ucp_profile_validate_profile_info' => 'ucp_profile_validate_profile_info',
		];
	}

	/* @var \phpbb\request\request  */
	protected $request;

	/* @var \phpbb\user  */
	protected $user;

	/* @var \phpbb\language\language */
	protected $language;

	/* @var \phpbb\controller\helper */
	protected $helper;

	/* @var \phpbb\template\template */
	protected $template;

	/** @var string phpEx */
	protected $php_ext;

	/** @var string eb\telegram\core\forum_api */
	protected $forum_api;

	/** A map post_id => poster_id for temporarily storing the poster of a new post,
	 * while it is reset for notificaton selection (i.e. the poster himself also receives
	 * a notification)
	 */
	private $poster_id;

	/**
	 * Constructor
	 *
	 * @param \phpbb\language\language	$language	Language object
	 * @param \phpbb\controller\helper	$helper		Controller helper object
	 * @param \phpbb\template\template	$template	Template object
	 * @param string                    $php_ext    phpEx
	 */
	public function __construct(\phpbb\request\request $request,
								\phpbb\user $user,
								\phpbb\language\language $language,
								\phpbb\controller\helper $helper,
								\phpbb\template\template $template,
								$php_ext,
								\eb\telegram\core\forum_api $forum_api)
	{
		$this->request  = $request;
		$this->user     = $user;
		$this->language = $language;
		$this->helper   = $helper;
		$this->template = $template;
		$this->php_ext  = $php_ext;
		$this->forum_api = $forum_api;
	}

	/**
	 * Load common language files during user setup
	 *
	 * @param \phpbb\event\data	$event	Event object
	 */
	public function load_language_on_setup($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = [
			'ext_name' => 'eb/telegram',
			'lang_set' => 'common',
		];
		$event['lang_set_ext'] = $lang_set_ext;
	}

	/**
	* Modify user data on editing profile in ACP
	* Event-Data: data, submit, user_id, user_row
	* Move the telegram-id to the event, such that it can be used
	* in the ..._sql_ary events, below.
	*/
	public function acp_users_modify_profile($event)
	{
		if ($event['submit'])
		{
			$telegram =  $this->request->variable('telegram', '');
		} else
		{
			$telegram =  $event['user_row']['user_telegram_id'];
		}
		//$telegram =  $this->request->variable('telegram', $event['user_row']['user_telegram_id']);
		$event['user_row'] = array_merge($event['user_row'], array(
			'user_telegram_id'	=> $telegram,
		));
		$this->set_template_field($telegram);
	}

	/**
	* Modify user data on editing profile in UCP
	* Event-Data: data, submit
	*/
	public function ucp_profile_modify_profile_info($event)
	{
		if ($event['submit'])
		{
			$telegram =  $this->request->variable('telegram', '');
		} else
		{
			$telegram =  $this->user->data['user_telegram_id'];
		}
		// $telegram =  $this->request->variable('telegram', $this->user->data['user_telegram_id']);
		$event['data'] = array_merge($event['data'], array(
			'user_telegram_id'	=> $telegram,
		));

		$this->set_template_field($telegram);
	}

	/**
	 * Add the TelegramID field to profile
	 */
	private function set_template_field($telegram_id)
	{
		$this->template->assign_vars(array(
			'TELEGRAM'		=> $telegram_id,
		));
	}

	/**
	* Validate user data on editing profile in UCP
	* Event-Data: data, error, submit
	*/
	public function ucp_profile_validate_profile_info($event)
	{
		$errors = $event['error'];
		$error = array();
		$telegram_id = $event['data']['user_telegram_id'];
		$previous_id = $this->user->data['user_telegram_id'];
		if ($telegram_id)
		{
			if (!is_numeric($telegram_id))
			{
				$error[] = 'TELEGRAM_ID_NOT_NUMERIC';
				$event['error'] = array_merge($errors, $error);
			}
			$users = $this->forum_api->find_telegram_user($telegram_id);
			$current_users_id = $this->user->data['user_id'];
			$users = array_filter($users, function($val) use ($current_users_id)
										{
											return $val['user_id'] != $current_users_id;
										});
			if (count($users) > 0)
			{
				$error[] = 'TELEGRAM_ID_ALREADY_USED';
				$event['error'] = array_merge($errors, $error);
			}
		}
		if (empty($errors) && $previous_id && $telegram_id != $previous_id)
		{
			//Telegram id is changed 
			$users = $this->forum_api->delete_telegram_chat_state($previous_id);
		}
	}

	/**
	* Modify profile data in UCP before submitting to the database
	* Event-Data: cp_data, data, sql_ary
	*/
	public function ucp_profile_info_modify_sql_ary($event)
	{
		$this->add_telegram_id_to_sql_ary($event, $event['data']['user_telegram_id']);
	}

	/**
	* Modify profile data in ACP before submitting to the database.
	* Event-Data: cp_data, data, sql_ary, user_id, user_row
	*/
	public function acp_users_profile_modify_sql_ary($event)
	{
		$this->add_telegram_id_to_sql_ary($event, $event['user_row']['user_telegram_id']);
	}

	public function add_telegram_id_to_sql_ary($event, $telegram_id)
	{
		$event['sql_ary'] = array_merge($event['sql_ary'], array(
			'user_telegram_id' => $telegram_id,
		));
	}

}
