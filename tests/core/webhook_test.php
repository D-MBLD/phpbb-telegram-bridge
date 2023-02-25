<?php

namespace eb\telegram\core;

//See https://area51.phpbb.com/docs/dev/master/extensions/tutorial_testing.html

class webhook_test extends \phpbb_test_case
{
	public function setUp(): void
	{
		parent::setUp();
		$this->config = $this->getMockBuilder('\phpbb\config\config')
			->disableOriginalConstructor()
			->getMock();
		$this->user = $this->getMockBuilder('\phpbb\user')
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
		//Now create a non-mocked webhook class
		$this->webhook = new \eb\telegram\core\webhook(
			$this->config,
			$this->user,
			$this->auth,
			$this->helper,
			$this->request,
			$this->telegram_api,
			$this->forum_api
		);
	}

	/** DataProvider for test-function test_create_command_for_button_callback */
	public function command_data_provider()
	{
		$dataPairs = array();
		//Create an array of parameters passed to the test function
		$command_in = ['buttonCallback'=>'does not matter'];
		$command_out = ['buttonCallback'=>'does not matter','action' => 'registrationFailed'];
		$dataPairs[] = [$command_in, $command_out];

		$command_in = ['buttonCallback'=>'requestEmail'];
		$command_out = ['buttonCallback'=>'requestEmail','action' => 'registrationEmailed'];
		$dataPairs[] = [$command_in, $command_out];

		$command_in = $command_out = ['buttonCallback'=>'allForumsXXX', 'chatState' => 'anythingDifferentFromVOrEmpty'];
		$command_out['action'] = 'allForums';
		$dataPairs[] = [$command_in, $command_out];

		$command_in = $command_out = ['buttonCallback'=>'allForumTopics~f123', 'chatState' => 'X'];
		$command_out['action'] = 'allForumTopics';
		$command_out['forum_id'] = 123;
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

	/** Test a full call with the command allForums */
	public function test_handle_all_forums()
	{
		$this->config->expects($this->any())
			->method('offsetGet')
			->willReturnMap([ //Map param(s) to return value
				['eb_telegram_secret', 'sec_token'],
				['sitename', 'configured sitename'],
			]);
		$this->webhook->secret_token = 'sec_token';

		$this->user->expects($this->any())
			->method('lang')
			->willReturnArgument(0);

		$this->forum_api->expects($this->once())
			->method('find_telegram_user')
			->with('123')
			->willReturn(array('user_id' => 123, 'username' => 'Test User', 'user_telegram_id' => 12345));

		$chat_state = array( 'chat_id' => 123,
				'message_id' => 345,
				'state' => '0',
				'forum_id' => 17,
				'topic_id' => 0,
				'title' => '' 
			);
		$this->forum_api->expects($this->once())
			->method('select_telegram_chat_state')
			->with('123')
			->willReturn($chat_state);

		$forums[] = array( 'id' => 111,
							'title' => 'forum1',
							'lastTopicTitle' => 'last topic',
							'lastTopicDate' => '443134213',
							'lastTopicAuthor' => 'Last Poster',
							'readonly' => false,
							'moderated' => false
		);

		$this->forum_api->expects($this->any())
			->method('selectAllForums')
			->willReturn($forums);

		$eol = PHP_EOL;
		$expected = array(
			'disable_web_page_preview' => 'true',
			'parse_mode' => 'HTML',
			'text' => "EBT_FORUM_LIST_TITLE{$eol}{$eol} 1: <b>forum1</b>{$eol}EBT_LAST_POST{$eol}last topic$eol" .
					  "<u>___________________________________</u>{$eol}EBT_SELECT_A_FORUM",
			'reply_markup' => array (
				'inline_keyboard' => array (array(
					array(
					'text' => '1: forum1',
					'callback_data' => 'allForumTopics~f111'),
					array(
						'text' => 'EBT_BACK',
						'callback_data' => 'allForumTopics~f17'),
					))
			),
			'chat_id' => '123',
			'message_id' => '345',
		);
		$this->telegram_api->expects($this->once())
			->method('sendOrEditMessage')
			->with($expected);

		$json = '{"callback_query":' .
			'{"from": {"id":"123"},' .
			' "message":{"chat":{"id":"123"},' .
			 '           "message_id":"345"},' .
			 '"data":"allForums"}}';
		$payload = json_decode($json);
		$this->webhook->process_input($payload);
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