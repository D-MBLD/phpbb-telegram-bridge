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

/** Create the text and buttons response depending on the state and the users
 * input.
 * If an action like saving a post or saving the state is involved, this is
 * also done here.*/
class formatters
{

	/*
	public function __construct(\phpbb\config\config $config,
								\phpbb\language\language $language,
								\eb\telegram\core\forum_api $forum_api
								)
	{
		$this->config = $config;
		$this->language = $language;
	}
	*/

	/** Returns the text length, when only pure text included in HTML tags is
	 * counted. The tags themselves are not included into the length.
	 * Example: Length of "A <b><i><u>formatted</u></i></b> Text" would be
	 * the same as the length of "A formatted Text".
	 */
	public function pure_text_len($tagged_text)
	{
		$text_parts = $this->split_at_tags($tagged_text);
		return($text_parts[0]['len'] + mb_strlen($text_parts[0]['text']));
	}

	/** Returns the text cut at the beginning to the given length, where
	 * only the pure text counts for the length. HTML-Tags are kept intact,
	 * but are not counted for length calculation.
	 * This is how telegram limits the length of its text messages.
	 * (There is one exception: HTML-Entities like &amp; are counted by
	 * telegram as 1 character, whereas here it is treated as 5 chars.)
	  */
	public function pure_text_substr($tagged_text, $length)
	{
		$text_parts = $this->split_at_tags($tagged_text);
		$first = true;
		foreach ($text_parts as $part)
		{
			if ($part['len'] >= $length)
			{
				continue;
			}
			if ($first)
			{
				$first = false;
				$text_part = mb_substr($part['text'], $part['len'] - $length);
				$text_part = htmlspecialchars($text_part, ENT_NOQUOTES);
				$text = $part['O'] . $text_part . $part['E'];
			} else
			{
				$text .= $part['S'] . htmlspecialchars($part['text'], ENT_NOQUOTES) . $part['E'];
			}
		}
		return $text;
	}

	/** Split a text into the parts enclosed or limited by html-tags.
	 * The resulting array contains for each text part, the surrounding tags
	 * (Starting and/or Ending) and a list of the open tags, at this text position.
	 * Examples: "This <i>is an <b>important<a href="..."> and </a><u>underlined</u> </b>text</i>."
	 * would be split into

	 * [0] =>  [S => '', E => '', O => '' text => 'This ', len => 42]
	 * [5] =>  [S => '<i>, E => '', O => '<i>' text => 'is an ', len => 37]
	 * [14] => [S => '<b>', E => '', O => '<i><b>' text => 'important', len => 31]
	 * [26] => [S => '<a href..>', E => '</a>', O => '<i><b><a...>' text => ' and ', len => 22]
	 * [49] => [S => '<u>', E => '</u>', O => '<i><b><u>' text => 'underlined', len => 17]
	 * [66] => [S => '', E => '</b>', O => '<i><b>' text => ' ', len => 7]
	 * [71] => [S => '', E => '</i>', O => '<i>' text => ' text', len => 6]
	 * [79] => [S => '', E => '', O => '' text => '.', len => 1]
	 * )
	 * The index represents the split points of the original text.
	 * (i.e the start position of opening or end position of closing tags.)
	 * 'len' describes the length of the pure text withput tags.
	 * 'O' contains the open tags, at the corresponding text position.
	 */
	public function split_at_tags($tagged_text)
	{
		$tag_info = $this->parse_tags($tagged_text);
		foreach ($tag_info as $tag)
		{
			$result[$tag['full_s']]['S'] = $tag['s_tag'];
			$result[$tag['full_s']]['O'] = $tag['open_tags'];
			$result[$tag['full_s']]['E'] = '';
		}
		foreach ($tag_info as $tag)
		{
			if (!isset($result[$tag['full_e']]))
			{
				$result[$tag['full_e']]['S'] = '';
				$result[$tag['full_e']]['O'] = $tag['open_tags_e'];
			}
			$result[$tag['full_e']]['E'] = $tag['e_tag'];
		}
		if (!isset($result[0]))
		{
			//Text does not start with a tag
			$result[0]['S'] = '';
			$result[0]['E'] = '';
			$result[0]['O'] = '';
		}
		ksort($result);
		//The End-tag is now at the index which indicates the start of the next text.
		//It's easier to understand, if its moved to the previous text part (where it belongs to).
		$offsets = array_keys($result);
		for ($i = 0; $i < count($offsets) - 1; $i++)
		{
			$result[$offsets[$i]]['E'] = $result[$offsets[$i+1]]['E'];
		}
		$result[$offsets[$i]]['E'] = ''; //Clear the last end-tag

		for ($i = 0; $i < count($offsets) - 1; $i++)
		{
			$current_offset = $offsets[$i];
			$next_offset = $offsets[$i+1];
			$stag_len = mb_strlen($result[$offsets[$i]]['S']);
			$etag_len = mb_strlen($result[$offsets[$i]]['E']);
			$start = $current_offset + $stag_len;
			$end = $next_offset - $etag_len;
			$pure_text = mb_substr($tagged_text, $start, $end - $start);
			$pure_text = html_entity_decode($pure_text);
			$result[$offsets[$i]]['text'] = $pure_text;
		}
		//Last entry has no tags
		$result[$offsets[$i]]['text'] = mb_substr($tagged_text, $offsets[$i]);

		krsort($result); //Fill length from end to beginning
		$pure_len = 0;
		foreach ($result as &$entry)
		{
			$entry['len'] = $pure_len;
			$pure_len += mb_strlen($entry['text']);
		}
		ksort($result);
		return $result;
	}



