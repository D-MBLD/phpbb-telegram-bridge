<?php
/**
 *
 * Telegram Bridge. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Edgar Bolender
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine
//
// Some characters you may want to copy&paste:
// ’ » “ ” …
//

$lang = array_merge($lang, [
	'UCP_TELEGRAM_COLUMN'		=> 'Telegram',

	//Used in the html events (Prefixed with L_)
	'TELEGRAM_ID'			=> 'Your Telegram ID',
	'TELEGRAM_ID_DESCR'		=> 'Add the bot (ask your admin for the name) to your Telegram contacts and enter your Telegram ID here.',
	'TELEGRAM_ID_NOT_NUMERIC'	=> '<b><font color="red">The telegram id must be numeric.</font></b>',
	'TELEGRAM_ID_ALREADY_USED'	=> '<b><font color="red">Same telegram id is already used by another user!</font></b>',

]);
