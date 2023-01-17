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

/** Provides some test features for the telegram bridge.
 *  In order to use them, at first you need to set your IP-Adress in the constant ALLOWED_CALLER_IP below, such that only you can call the tests.
 *  (No worries, just call one of the links below, and you will see your IP-adress.)
 *  Then the following test calls are possible (after the extension was configured in the ACP):
 *  - Simulate a text input:
 *    https://<server>/<forum-base>/telegram/test?chat_id=<your telegram id>&text=<some text input>
 *  - Simulate a button press (you need to know the command send by the button, either from the code
 *    or from switching on the admin-echo in ACP):
 *    https://<server>/<forum-base>/telegram/test?chat_id=<your telegram id>&command=<button callback data>
 *  - Test html-formatting and escaping by sending a complicated test to the configured telegram admin id.
 *    https://<server>/<forum-base>/telegram/test?test=html
 */

$phpbb_root_path = './../../../../';
$phpEx = substr(strrchr(__FILE__, '.'), 1);

const ALLOWED_CALLER_IP = 'xxx.x.xxx.xxx';

class test {

	private $telegram_api;
	private $webhook;

	/* @var \phpbb\config\config */
	private $config;

	/* @var \phpbb\request\request   */
	private $request;

	/* @var \phpbb\controller\helper   */
	private $helper;

	/**
	* Constructor
	*/
	public function __construct(\phpbb\config\config $config,
								\phpbb\request\request $request,
								\phpbb\controller\helper $helper,
								\eb\telegram\core\telegram_api $telegram_api,
								\eb\telegram\core\webhook $webhook
								)
	{
		$this->config = $config;
		$this->request = $request;
		$this->helper = $helper;
		$this->telegram_api = $telegram_api;
		$this->webhook = $webhook;
	}

	public function handle()
	{
		//Set the header here, such that the forum does not complain.
		header('HTTP/1.1 200 OK');
		header('Content-Type: text/html; charset=utf-8');
		
		$this->check_caller($this->request, ALLOWED_CALLER_IP);
		$this->dispatch_request();

		return $this->helper->render('blank.html', 'Telegram Webhook');

	}

	private function dispatch_request() {
		//Simulate text-input or the data of an inline button with the url-parameter 'text' or 'command'
		$command = $this->request->variable('command','~~~');
		$text = $this->request->variable('text','~~~');
		$test = $this->request->variable('test','~~~');
		$chat_id = $this->request->variable('chat_id', $this->config['eb_telegram_admin_telegram_id']);
		
		if ($command != '~~~') {
			$this->simulate_button_callback($command, $chat_id);
		} else if ($text != '~~~') {
			$this->simulate_text_input($text, $chat_id);
		} else if ($test == 'html'){
			$this->test_html_escape();
		} else {
			$this->echo_help_text();
		}
	}

	private function simulate_button_callback($command, $chat_id) {
		$json = '{"callback_query":' . 
					'{"from":{"id":"' . $chat_id . '"},' . 
					 '"message":{"chat":{"id":"' . $chat_id . '"}},' .
					 '"data":"' . $command .
				'"}}';
		$payload = json_decode($json);
		echo '<pre>';
		$this->webhook->process_input($payload, true);
		echo '</pre>';
	}
	
	private function simulate_text_input($text, $chat_id) {
		$json = '{"message":' .
					'{"from":{"id":"' . $chat_id . '"},' .
					 '"chat":{"id":"' . $chat_id . '"},' .
					 '"text":"' . $text . '"}}';
		$payload = json_decode($json);
		echo '<pre>';
		$this->webhook->process_input($payload, true);
		echo '</pre>';
	}

	private function echo_help_text() {
		$path = $this->config['server_protocol'] . $this->config['server_name'] . $this->config['script_path'] . '/telegram/test';
		echo 'You may use one of the following URLs for testing:';
		echo '<ul>';
		echo '<li> send a html formatted test message to the configured admin telegram user:';
		echo "<br>   <a href=\"$path?test=html\">$path?test=html</a>";
		echo '</li>';
		echo '<li> simulate how the bot sends a text called from the given chat_id:';
		echo "<br>   <a href=\"$path?chat_id=&lt;chat_id&gt;&amp;text=&lt;some text&gt;\">$path?chat_id=&lt;chat_id&gt;&amp;text=&lt;some text&gt;</a>";
		echo '</li>';
		echo '<li> simulate how the bot sends a button callback from the given chat_id:';
		echo "<br>   <a href=\"$path?chat_id=&lt;chat_id&gt;&amp;command=&lt;button callback data&gt;\">$path?chat_id=&lt;chat_id&gt;&amp;command=&lt;button callback data&gt;</a>";
		echo '</li>';
		echo '</ul>';
		die();
	}

	private function test_html_escape() {
		echo '<pre>';
		$text = $this->create_complicated_html_text();
		$postdata = $this->telegram_api->prepareMessage($text);
		$postdata['chat_id'] = $this->config['eb_telegram_admin_telegram_id'];
		//$postdata['message_id'] = 1234;
		$result = $this->telegram_api->sendOrEditMessage($postdata);
		print_r($result);
		echo '</pre>';
		die("Done");
	}
	private function create_complicated_html_text() {
			$htmlText = '<b attr="unsupportedAttr" attrWithoutValue>Tags are escaped and bold is ignored</b>';
			$htmlText .= '<br><b attr1="value" attr2 = "value">Bold with unnecessary attributes</b>';
			$htmlText .= '<br><b>And now bold<i> with nested italic <u>and underlined</u></i> tags</b>';
			$htmlText .= PHP_EOL . 'Are quotes \' and double qoutes " also allowed?';
			$htmlText .= '<br>Link without anchor: https://google.com';
			$htmlText .= '<br><a href="https://google.com">Link with anchor</a>';
			$htmlText .= '<br>Illegal code tag (surrounding \'pre\' missing): <code class="language-java">Java-Code</code>';
			$htmlText .= '<br>Selfclosing b-tag: <b/> No bold expected here.';
			$htmlText .= PHP_EOL . 'Unopened tag:</b> illegal tag: <jkkk></jkkk>';
			return $htmlText;
	}

	 /** Allow calls to the test-api only by specified IP-Adresses.
	 */
	private function check_caller($request, $allowed_ip) {
		//The forum usually does not allow to read the super globals. Therefore
		//this access must be temporarily enabled.
		$request->enable_super_globals();
		$remote_addr = $_SERVER['REMOTE_ADDR'];
		$request->disable_super_globals();
		if ($remote_addr != $allowed_ip) {
			//Return the ip adress, so its easier to adapt the
			//code during testing.
			die("Forbidden for $remote_addr");
		}
	}

}

?>