	/** Format a phpbb-post, such that the formatting information
	 * is transformed into valid telegram formatting.
	 */
	public function format_post_for_telegram($text)
	{
		$ent = ENT_SUBSTITUTE | ENT_HTML401; //Don't substitute quotes
		//The telegram bot only allows a predefined set of HTLM-Tags.
		//The forum posts however surround each opening BBCode with an <s>-Tag (and each closing with an <e>)

		//At first we remove all these tags and their BBCodes, we do not want to show
		//up in telegram.
		$bbcode_pattern = '~<s>\[((i|b|u|url|email)(?:(?:=|\s).*)?)\]</s>(.*?)<e>\[/\\2]</e>~';
		do
		{
			$text = preg_replace($bbcode_pattern, '$3', $text, 1, $count);
		} while ($count > 0);

		$allowed_tags = ['url', 'img', 'b', 'strong', 'i', 'em', 'u', 'ins', 'strike', 'del', 'a', 'code', 'pre'];

		//Now remove all tags, which we do not allow. (Exception list contains all allowed tags.)
		//This would not be necessary for "normal" text. But when the forum sends notifications,
		//a lot of additional tags are surrounding the text, BB-Codes, links etc.
		//$text = strip_tags($text, $allowed_tags); //Needs php version >= 7.4
		$text = strip_tags($text, '<' . implode('><',$allowed_tags) . '>');

		//Special handling of the url tag: Replace <URL url=...> by <a href=...>
		$url_pattern = '~<URL url="([^"]*)">(.*?)</URL>~is';
		$text = preg_replace($url_pattern, '<a href="$1">$2</a>', $text);

		//Special handling of the img tag:
		//Replace the content of the anchor tag with a <IMAGE>-Placeholder
		$img_pattern = '~<IMG src="([^"]*)">\[img](.*?)\[/img]</IMG>~is';
		$text = preg_replace($img_pattern, '<a href="$1">&lt;&lt;IMAGE&gt;&gt;</a>', $text);

		//Replace all BBCodes which are just enclosing an URL by "<<BBcode-name>>":
		//e.g.: [media=30,20]<a href="https://youtube.com/">https://youtube.com/</a>[/media]
		// --> <a href="https://youtube.com/"><<media>></a>
		$bburl_pattern = '~\[([^\]\s]+)(?:\s*=[^\]]*)?\s*]\s*<a href="([^"]+)">(.*)</a>\s*\[/\1]~is';
		$text = preg_replace($bburl_pattern, '<a href="$2">&lt;&lt;$1&gt;&gt;</a>', $text);

		//Add server address to relative links (no http-protokoll) starting with a slash (/)
		$rel_url_pattern = '~<a href="((?!https?://)/[^"]*)">(.*?)</a>~is';
		$replace = '<a href="' . generate_board_url(true) . '$1">$2</a>';
		$text = preg_replace($rel_url_pattern, $replace, $text);

		//Add server address to relative links (no http-protokoll) NOT starting with a slash (/)
		$rel_url_pattern = '~<a href="((?!https?://)[^"]*)">(.*?)</a>~is';
		$replace = '<a href="' . generate_board_url(false) . '/$1">$2</a>';
		$text = preg_replace($rel_url_pattern, $replace, $text);

		return $text;
	}

