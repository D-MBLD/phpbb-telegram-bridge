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
 *  Then the following test calls are possible (after the extension was configured in the ACP):
 *  - Simulate a text input:
 *    https://<server>/<forum-base>/telegram/test?chat_id=<your telegram id>&text=<some text input>
 *  - Simulate a button press (you need to know the command send by the button, either from the code
 *    or from switching on the admin-echo in ACP):
 *    https://<server>/<forum-base>/telegram/test?chat_id=<your telegram id>&command=<button callback data>
 *  - Test html-formatting and escaping by sending a complicated test to the configured telegram admin id.
 *    https://<server>/<forum-base>/telegram/test?test=html
 */

class test {

	private $telegram_api;
	private $webhook;

	/* @var \phpbb\config\config */
	private $config;

	/* @var \phpbb\request\request   */
	private $request;

	/* @var \phpbb\controller\helper   */
	private $helper;

	/* @var \phpbb\template\template   */
	private $template;

	/**
	* Constructor
	*/
	public function __construct(\phpbb\config\config $config,
								\phpbb\request\request $request,
								\phpbb\controller\helper $helper,
								\phpbb\template\template $template,
								\eb\telegram\core\telegram_api $telegram_api,
								\eb\telegram\core\webhook $webhook
								)
	{
		$this->config = $config;
		$this->request = $request;
		$this->helper = $helper;
		$this->template = $template;

		$this->telegram_api = $telegram_api;
		$this->webhook = $webhook;
	}

	private $text_lines;

	public function handle()
	{
		//Set the header here, such that the forum does not complain.
		$this->text_lines = array();

		$output = $this->dispatch_request();

		foreach ($output as $line)
		{
			$this->template->assign_block_vars ( 'TEXT_LINES', array (
				'LINE' => $line,
				) );
		}
		return $this->helper->render('test.html', 'Telegram Webhook');

	}

	private function dispatch_request()
	{
		//Simulate text-input or the data of an inline button with the url-parameter 'text' or 'command'
		$command = $this->request->variable('command','~~~');
		$text = $this->request->variable('text','~~~');
		$test = $this->request->variable('test','~~~');
		$chat_id = $this->request->variable('chat_id', $this->config['eb_telegram_admin_telegram_id']);

		if ($command != '~~~')
		{
			return $this->simulate_button_callback($command, $chat_id);
		} else if ($text != '~~~')
		{
			return $this->simulate_text_input($text, $chat_id);
		} else if ($test == 'html')
		{
			return $this->test_html_escape();
		} else
		{
			return $this->create_help_text();
		}
	}

	private function simulate_button_callback($command, $chat_id)
	{
		$json = '{"callback_query":' .
					'{"from":{"id":"' . $chat_id . '"},' .
					 '"message":{"chat":{"id":"' . $chat_id . '"}},' .
					 '"data":"' . $command .
				'"}}';
		$payload = json_decode($json);
		$this->webhook->process_input($payload, true);
	}

	private function simulate_text_input($text, $chat_id)
	{
		$json = '{"message":' .
					'{"from":{"id":"' . $chat_id . '"},' .
					 '"chat":{"id":"' . $chat_id . '"},' .
					 '"text":"' . $text . '"}}';
		$payload = json_decode($json);
		$this->webhook->process_input($payload, true);
	}

	private function create_help_text()
	{
		$text_lines = array();
		$path = $this->config['server_protocol'] . $this->config['server_name'] . $this->config['script_path'] . '/telegram/test';
		$text_lines[] = 'You may use one of the following URLs for testing:';
		$text_lines[] = '<ul>';
		$text_lines[] = '<li> send a html formatted test message to the configured admin telegram user:';
		$text_lines[] = "<br>   <a href=\"$path?test=html\">$path?test=html</a>";
		$text_lines[] = '</li>';
		$text_lines[] = '<li> simulate how the bot sends a text called from the given chat_id:';
		$text_lines[] = "<br>   <a href=\"$path?chat_id=&lt;chat_id&gt;&amp;text=&lt;some text&gt;\">$path?chat_id=&lt;chat_id&gt;&amp;text=&lt;some text&gt;</a>";
		$text_lines[] = '</li>';
		$text_lines[] = '<li> simulate how the bot sends a button callback from the given chat_id:';
		$text_lines[] = "<br>   <a href=\"$path?chat_id=&lt;chat_id&gt;&amp;command=&lt;button callback data&gt;\">$path?chat_id=&lt;chat_id&gt;&amp;command=&lt;button callback data&gt;</a>";
		$text_lines[] = '</li>';
		$text_lines[] = '</ul>';
		return $text_lines;
	}

	private function test_html_escape()
	{
		$output = array();
		$text = $this->create_complicated_html_text();
		$postdata = $this->telegram_api->prepareMessage($text);
		$postdata['chat_id'] = $this->config['eb_telegram_admin_telegram_id'];
		$output[] = '<b>Sending following data to telegram:</b>';
		$data = print_r($postdata, true);
		$data = \str_replace(PHP_EOL, '<br>', $data);
		$data = \str_replace(' ', '&nbsp;', $data);
		$output[] = $data;
		//$postdata['message_id'] = 1234;
		$result = $this->telegram_api->sendOrEditMessage($postdata);
		$output[] = '<b>Response from telegram:</b>';
		$result = \json_decode($result);
		$result = \json_encode($result, JSON_PRETTY_PRINT);
		$result = \str_replace(PHP_EOL, "<br>", $result);
		$result = \str_replace(' ', '&nbsp;', $result);
		$output[] = $result;
		return $output;
	}

	private function create_complicated_html_text()
	{
		$htmlText = 'Shouldn\'t be bold:<b attr="unsupportedAttr" attrWithoutValue>Tags are escaped and bold</b> is ignored';
		$htmlText .= '<br><b attr1="value" attr2 = "value">Bold with unnecessary attributes</b>';
		$htmlText .= '<br><b>And now bold<i> with nested italic <u>and underlined</u></i> tags</b>';
		$htmlText .= "<br><b>What happens if a bold text\nspans multiple lines</b>";
		$htmlText .= PHP_EOL . 'Are quotes \' and double qoutes " also allowed?';
		$htmlText .= '<br>Link without anchor: https://google.com';
		$htmlText .= '<br><a href="https://google.com">Link with anchor</a>';
		$htmlText .= '<br>Illegal code tag (surrounding \'pre\' missing): <code class="language-java">Java-Code</code>';
		$htmlText .= '<br>Selfclosing b-tag: <b/> No bold expected here.';
		$htmlText .= PHP_EOL . 'Unopened tag:</b> illegal tag: <jkkk></jkkk>';
		return $htmlText;
	}

}
