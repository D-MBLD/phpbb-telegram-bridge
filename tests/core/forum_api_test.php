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

class forum_api_test extends \phpbb_test_case
{
	public function setUp(): void
	{
		parent::setUp();
		$this->config = $this->getMockBuilder('\phpbb\config\config')
			->disableOriginalConstructor()
			->getMock();
		$this->db = $this->getMockBuilder('\phpbb\db\driver\driver_interface')
			->disableOriginalConstructor()
			->getMock();
		$this->user = $this->getMockBuilder('\phpbb\user')
			->disableOriginalConstructor()
			->getMock();
		$this->auth = $this->getMockBuilder('\phpbb\auth\auth')
			->disableOriginalConstructor()
			->getMock();

		//Now create a non-mocked forum_api class
		$this->forum_api = new \eb\telegram\core\forum_api(
			$this->config,
			$this->db,
			$this->user,
			$this->auth,
			'', //root_path
			'' //ext
		);

		//We can always use the same user_id
		$this->user_id = 123;
	}

	/** DataProvider for test-function test_create_command_for_button_callback */
	public function data_provider()
	{
		$user_id = $this->user_id;
		$dataPairs = array();
		
		//Create an array of parameters passed to the test function
		$acls = array('0' => array('u_ebt_notify'=>array($user_id), 'u_ebt_browse'=>array($user_id)));
		$expected = array('u_ebt_notify' => true, 'u_ebt_browse' => true, 'u_ebt_post' => false);
		$dataPairs[] = [$acls, $expected];

		$acls = array();
		$expected = array('u_ebt_notify' => false, 'u_ebt_browse' => false, 'u_ebt_post' => false);
		$dataPairs[] = [$acls, $expected];

		return $dataPairs;
	}

	
	/** Test the conversion of the acl_list_call.
	 * @dataProvider data_provider
	 */
	public function test_read_telegram_permissions($acls, $expected_permissions)
	{		
		$this->auth->expects($this->once())
			->method('acl_get_list')
			->with($user_id)
			->willReturn($acls);
		
		$permissions = $this->forum_api->read_telegram_permissions($user_id);
		$this->assertEquals($expected_permissions, $permissions);
	}
}
