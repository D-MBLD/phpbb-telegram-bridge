<?php
/**
 *
 * Telegram Bridge. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Edgar Bolender
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace eb\telegram\controller;

/**
 * Telegram Bridge ACP controller.
 */
class acp_controller
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\log\log */
	protected $log;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var string Custom form action */
	protected $u_action;

	/**
	 * Constructor.
	 *
	 * @param \phpbb\config\config		$config		Config object
	 * @param \phpbb\language\language	$language	Language object
	 * @param \phpbb\log\log			$log		Log object
	 * @param \phpbb\request\request	$request	Request object
	 * @param \phpbb\template\template	$template	Template object
	 * @param \phpbb\user				$user		User object
	 */
	public function __construct(\phpbb\config\config $config, \phpbb\language\language $language, \phpbb\log\log $log, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user)
	{
		$this->config	= $config;
		$this->language	= $language;
		$this->log		= $log;
		$this->request	= $request;
		$this->template	= $template;
		$this->user		= $user;
	}

	/**
	 * Display the options a user can configure for this extension.
	 *
	 * @return void
	 */
	public function display_options()
	{
		// Add our common language file
		$this->language->add_lang('common', 'eb/telegram');

		// Create a form key for preventing CSRF attacks
		add_form_key('eb_telegram_acp');

		// Create an array to collect errors that will be output to the user
		$errors = [];

		// Is the form being submitted to us?
		if ($this->request->is_set_post('reset')){
			$message = '<script>jQuery("document").ready(function(){jQuery("#mydialog").dialog("open")});setTimeout(function(){jQuery("#mydialog").fadeOut();window.location.href="index.php";}, 5000);</script>'; 
			trigger_error($message);
		}
		if ($this->request->is_set_post('submit'))
		{
			// Test if the submitted form is valid
			if (!check_form_key('eb_telegram_acp'))
			{
				$errors[] = $this->language->lang('FORM_INVALID');
			}

			// If no errors, process the form data
			if (empty($errors))
			{
				// Set the options the user configured
				$this->config->set('eb_telegram_bot_token', $this->request->variable('eb_telegram_bot_token', ''));
				$this->config->set('eb_telegram_secret', $this->request->variable('eb_telegram_secret', ''));
				$this->config->set('eb_telegram_admin_user', $this->request->variable('eb_telegram_admin_user', ''));
				$this->config->set('eb_telegram_admin_pw', $this->request->variable('eb_telegram_admin_pw', ''));
				$this->config->set('eb_telegram_footer', $this->request->variable('eb_telegram_footer', ''));
				$this->config->set('eb_telegram_admin_telegram_id', $this->request->variable('eb_telegram_admin_telegram_id', ''));
				$this->config->set('eb_telegram_admin_echo', $this->request->variable('eb_telegram_admin_echo', ''));

				// Add option settings change action to the admin log
				$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_ACP_TELEGRAM_SETTINGS');

				// Option settings have been updated and logged
				// Confirm this to the user and provide link back to previous page
				trigger_error($this->language->lang('ACP_TELEGRAM_SETTING_SAVED') . adm_back_link($this->u_action));
			}
		}
		$s_errors = !empty($errors);

		$token = $this->config['eb_telegram_bot_token'] ?? '';
		$secret = $this->config['eb_telegram_secret'] ?? '';
		$webhook = false;
		if ($token && $secret) {
			$root_url = $this->config['server_protocol'].$this->config['server_name'].$this->config['script_path'];
			$webhook = sprintf($this->user->lang('ACP_TELEGRAM_WEBHOOK_TEMPLATE'), $token, $root_url, $secret);
		}

		// Set output variables for display in the template
		$this->template->assign_vars([
			'S_ERROR'		=> $s_errors,
			'ERROR_MSG'		=> $s_errors ? implode('<br />', $errors) : '',

			'U_ACTION'		=> $this->u_action,

			'BOT_TOKEN'	=> $token,
			'SECRET'	=> $secret,
			'ADMIN_USER'	=> $this->config['eb_telegram_admin_user'],
			'ADMIN_PW'	=> $this->config['eb_telegram_admin_pw'],
			'FOOTER'	=> $this->config['eb_telegram_footer'],
			'FOOTER_PLACEHOLDER'	=> $this->user->lang('ACP_TELEGRAM_FOOTER_DEFAULT'),
			'WEBHOOK'   => $webhook,
			'ADMIN_TELEGRAM_ID'   => $this->config['eb_telegram_admin_telegram_id'],
			'ADMIN_TELEGRAM_ECHO'   => $this->config['eb_telegram_admin_echo'],
		]);
	}

	/**
	 * Set custom form action.
	 *
	 * @param string	$u_action	Custom form action
	 * @return void
	 */
	public function set_page_url($u_action)
	{
		$this->u_action = $u_action;
	}
}
