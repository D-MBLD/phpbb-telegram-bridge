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

	'EB_TELEGRAM_NOTIFICATION'	=> 'Telegram Bridge notification',
	//Automatically compiled as column title in notification method selection
	'EB_TELEGRAM_NOTIFICATION_METHOD_TELEGRAM'	=> 'Telegram',
	'EB_TELEGRAM_NOTIFICATION_METHOD_TELEGRAM_NP'	=> 'Telegram<br>(No Permission)',

	//Notification
	'EBT_FULL_TOPIC'	=> 'Show complete topic',
	'EBT_SHOW_FORUM'	=> 'Show forum',

	//Forum list
	'EBT_FORUM_LIST_TITLE'	=> '<u>%1$s has %2$s forums:</u>',
	'EBT_SELECT_A_FORUM'	=> 'Use one of the buttons to select a forum.',
	'EBT_LAST_POST'	=> 'Last post at %1$s by <b>%2$s</b>:',
	'EBT_READ_ONLY' => ' (readonly)',
	'EBT_BACK'	=> 'Back',
	'EBT_ILLEGAL_INPUT' => " \xE2\x9A\xA0 Input <b>%s</b> is invalid.",  //"\xE2\x9A\xA0" = ⚠

	//Topic list
	'EBT_TOPIC_LIST_TITLE' => 'The forum <a href="%3$s"><b>%1$s</b></a> has %2$s topics:',
	'EBT_TOPIC_LIST_TITLE_EMPTY' => 'Currently there are no topics in the forum <a href="%3$s"><b>%s</b></a>',
	'EBT_SELECT_TOPIC' => 'Use one of the buttons to select a topic.',
	'EBT_SELECT_NEXT_PAGE' => 'Send a \'+\'- or \'-\'-sign, to show next/previous page.',
	'EBT_FORUM_NOT_FOUND' => 'Could not read the forum (!?). Please try again',
	'EBT_SHOW_FORUMS' => 'Show other forums',
	'EBT_ADD_TOPIC' => 'Add topic',

	//Post list
	'EBT_TOPIC_AT_BY' => '<b>%1$s:</b> Topic created by <b>%2$s</b>', //time, user
	'EBT_TOPIC_TITLE' => 'Title: <a href="%2$s"><b>%1$s</b></a>',
	'EBT_REPLY_AT_BY' => '<b>%1$s:</b> Reply from <b>%2$s</b>', //time, user
	'EBT_TOPIC_SHORTENED' => '<b>Warning: Topic is too long and was cut. Telegram doesn \'t allow more than 4096 characters !</b>',
	'EBT_NEW_REPLY' => 'Send a reply',
	'EBT_ILLEGAL_TOPIC_ID' => 'Illegal attempt to read topic with ID %s',

	//Helpscreen unregistered user
	//Placeholders: Sitename, Site-URL, telegram-id
	'EBT_HELP_SCREEN_NON_MEMBER' => 'This service can be used by registered members of ' .
	'<a href="%2$s">%1$s</a> only.' .
	'<br>If you are a member, you must enter your telegram id (<code>%3$s</code>) into your forums profile ' .
	'in order to use the service.',

	//Registration screens
	'EBT_HELP_SCREEN_REGISTERED' => '<b>Your telegram id has been sucessfully verified.</b>' .
								'<br>Don\'t forget to select the events for which you want to receive notifications.' .
								'Therfore select and edit your profile in the forum, go to ' .
								'"Settings->Edit notification options" and select the events in the telegram column.',
	'EBT_ILLEGAL_CODE' => " \xE2\x9A\xA0 The entered Code was wrong. Please request a new one.", //"\xE2\x9A\xA0" = ⚠
	'EBT_HELP_SCREEN_REGISTRATION_FAILED' => '<b>Your telegram id is not yet verified.</b>' .
								'<br>Use the button to request an email to the address stored with your forums profile. ' .
								'The email will contain a code, which you need to send once via telegram to the forum ' .
								'in order to verify your telegram id.',

	'EBT_HELP_SCREEN_EMAILED' => 	'<b>Email was sent.</b>' .
								'<br>Check your inbox and if necessary also your spam folders. ' .
								'<br>Then send the code as message.' .
								'<br>Don\'t send other messages in between. Otherwise you have to request a new code.',

	'EBT_REQUEST_EMAIL'	=> 'Send email',

	//Permissions
	'EBT_PERMISSION_TITEL' => '<b>You have the following permissions:</b>',
	'EBT_PERMISSION_NOTIFY_YES' => 'You can receive notifications via telegram.',
	'EBT_PERMISSION_NOTIFY_NO' => 'You <b>cannot</b> receive notifications via telegram.',
	'EBT_PERMISSION_BROWSE_YES' => 'You can browse and read the forum via telegram.',
	'EBT_PERMISSION_BROWSE_NO' => 'You <b>cannot</b> browse and read the forum via telegram.',
	'EBT_PERMISSION_POST_YES' => 'You can post new topics and replies via telegram.',
	'EBT_PERMISSION_POST_NO' => 'You <b>cannot</b> post new topics and replies via telegram.',

	//Button outdated
	'EBT_BUTTON_OUTDATED' => 'Please use only buttons of the last message.',
	'EBT_OK' => 'OK',

	//Illegal Forum was somehow set
	'EBT_ILLEGAL_FORUM' => 'You don\'t have access to the selected forum.',

	//Send new posts and topics
	'EBT_REQUEST_POST' => 'Send your reply or use the cancel button.',
	'EBT_REQUEST_TITLE' => 'Send the title for your new post or use the cancel button.',
	'EBT_REQUEST_TEXT_FOR_TITLE' => 'Send the text for your new post with title <b>%s</b> or use the cancel button.',
	'EBT_CANCEL' => 'Cancel',
	'EBT_TOPIC_SAVED' => 'The following topic was saved:',
	'EBT_TOPIC_SAVE_FAILED' => 'The new post could not be saved. (!?)',

	//Illegal call
	'EBT_GROUP_OR_CHANNEL_CALL' => 'The forum cannot be called via groups or channels.',

]);
