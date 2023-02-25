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

class telegram_api
{

	/* @var \phpbb\config\config */
	protected $config;
	/* @var \phpbb\language\language */
	protected $language;

	public $debug_output = array();

	/**
	* Constructor
	*/

	public function __construct(\phpbb\config\config $config,
								\phpbb\language\language $language
								)
	{
		$this->config = $config;
		$this->language = $language;
	}

	/** Get the name of the bot */
	public function get_bot_name()
	{
		$token = $this->config['eb_telegram_bot_token'];

		$opts = array('http' =>
		array(
			'method'  => 'GET',
			'ignore_errors' => true, //Return content, even in case of "Bad request"
			)
		);
		$context  = stream_context_create($opts);
		$result = file_get_contents("https://api.telegram.org/bot$token/getMe", false, $context);
		$result_obj = json_decode($result);
		if ($result_obj->ok)
		{
			return $result_obj->result->username;
		} else
		{
			return false;
		}
	}

	public function sendOrEditMessage($postdata)
	{
		$token = $this->config['eb_telegram_bot_token'];
		if (empty($token))
		{
			$this->debug_output[] = "Error, Telegram Bot ID is needed.";
			return false;
		}

		$opts = array('http' =>
			array(
				'method'  => 'POST',
				'header'  => 'Content-Type: application/json',
				'ignore_errors' => true, //Return content, even in case of "Bad request"
				'content' => json_encode($postdata)
			)
		);

		$context  = stream_context_create($opts);
		/* If a message-id is set, the last message is updated instead of
		sending a new one. */
		$update = isset($postdata['message_id']);

		if ($update)
		{
			$result = file_get_contents("https://api.telegram.org/bot$token/editMessageText", false, $context);
			//else try sendMessage
			if ($result === false || !strpos($http_response_header[0], '200'))
			{
				$this->log_result($result, $http_response_header[0], true);
			} else
			{
				$this->log_result($result, $http_response_header[0], false);
				return $result;
			}
		}
		$result = file_get_contents("https://api.telegram.org/bot$token/sendMessage", false, $context);
		$this->log_result($result, $http_response_header[0], false);
		return $result;
	}

	private function log_result($result, $http_status, $retry)
	{
		$json = \json_decode($result);
		$json_lines = explode(PHP_EOL, json_encode($json, JSON_PRETTY_PRINT));
		$json_lines = str_replace(' ', '&nbsp;', $json_lines);
		if ($result === false || !strpos($http_status, '200'))
		{
			$this->debug_output[] = "Request failed ($http_status) with content:";
			$this->debug_output = array_merge($this->debug_output, $json_lines);
			if ($retry)
			{
				$this->debug_output[] = "Retrying with sendMessage.";
			}
		} else
		{
			$this->debug_output[] = "Telegram response:";
			$this->debug_output = array_merge($this->debug_output, $json_lines);
		}
	}

	/** Prepare a text message with optional buttons.
	 * Buttons are passed as an array with button text as key and command as value.
	 */
	public function prepareMessage($text, $buttons = false)
	{
		$text = $this->prepareText($text);
		if ($buttons)
		{
			$buttonStack = array();
			$buttonRow = array();
			$new_line = true;
			foreach ($buttons as $b_text => $command)
			{
				$new_line = !$new_line; //New line after every second button
				if ($command != 'NEXT_LINE')
				{
					$b_text  = $this->prepare_button_text($b_text);
					$button = array('text' => $b_text, 'callback_data' => $command);
					$buttonRow[] = $button;
				} else
				{
					$new_line = true;
				}
				if ($new_line)
				{
					//At most 2 buttons in one row
					$buttonStack[] = $buttonRow;
					$buttonRow = array();
				}
			}
			if (count($buttonRow) > 0)
			{
				$buttonStack[] = $buttonRow;
			}
			$reply_markup_ik = array( 'inline_keyboard' => $buttonStack );
		}

		$message =
			array (
				'disable_web_page_preview' => 'true',
				'parse_mode' => 'HTML',
				'text' => $text,
			);
		if ($buttons)
		{
			$message['reply_markup'] = $reply_markup_ik;
		}
		//chat_id (message receiver) and optionally message_id will be set later.
		return $message;
	}

