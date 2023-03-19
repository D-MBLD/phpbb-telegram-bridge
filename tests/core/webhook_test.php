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

class webhook_test extends \phpbb_test_case
{
	public function setUp(): void
	{
		parent::setUp();
		$this->config = $this->getMockBuilder('\phpbb\config\config')
			->disableOriginalConstructor()
			->getMock();
		$this->language = $this->getMockBuilder('\phpbb\language\language')
			->disableOriginalConstructor()
			->getMock();
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

	/** DataProvider for test-function test_create_command_for_button_callback */
	public function command_data_provider()
	{
		$dataPairs = array();
		//Create an array of parameters passed to the test function
		$command_in = ['buttonCallback'=>'does not matter'];
		$command_out = ['buttonCallback'=>'does not matter','action' => 'registrationRequired'];
		$dataPairs[] = [$command_in, $command_out];

		$command_in = ['buttonCallback'=>'does not matter', 'title' => 'expected code'];
		$command_out = $command_in;
		$command_out['action'] = 'registrationRequired';
		$dataPairs[] = [$command_in, $command_out];

		$command_in = ['buttonCallback'=>'requestEmail'];
		$command_out = ['buttonCallback'=>'requestEmail','action' => 'registrationEmailed'];
		$dataPairs[] = [$command_in, $command_out];

		$command_in = $command_out = ['buttonCallback'=>'allForumsXXX', 'chatState' => 'anythingDifferentFromVOrEmpty', 'page' => 1];
		$command_out['action'] = 'allForums';
		$dataPairs[] = [$command_in, $command_out];

		$command_in = $command_out = ['buttonCallback'=>'allForumTopics~f123', 'chatState' => 'X', 'page' => 1];
		$command_out['action'] = 'allForumTopics';
		$command_out['forum_id'] = 123;
		$command_out['page'] = 0; //Because new forum was defined
		$dataPairs[] = [$command_in, $command_out];

		$command_in = $command_out = ['buttonCallback'=>'allForumTopics~p123', 'chatState' => 'X'];
		$command_out['action'] = 'allForumTopics';
		$command_out['page'] = 123;
		$dataPairs[] = [$command_in, $command_out];

		$command_in = $command_out = ['buttonCallback'=>'showTopic~t123', 'chatState' => 'X'];
		$command_out['action'] = 'showTopic';
		$command_out['topic_id'] = 123;
		$dataPairs[] = [$command_in, $command_out];

		$command_in = $command_out = ['buttonCallback'=>'newPost~t123', 'chatState' => 'X', 'message_id' => 234];
		$command_out['action'] = 'newPost';
		$command_out['topic_id'] = 123;
		unset($command_out['message_id']);
		$dataPairs[] = [$command_in, $command_out];

		$command_in = $command_out = ['buttonCallback'=>'newTopicTitle', 'chatState' => 'X', 'message_id' => 234];
		$command_out['action'] = 'newTopicTitle';
		unset($command_out['message_id']);
		$dataPairs[] = [$command_in, $command_out];

		return $dataPairs;
	}

	/**
	 * @dataProvider command_data_provider
	 */
	public function test_create_command_for_button_callback($input_cmd, $expected_cmd)
	{
		$method = $this->getPrivateMethod( '\eb\telegram\core\webhook', 'create_command_for_button_callback' );;
		$result = $method->invokeArgs( $this->webhook, array( $input_cmd ) );

		$this->assertEquals($expected_cmd, $result);
	}

	/**
 	 * getPrivateMethod
 	 *
 	 * @author	Joe Sexton <joe@webtipblog.com>
 	 * @param 	string $className
 	 * @param 	string $methodName
 	 * @return	ReflectionMethod
 	 */
	  public function getPrivateMethod( $className, $methodName ) {
		$reflector = new \ReflectionClass( $className );
		$method = $reflector->getMethod( $methodName );
		$method->setAccessible( true );

		return $method;
	}
}
