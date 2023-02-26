<?php
/**
 *
 * Telegram Bridge. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Edgar Bolender
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace eb\telegram\tests\controller;

if (!isset($phpbb_root_path))
{
	global $phpbb_root_path;
}
if (!isset($phpEx))
{
	global $phpEx;
}
include($phpbb_root_path . 'includes\functions_acp.' . $phpEx);

class acp_controller_test extends \phpbb_test_case
{
	/** @var \eb\telegram\controller\acp_controller */
	protected $controller;
	
	protected $config;
	protected $language;
	protected $log;
	protected $request;
	protected $template;
	protected $user;
	
	public function setUp(): void
	{
		parent::setUp();

		$this->config = $this->getMockBuilder('\phpbb\config\config')
			->disableOriginalConstructor()
			->getMock();
		$this->language = $this->getMockBuilder('\phpbb\language\language')
			->disableOriginalConstructor()
			->getMock();
		$this->log = $this->getMockBuilder('\phpbb\log\log')
			->disableOriginalConstructor()
			->getMock();
		$this->request = $this->getMockBuilder('\phpbb\request\request')
			->disableOriginalConstructor()
			->getMock();
		$this->template = $this->getMockBuilder('\phpbb\template\template')
			->disableOriginalConstructor()
			->getMock();
		$this->user = $this->getMockBuilder('\phpbb\user')
			->disableOriginalConstructor()
			->getMock();

		$this->controller = new \eb\telegram\controller\acp_controller(
			$this->config,
			$this->language,
			$this->log,
			$this->request,
			$this->template,
			$this->user
		);
	}

	/** Switch off temporarily and try in develop branch. */
	public function xxtest_display_options_submit()
	{
		$config = array();

		$this->config->expects($this->any())
			->method('offsetGet')
			->willReturnCallback(function ($v) use (&$config)
			{
				return $config[$v];
			});
		$this->config->expects($this->any())
			->method('set')
			->willReturnCallback(function ($k,$v) use (&$config)
			{
				$config[$k] = $v;
				return null;
			});
		
		$this->request->expects($this->any())
			->method('is_set_post')
			->willReturnMap([ //Map param(s) to return value
				['submit', true],
				['reset', false],
			]);

		$this->language->expects($this->any())
			->method('lang')
			->willReturnCallback(function ($key, ...$vars)
				{
					$result = "$key";
					foreach($vars as $var) {
						$result .= "~$var~";
					}
					return $result;
				});

		$this->request->expects($this->any())
			->method('variable')
			->willReturnCallback(function ($v)
				{
					return $v . '_value';
				});

		$expected_assignment = array (
			'S_ERROR' => false,
			'ERROR_MSG' => '',
			'U_ACTION' => null,
			'FOOTER_PLACEHOLDER' => 'EBT_SETTINGS_FOOTER_DEFAULT',
			'WEBHOOK' => 'EBT_SETTINGS_WEBHOOK_TEMPLATE~eb_telegram_bot_token_value~~http:/~~eb_telegram_secret_value~',
			'eb_telegram_bot_token' => 'eb_telegram_bot_token_value',
			'eb_telegram_secret' => 'eb_telegram_secret_value',
			'eb_telegram_footer' => 'eb_telegram_footer_value',
			'eb_telegram_admin_telegram_id' => 'eb_telegram_admin_telegram_id_value',
			'eb_telegram_admin_echo' => 'eb_telegram_admin_echo_value',
			 );
		$this->template->expects($this->once())
			->method('assign_vars')
			->with($expected_assignment);

		$response = $this->controller->display_options(true);
	}
}