	private function prepareText($org_text)
	{
		$text = $this->htmlentitiesForTelegram($org_text);
		// Return the text from its XML form to its original plain text form
		if (strlen($text) >= 4096)
		{
			// <b>Warning: Topic is too long and was cut. Telegram doesn \'t allow more than 4096 characters !</b>',
			$pretext = $this->language->lang('EBT_TOPIC_SHORTENED') . PHP_EOL . '...' . PHP_EOL;
			$len = 4095 - strlen($pretext);
			while (strlen($text) >= 4069)
			{
				$len--;
				$text = mb_substr($org_text, -$len);
				//To avoid open tags, we need to encode html-chars again, after the text was shortend
				$text = $this->htmlentitiesForTelegram($text);
				$text = $pretext . $text;
			}
		}
		return $text;
	}

	/** Prepare text for inline buttons.
	 * All tags are removed.
	 * Text is shortened to at most 20 characters. If so, ... is appended.
	 */
	private function prepare_button_text($text)
	{
		$text = strip_tags($text);
		if (strlen($text) > 24)
		{
			$text = mb_substr($text, 0, 20) . ' ...'; //Multibyte-safe cut
		}
		//Button-texts do not need html-encoding
		$text = html_entity_decode($text);
		return $text;

	}
	/** Encode all html-formatting, which is not allowed by telegram.
	 *
	 */
	private function htmlentitiesForTelegram ($text)
	{
		$ent = ENT_SUBSTITUTE | ENT_HTML401; //Don't substitute quotes
		//The telegram bot only allows a predefined set of HTLM-Tags.
		//The forum posts however surround each opening bb-code with an <s>-Tag (and each closing with an <e>)
		//Therefore we do not include the <s> tag in the following list.
		$allowed_tags = ['b', 'strong', 'i', 'em', 'u', 'ins', 'strike', 'del', 'a', 'code', 'pre'];
		$allowed_tags_bar_separated = implode('|', $allowed_tags);
		//Match for opening tags with optional attributes, followed by any text, followed by the same closing tag.
		//Use https://regexper.com/ to visualize the pattern
		//("\\" must be replaced by "\" and "/" by "\/" for this tool )
		//https://regexper.com/#%26lt%3B%28%28list%7Cof%7Callowed%7Ctags%29%28%3F%3A%28%3F%3A%5Cs%2B%5Cw%2B%3F%28%3F%3A%5Cs*%3D%5Cs*%28%3F%3A%5C%22%5B%5E%5C%22%5D*%5C%22%7C'%5B%5E'%5D*'%29%29%29%2B%5Cs*%7C%5Cs*%29%29%26gt%3B%28.*%3F%29%26lt%3B%5C%2F%5C2%5Cs*%26gt%3B
		$pattern = "~&lt;(($allowed_tags_bar_separated)(?:(?:\s+\w+?(?:\s*=\s*(?:\"[^\"]*\"|'[^']*')))+\s*|\s*))&gt;(.*?)&lt;/\\2\s*&gt;~is";
		//Groups: 1: Full tag-content including attributs, 2: tag-name, 3: content between tags.
		//Modifier s: DOTALL, i.e. a dot matches also newline
		$replacement = '<$1>$3</$2>';

		//If a HTML-Break is already followed by an EOL just remove the HTML-Break
		//otherwise replace it by EOL.
		$text = preg_replace('~<br/>\r?\n?~', PHP_EOL, $text);
		$text = preg_replace('~<br>\r?\n?~', PHP_EOL, $text);
		$text = str_replace('<br/>', PHP_EOL, $text);
		$text = str_replace('<br>', PHP_EOL, $text);
		//Now remove all tags, which we do not allow. (Exception list contains all allowed tags.)
		//This would not be necessary for "normal" text. But when the forum sends notifications,
		//a lot of additional tags are surrounding the text, BB-Codes, links etc.
		$text = strip_tags($text, $allowed_tags);

		//Escape all html entities (including the allowed tags, which are still contained)
		$text = \htmlspecialchars($text, $ent, null, false);

		//Now look for correctly opened and closed tags, using the escaped entities for < (&lt;) and > (&gt) and
		//replace them again by < and >
		do
		{
			$text = preg_replace($pattern, $replacement, $text, 1, $count);
		} while ($count > 0);
		return $text;
	}

}
