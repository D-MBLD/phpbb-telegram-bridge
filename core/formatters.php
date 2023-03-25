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

	/** Format a phpbb-post, such that the formatting information
	 * is transformed into valid telegram formatting.
	 */
	public function format_post_for_telegram($text) {
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

		//Add server address to relative links (no http-protokoll) starting with a slash (/)
		$rel_url_pattern = '~<a href="((?!https?://)/[^"]*)">(.*?)</a>~is';
		$replace = '<a href="' . generate_board_url(true) . '$1">$2</a>';
		$text = preg_replace($rel_url_pattern, $replace, $text);

		//Add server address to relative links (no http-protokoll) NOT starting with a slash (/)
		$rel_url_pattern = '~<a href="((?!https?://)[^"]*)">(.*?)</a>~is';
		$replace = '<a href="' . generate_board_url(false) . '/$1">$2</a>';
		$text = preg_replace($rel_url_pattern, $replace, $text);

		//Add a non printable space (ZWSP) to all forward slashes.
		//By that, telegram does not treat the forward slash as the beginning of a command.
		//Exclude double // and slashes belonging to html-tags
		$text = preg_replace('~([^<]/)([^/])~', "$1\u{200B}$2", $text);

		//Revert this, for all hrefs in anchors.
		//By that, telegram does not treat the forward slash as the beginning of a command.
		do
		{
			$text = preg_replace("~(<a href=\"[^\"]*/)\u{200B}([^\"]*\">)~", '$1$2', $text, 1, $count);
		} while ($count > 0);
		return $text;
	}

	/** Implements the substring function (without length-param) 
	 * such that html-tags are still correctly opened and closed.
	 * Cut the beginning of a text at the given offset.
	 * If the offset happens to lay inside a tagged area,
	 * the possibly cut off start tags are added again to the beginning of the
	 * text, such that the tags are still opened and closed correctly.
	 * If the offset would cut a start tag (before the ending >) or
	 * an end tag into pieces, the $offset is moved behind the closing >.
	 * By adding the start-tags before the offset, the text-length is increased.
	 * To ensure, the text is not longer, than it would be expected by the offset,
	 * the offset is increased, until the total lenght is less or equal
	 * than mb_strlen($text) - $offset. 
	 */
	public function tag_aware_substr($tagged_text, $offset) {
		$tags = $this->parse_tags($tagged_text);
		$prefix = '';
		$cut_point = $offset - 1;
		do
		{
			$cut_point++;
			$prefix = $this->adapt_cut_point($tags, $cut_point);
			
		} while ($cut_point - mb_strlen($prefix) < $offset);
		return $prefix . mb_substr($tagged_text, $cut_point);
	}

	/** For a text, which is to be cut, adapt the cut-point, such
	 * that it does not cut a start or end tag of the text, and
	 * return the start-tags, which must be added as prefix, because the
	 * cut text still contains the corresponding end tags.
	 */
	public function adapt_cut_point($tags, &$offset) {
		$print = array();
		//Move the offset, such that no tag is split
		foreach ($tags as $tag) {
			if ($offset <= $tag['full_s'] || $offset >= $tag['full_e'])
			{
				continue; //We are outside the enclosing tags.
			}
			if ($offset < $tag['full_s'] + mb_strlen($tag['s_tag']))
			{
				//Start tag would be cut. Move offset behind start-tag
				$start_tag = $tag['s_tag'];
				$offset = $tag['full_s'] + mb_strlen($start_tag);
				break; //Tags are sequential. Cannot happen again.
			}
			if ($offset >= $tag['full_e'] - mb_strlen($tag['e_tag']))
			{
				//End tag would be cut, move $offset behind the end-tag.
				//A pure endtag without previous content also does not make sense,
				//therefore, this is also skipped. (>= in the condition above)
				$offset = $tag['full_e'];
				break; //Tags are sequential. Cannot happen again.
			}
		}
		//Collect the start tags, which must be added to the beginning, such that
		//their is no end-tag with missing start tag	
		$start_tags = array();
		foreach ($tags as $tag) {
			if ($offset > $tag['full_s'] && $offset < $tag['full_e'])
			{
				$start_tag = $tag['s_tag'];
				$pos = $tag['full_s']; //keep start order
				$start_tags[$pos] = $start_tag;
			}
		}
		ksort($start_tags);
		return implode('', $start_tags);
	}

	/** Find the start and end of text enclosed in html tags.
	 * The result is an array containing all tagged texts (alos if nested)
	 * in the following form:
	 * array( 
	 *    array('full' => full tag enclosed text,
	 *          'full_s' => offset, where full text starts
	 *          'full_e' => offset, where full text ends
	 *          's_tag' => complete start tag (including attributes)
	 *          'e_tag' => complete end tag
	 *  ))
	 * In case of self closing tags, e_tag is empty.
	 */
	public function parse_tags($tagged_text) {
		$tag_pattern = "/<([\w]+)([^>]*?)(?:([\s]*\/>)|(?:(>)(?:(?:(?:[^<]*?|<\!\-\-.*?\-\->)|(?R))*)(<\/\\1[\s]*>)))/xsmu";
		$result[] = array('full' => ' ' . $tagged_text, 'full_s' => -1);
		for($i = 0; $i < count($result); $i++) {
			$full = $result[$i]['full'];
			$offset = $result[$i]['full_s'];
			$t_count = preg_match_all($tag_pattern, mb_substr($full,1), $matches, PREG_OFFSET_CAPTURE);
			if ($t_count) {
				$j = 0;
				foreach($matches[0] as $match) {
					$mb_offset = mb_strlen(substr(mb_substr($full,1), 0, $match[1]));
					$result[] = array(
						'full' => $match[0],
						'full_s' => $mb_offset + 1 + $offset,
						'full_e' => $mb_offset + 1 + mb_strlen($match[0]) + $offset,
						's_tag' => '<' . $matches[1][$j][0] . $matches[2][$j][0] . $matches[3][$j][0] . $matches[4][$j][0],
						'e_tag' => $matches[5][$j][0],
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
