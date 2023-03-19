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

//See https://area51.phpbb.com/docs/dev/master/extensions/tutorial_testing.html
//phpunit docu:
//https://phpunit.de/manual/6.5/en/test-doubles.html#test-doubles.stubs.examples.StubTest3.php

class command_pattern_test extends \phpbb_test_case
{
	/** @var \eb\telegram\core\webhook */
	private $webhook;

	public function setUp(): void
	{
		parent::setUp();
		$this->config = $this->getMockBuilder('\phpbb\config\config')
			->disableOriginalConstructor()
			->getMock();
		$this->language = $this->getMockBuilder('\phpbb\language\language')
			->disableOriginalConstructor()
			->getMock();
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
		$this->auth = $this->getMockBuilder('\phpbb\auth\auth')
			->disableOriginalConstructor()
			->getMock();		
		$this->helper = $this->getMockBuilder('\phpbb\controller\helper')
			->disableOriginalConstructor()
			->getMock();
		$this->request = $this->getMockBuilder('\phpbb\request\request')
			->disableOriginalConstructor()
			->getMock();
		$this->telegram_api = $this->getMockBuilder('\eb\telegram\core\telegram_api')
			->disableOriginalConstructor()
			->setMethods(['sendOrEditMessage'])
			->getMock();
		$this->forum_api = $this->getMockBuilder('\eb\telegram\core\forum_api')
			->disableOriginalConstructor()
			->getMock();
		//Now create a non-mocked commands and webhook class
		$this->commands = new \eb\telegram\core\commands(
			$this->config,
			$this->language,
			$this->forum_api
		);
		$this->webhook = new \eb\telegram\core\webhook(
			$this->config,
			$this->language,
			$this->helper,
			$this->request,
			$this->telegram_api,
			$this->forum_api,
			$this->commands
		);
	}

	private function defaultSetup() {
		$users[] = array('user_id' => 123, 'username' => 'Test User', 'user_lang' => 'en', 'user_telegram_id' => 12345);
		$this->forum_api->expects($this->any())
			->method('find_telegram_user')
			->with('123')
			->willReturn($users);
	}

	public function test01_howToRegister()
	{
		//Test if admin receives the information also
		$this->config->expects($this->any())
		->method('offsetGet')
		->willReturnMap([ //Map param(s) to return value
			['eb_telegram_admin_telegram_id', '12345'],
		]);
		$this->config->expects($this->any())
		->method('offsetExists')
		->willReturnMap([ //Map param(s) to return value
			['eb_telegram_admin_telegram_id', true],
		]);

		// No default-setup: Don't return a user for this testcase
		$this->forum_api->expects($this->once())
			->method('find_telegram_user')
			->with('123')
			->willReturn(array());
		//In this case, we expect two calls, one for the user, and one for the admin.
		$user_text = '/^EBT_HELP_SCREEN_NON_MEMBER/';
		$admin_text = '/Request from unregistered user/';
		$this->telegram_api->expects($this->exactly(2))
			->method('sendOrEditMessage')
			->withConsecutive(
				[$this->callback(function($input) use ($user_text) { return $this->accept_text_and_buttons($input, $user_text); })],
				[$this->callback(function($input) use ($admin_text) { return $this->accept_text_and_buttons($input, $admin_text); })]
			);
		
		$input = $this->create_button_input('allForums'); //try with a valid input
		$command = $this->webhook->process_input($input);
	}

	public function test02_registration_not_yet_verified()
	{
		$this->defaultSetup();
		//No chat state
		$this->set_chat_state(false);
		$this->assert_telegram_output('/^EBT_HELP_SCREEN_REGISTRATION_FAILED$/');
		
		$input = $this->create_button_input('allForums'); //try with a valid input
		$command = $this->webhook->process_input($input);
	}

	public function test03_registration_failed()
	{
		$this->defaultSetup();
		$this->set_chat_state(array('state' => 'V', 'title' => 'xyz'));

		$this->assert_telegram_output('/^EBT_ILLEGAL_CODE.*EBT_HELP_SCREEN_REGISTRATION_FAILED$/s');
		
		$input = $this->create_text_input('abc');
		$command = $this->webhook->process_input($input);
	}

	public function test04_registration_email_requested()
	{
		$this->defaultSetup();
		$this->set_chat_state(array('state' => 'V'));

		$this->assert_telegram_output('/^EBT_HELP_SCREEN_EMAILED$/');
		
		$input = $this->create_button_input('requestEmail');
		$command = $this->webhook->process_input($input);
	}

