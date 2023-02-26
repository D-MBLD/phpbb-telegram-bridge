<?php
/**
 *
 * Telegram Bridge. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Edgar Bolender
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

 /** Note: Any language file starting with info_acp is automatically loaded into the
  * ACP modules.
  * As some of the variables are also used in UCP, the file is also loaded
  * in the core.user_setup event.
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

	//Used in the event ucp_profile_profile_info_after.html
	'EBT_TELEGRAM_ID'					=> 'Your Telegram ID',
	'EBT_TELEGRAM_ID_DESCR'				=> 'Enter your telegram id (numeric id, not name) and add the forums bot (@%s) to your telegram contacts. ' .
									   'If you don\'t know your id, send an arbitrary message to the bot.',

	//Used in the event acp_users_profile_after.html
	'EBT_ACP_UP_TELEGRAM_ID'			=> 'Telegram id',
	'EBT_ACP_UP_TELEGRAM_ID_DESCR'		=> 'Users numeric telegram id.',

	//Used in both events acp_users_profile_after.html and ucp_profile_profile_info_after.html
	'EBT_TELEGRAM_ID_NOT_NUMERIC'		=> 'The telegram id must be numeric.',
	'EBT_TELEGRAM_ID_ALREADY_USED'		=> '<b><font color="red">Same telegram id is already used by another user!</font></b>',

	//Language entries for the ACP-Module
	'ACP_TELEGRAM_TITLE'			=> 'Telegram Bridge Module',
	'ACP_TELEGRAM'					=> 'Settings',

	'EBT_SETTINGS_UPDATED'		=> '<strong>Telegram Bridge settings updated</strong>',

	'EBT_SETTINGS_BOT_TOKEN'		=> '<strong>Telegram Bot-Token</strong>',
	'EBT_SETTINGS_BOT_TOKEN_DESCR'	=> 'The API token as provided by @BotFather in a format like 123456789:AaZz0...AaZz9.',
	'EBT_SETTINGS_SECRET'			=> '<strong>Secret string for webhook requests</strong>',
	'EBT_SETTINGS_SECRET_DESCR'		=> 'Arbitrary secret string, used when registering the webhook. The bot will then send it in the header of every request, so it is ensured only requests from the bot are accepted.',

	'EBT_SETTINGS_FOOTER'			=> '<strong>Footer line</strong>',
	'EBT_SETTINGS_FOOTER_DESCR'		=> 'A line, which is added to every post sent via telegram. You may add a link to a description, how users can register for the telegram bridge.',
	'EBT_SETTINGS_FOOTER_DEFAULT'	=> '[size=85][i]This message was sent via telegram.[/i][/size]',

	'EBT_SETTINGS_WEBHOOK'			=> '<strong>URL for webhook registration</strong>',
	'EBT_SETTINGS_WEBHOOK_DESCR'	=> 'You can register the webhook by just clicking the link, after bot token and secret string have been saved.',
	'EBT_SETTINGS_WEBHOOK_TEMPLATE' => 'https://api.telegram.org/bot%s/setWebhook?url=%s/telegram/webhook&secret_token=%s',

	'EBT_SETTINGS_ADMIN_ID'			=> '<strong>Telegram id of an admin</strong>',
	'EBT_SETTINGS_ADMIN_ID_DESCR'	=> 'If set, all requests sent to the webhook, which can not be interpreted, are forwarded to this telegram user. ' .
									   'This could help to identify attempts to mis-use the bot, but also legal requests not yet handled by the extension.',

	'EBT_SETTINGS_ADMIN_ECHO'		=> '<strong>Echo all input to admin id</strong>',
	'EBT_SETTINGS_ADMIN_ECHO_DESCR'	=> 'For debugging all requests to the bot can be echoed to the telegram id above.' .
									   'To avoid spamming your own account, you may create a group or a channel and use ' .
									   'the ID of this account.',


]);
