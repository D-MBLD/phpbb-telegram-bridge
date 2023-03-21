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
	 * $testcall can be set to avoid difficult mocking of the form_key handling
	 * in the tests.
	 *
	 * @return void
	 */
	public function display_options($testcall = false)
	{
		 /* List of configuration parameters */
		 $configparams = [
			'eb_telegram_bot_token',
			'eb_telegram_secret',
			'eb_telegram_footer',
			'eb_telegram_admin_telegram_id',
			'eb_telegram_admin_echo',
		];

		// Add our common language file
		$this->language->add_lang('common', 'eb/telegram');

		// Create a form key for preventing CSRF attacks
		if (!$testcall)
		{
			add_form_key('eb_telegram_acp');
		}

		// Create an array to collect errors that will be output to the user
		$errors = [];

		// Is the form being submitted to us?
		if ($this->request->is_set_post('reset'))
		{
			$message = '<script>jQuery("document").ready(function(){jQuery("#mydialog").dialog("open")});setTimeout(function(){jQuery("#mydialog").fadeOut();window.location.href="index.php";}, 5000);</script>';
			trigger_error($message);
		}
		if ($this->request->is_set_post('submit'))
		{
			// Test if the submitted form is valid
			if (!$testcall && !check_form_key('eb_telegram_acp'))
			{
				$errors[] = $this->language->lang('FORM_INVALID');
			}

			// If no errors, process the form data
			if (empty($errors))
			{
				foreach ($configparams as $name)
				{
					$this->config->set($name, $this->request->variable($name, '', true));
				}

				// Add option settings change action to the admin log
				$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'EBT_SETTINGS_UPDATED');

				// Option settings have been updated and logged
				// Confirm this to the user and provide link back to previous page
				if (!$testcall) //Would throw a test error
				{
					trigger_error($this->language->lang('EBT_SETTINGS_UPDATED') . adm_back_link($this->u_action));
				}
			}
		}
		$s_errors = !empty($errors);

		$token = $this->config['eb_telegram_bot_token'];
		$secret = $this->config['eb_telegram_secret'];
		$webhook = false;
		if ($token && $secret)
		{
			$root_url = generate_board_url();
			$webhook = $this->language->lang('EBT_SETTINGS_WEBHOOK_TEMPLATE', $token, $root_url, $secret);
		}

		// Set output variables for display in the template
		$assignment['S_ERROR'] = $s_errors;
		$assignment['ERROR_MSG'] = $s_errors ? implode('<br />', $errors) : '';
		$assignment['U_ACTION'] = $this->u_action;
		$assignment['FOOTER_PLACEHOLDER'] = $this->language->lang('EBT_SETTINGS_FOOTER_DEFAULT');
		$assignment['WEBHOOK'] = $webhook;

		foreach ($configparams as $name)
		{
			$assignment[$name] = $this->config[$name];
		}

		$this->template->assign_vars($assignment);
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