	public function test05_registration_ok()
	{
		$this->defaultSetup();
		$this->set_chat_state(array('state' => 'V', 'title' => 'xyz'));
		//In the following pattern, * must also match EOL, therefore the s modifier
		$this->assert_telegram_output('/^EBT_HELP_SCREEN_REGISTERED.*EBT_PERMISSION_TITLE/s');
		
		$input = $this->create_text_input('xyz');
		$command = $this->webhook->process_input($input);
	}

	/** Test button press with wrong message id */
	public function test06_buttonOutdated()
	{
		$this->defaultSetup();
		$this->set_chat_state(array('message_id' => '42'));
		$this->assert_telegram_output('/^EBT_BUTTON_OUTDATED$/');
		
		$input = $this->create_button_input('allForums'); //try with a valid input
		$command = $this->webhook->process_input($input);
	}

	/** Test illegal button press with unset message id */
	public function test07_buttonOutdated()
	{
		$this->defaultSetup();
		$this->set_chat_state(array('message_id' => '0'));
		$this->assert_telegram_output('/^EBT_PERMISSION_TITLE/');
		
		$input = $this->create_button_input('allForums'); //try with a valid input, but no permission
		$command = $this->webhook->process_input($input);
	}

	/** Test back with chat status 'F' */
	public function test10_backToAllForums()
	{
		$this->defaultSetup();
		$this->set_chat_state(array('state' => 'F'));

		$permissions = array('u_ebt_notify' => false, 'u_ebt_browse' => true, 'u_ebt_post' => false);
		$this->forum_api->expects($this->any())
			->method('read_telegram_permissions')
			->with('123')
			->willReturn($permissions);
		$this->forum_api->expects($this->any())
			->method('selectAllForums')
			->willReturn(array());

		$this->assert_telegram_output('/^EBT_FORUM_LIST_TITLE/');
		
		$input = $this->create_button_input('back'); //try with a valid input, but no permission
		$command = $this->webhook->process_input($input);
	}

	/** Try to show a forum, whith no permission */
	public function test11_illegalForums()
	{
		$this->defaultSetup();
		$this->set_chat_state();
		$permissions = array('u_ebt_notify' => false, 'u_ebt_browse' => true, 'u_ebt_post' => false);
		$this->forum_api->expects($this->any())
			->method('read_telegram_permissions')
			->with('123')
			->willReturn($permissions);
		//If user has no permission, selected forum will not be returned.
		$this->forum_api->expects($this->any())
			->method('selectAllForums')
			->willReturn(array());

		$this->assert_telegram_output('/^EBT_ILLEGAL_FORUM$/');
		
		$input = $this->create_button_input('allForumTopics'); //try with a valid input, but no permission
		$command = $this->webhook->process_input($input);
	}

	private function assert_telegram_output($expected_text, $buttons = array()) {
		$this->telegram_api->expects($this->once())
			->method('sendOrEditMessage')
			->with($this->callback(function($input) use ($expected_text) { return $this->accept_text_and_buttons($input, $expected_text); }));
	}

	private function set_chat_state($state = array()) {
		//Set the default first.
		$default_state = array('chat_id' => 123,
			'message_id' => 345,
			'forum_id' => 3,
			'topic_id' => 0,
			'state' => '',
			'title' => '',
			'page' => 0);
		if ($state === false)
		{ 
			$chat_state = false;
		} else
		{
			$chat_state = array_merge($default_state, $state);
		}
		$this->forum_api->expects($this->once())
			->method('select_telegram_chat_state')
			->willReturn($chat_state);
	}

	private function accept_text_and_buttons($input, $pattern) {
		return (bool)preg_match($pattern, $input['text']);
	}

	private function create_button_input($button) {
		$json = '{"callback_query":' .
			'{"from": {"id":"123"},' .
			' "message":{"chat":{"id":"123"},' .
			 '           "message_id":"345"},' .
			 '"data":"' . $button . '"}}';
		$payload = json_decode($json);
		return $payload;
	}
	private function create_text_input($text) {
		$json = '{"message": {' .
				'	"message_id": 345,' .
				'	"from": {' .
				'		"id": 123' .
				'	},' .
				'	"chat": {' .
				'		"id": 123' .
				'	},' .
				'	"text": "' . $text . '"' .
				'}}';
		$payload = json_decode($json);
		return $payload;
	}

}