	/** Find the start and end of text enclosed in html tags.
	 * The result is an array containing all tagged texts (also if nested)
	 * in the following form:
	 * array(
	 *    array('full' => full tag enclosed text,
	 *          'full_s' => offset, where full text starts
	 *          'full_e' => offset, where full text ends
	 *          's_tag' => complete start tag (including attributes)
	 *          'e_tag' => complete end tag
	 *          'open_tags' => list of open tags at this position, including the s_tag,
	 *          'open_tags_e' => list of open tags after this tag is closed (without s_tag),
	 *  ))
	 * In case of self closing tags, e_tag is empty.
	 */
	public function parse_tags($tagged_text)
	{
		$tag_pattern = "/<([\w]+)([^>]*?)(?:([\s]*\/>)|(?:(>)((?:(?:[^<]*?|<\!\-\-.*?\-\->)|(?R))*)(<\/\\1[\s]*>)))/smu";
		$result[] = array('full' => ' ' . $tagged_text, 'full_s' => -1, 'open_tags' => '');
		for ($i = 0; $i < count($result); $i++)
		{
			$full = $result[$i]['full'];
			$offset = $result[$i]['full_s'];
			$open_tags = $result[$i]['open_tags'];
			$t_count = preg_match_all($tag_pattern, mb_substr($full,1), $matches, PREG_OFFSET_CAPTURE);
			if ($t_count)
			{
				$j = 0;
				foreach ($matches[0] as $match)
				{
					$mb_offset = mb_strlen(substr(mb_substr($full,1), 0, $match[1]));
					$start_tag = '<' . $matches[1][$j][0] . $matches[2][$j][0] . $matches[3][$j][0] . $matches[4][$j][0];
					$result[] = array(
						'full' => $match[0],
						'full_s' => $mb_offset + 1 + $offset,
						'full_e' => $mb_offset + 1 + mb_strlen($match[0]) + $offset,
						's_tag' => $start_tag,
						'e_tag' => $matches[6][$j][0],
						//'text' => $matches[5][$j][0],
						'open_tags' => $open_tags . $start_tag,
						'open_tags_e' => $open_tags,
					);
					$j++;
				}
			}
		}
		//Remove the first full text entry
		return array_slice($result,1);
	}

	/** Format the telegram input by adding bbCodes according to the formatting information,
	 * which telegram sends as so called entities.
	 */
	public function format_input($text, $entities)
	{
		/* Split the text, at every point where a formatting starts or ends into an array.
		 * Therefore we collect at first all splitpoints, and remove duplicates.
		 */
		$split_points[] = 0;
		foreach ($entities as $entity)
		{
			$split_points[] = $entity->offset;
			$split_points[] = $entity->offset + $entity->length;
		}
		$split_points = array_unique($split_points);
		rsort($split_points);
		$chunks = array();
		foreach ($split_points as $point)
		{
			$chunks[$point] = mb_substr($text, $point);
			$text = mb_substr($text, 0, $point);
		}
		ksort($chunks);
		//Sort by end of formatting, such that in case of overlapping formats, the opening tag
		//for the format, that gets closed last is placed at first.
		usort($entities, function($a, $b)
						{
							return (($a->offset + $a->length) < ($b->offset + $b->length)) ? -1 : 1;
						});
		foreach ($entities as $entity)
		{
			$bbcode = $this->get_bbcode($entity->type);
			if (!$bbcode)
			{
				continue;
			}
			$chunks[$entity->offset] = $bbcode . $chunks[$entity->offset];
		}
		for ($i = count($entities) - 1; $i >= 0; $i--)
		{
			$entity = $entities[$i];
			$bbcode = $this->get_bbcode($entity->type, false);
			if (!$bbcode)
			{
				continue;
			}
			$bbcode_start = $this->get_bbcode($entity->type);
			$end = $entity->offset + $entity->length;
			if (strpos($chunks[$end], $bbcode_start) === 0)
			{
				//Remove ending tag immediatly followed by starting tag
				$chunks[$end] = substr($chunks[$end], strlen($bbcode_start));
			} else
			{
				$chunks[$end] = $bbcode . $chunks[$end];
			}
		}
		//Remove non printable whitespace, which may have been included, when user copies
		//a part of a post, where the whitespace was added. (See telegrami_api->htmlentitiesForTelegram)
		$text = implode('', $chunks);
		$text = str_replace("/\u{200B}", "/", $text);
		return $text;
	}

	private function get_bbcode($format_type, $start = true)
	{
		switch ($format_type)
		{
			case 'bold': return $start ? '[b]' : '[/b]';
			case 'italic': return $start ? '[i]' : '[/i]';
			case 'underline': return $start ? '[u]' : '[/u]';
			case 'code': return $start ? '[code]' : '[/code]';
			case 'pre': return $start ? '[code]' : '[/code]';
			case 'strikethrough': return $start ? '<del>' : '</del>';
			case 'url': return ''; //No need for BBCode
			default: return false;
		}
	}

}
