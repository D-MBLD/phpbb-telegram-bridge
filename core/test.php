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
		// Create a form key for preventing CSRF attacks
		add_form_key('eb_telegram_test');
		$textlines = array();
		$html_text = $this->create_complicated_html_text();
		$chat_id = $this->config['eb_telegram_admin_telegram_id'];

		if ($this->request->is_set_post('submit'))
		{
			// Test if the submitted form is valid
			if (!check_form_key('eb_telegram_test'))
			{
				$textlines[] = 'Invalid form id (outdated?). Try again.';
			} else
			{
				// If no errors, process the form data
				// Set the options the user configured
				$chat_id = $this->request->variable('test_chat_id', '');
				$text = $this->request->variable('test_text', '', true); //Multibyte = true (Umlaute, etc.)
				$command = $this->request->variable('test_command', '');
				$html_text = $this->request->raw_variable('test_send', ''); //Raw without escaping

				if (!$chat_id)
				{
					$textlines[] = "Error: Chat-ID must be supplied";
				} else if ($command)
				{
					$textlines = $this->simulate_button_callback($command, $chat_id);
				} else if ($text)
				{
					$textlines = $this->simulate_text_input($text, $chat_id);
				} else if ($html_text)
				{
					$textlines = $this->test_html_escape($html_text, $chat_id);
				} else
				{
					$textlines[] = "Error: Either text or command must be supplied";
				}
			}
		}

		$this->template->assign_vars([
			'TEST_CHAT_ID'	=> $chat_id ?? '',
			'TEST_TEXT'		=> $text ?? '',
			'TEST_COMMAND'	=> $command ?? '',
			'TEST_SEND'		=> $html_text ?? ''
		]);
		foreach ($textlines as $line)
		{
			$this->template->assign_block_vars ( 'TEXT_LINES', array (
				'LINE' => $line,
				) );
		}
		return $this->helper->render('test.html', 'Telegram Webhook');

	}

	private function simulate_button_callback($command, $chat_id)
	{
		$json = '{"callback_query":' .
					'{"from": {"id":"' . $chat_id . '"},' .
					' "message":{"chat":{"id":"' . $chat_id . '"},' .
					 '           "message_id":"0"},' .
					 '"data":"' . $command .
				'"}}';
		$payload = json_decode($json);
		$this->webhook->process_input($payload, true);
		return $this->webhook->debug_output;
	}

	private function simulate_text_input($text, $chat_id)
	{
		$json = '{"message":' .
					'{"from":{"id":"' . $chat_id . '"},' .
					 '"chat":{"id":"' . $chat_id . '"},' .
					 '"text":"' . $text . '"}}';
		$payload = json_decode($json);
		$this->webhook->process_input($payload, true);
		return $this->webhook->debug_output;
	}

	private function test_html_escape($text, $chat_id)
	{
		$output = array();
		$output[] = '<b>Original text:</b>';
		$output = array_merge($output, explode(PHP_EOL, $text));
		$postdata = $this->telegram_api->prepareMessage($text);
		$postdata['chat_id'] = $chat_id;
		$output[] = '<b>Sending following data to telegram:</b>';
		$data = print_r($postdata, true);
		$data = \str_replace(PHP_EOL, '<br>', $data);
		$data = \str_replace(' ', '&nbsp;', $data);
		$output = array_merge($output, explode(PHP_EOL, $data));
		//$postdata['message_id'] = 1234;
		$result = $this->telegram_api->sendOrEditMessage($postdata);
		$output[] = '<b>Response from telegram:</b>';
		$result = \json_decode($result);
		$result = \json_encode($result, JSON_PRETTY_PRINT);
		$result = \str_replace(' ', '&nbsp;', $result);
		$output = array_merge($output, explode(PHP_EOL, $result));
		return $output;
	}

	private function create_complicated_html_text()
	{
		$htmlText = array();
		$htmlText[] = 'Various tags which should be escaped:';
		$htmlText[] = '<b attrWithoutValue>no bold expected here</b>';
		$htmlText[] = 'Unopened bold tag: </b>';
		$htmlText[] = 'Unknown tag <abc>(abc)</abc>';
		$htmlText[] = 'Self closing bold tag: <b/> No bold expected here';
		$htmlText[] = 'Tags which should work:';
		$htmlText[] = 'Bold <b attr1 = "value">tag contains unnecessary</b> attributes.';
		$htmlText[] = 'Nested <b>bold<i> italic <u>underlined</u></i> tags.</b> End nesting.';
		$htmlText[] = "Bold <b>tag spanning";
		$htmlText[] = "multiple</b> lines";
		$htmlText[] = 'Special characters:';
		$htmlText[] = 'quotes: \' ", Umlauts: äöüÄÖÜ?';
		$htmlText[] = 'Link without anchor: https://google.com';
		$htmlText[] = '<a href="https://google.com">Link with anchor</a>';
		return implode(PHP_EOL, $htmlText);
	}

}
