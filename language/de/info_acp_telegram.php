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
	'EBT_TELEGRAM_ID'					=> 'Deine Telegram ID',
	'EBT_TELEGRAM_ID_DESCR'				=> 'Nimm den Forums-Bot (<a href="https://t.me/%1$s">@%1$s</a>) in deine Telegram Kontakte auf und gib hier deine Telegram-ID ein. ' .
											'Sende dem Bot eine beliebige Nachricht, um deine ID zu erfahren.',

	//Used in the event acp_users_profile_after.html
	'EBT_ACP_UP_TELEGRAM_ID'					=> 'Telegram ID',
	'EBT_ACP_UP_TELEGRAM_ID_DESCR'				=> 'Numerische Telegram-ID des Benutzers.',

	//Used in both events acp_users_profile_after.html and ucp_profile_profile_info_after.html
	'EBT_TELEGRAM_ID_NOT_NUMERIC'		=> 'Die Telegram-ID muss numerisch sein.',
	'EBT_TELEGRAM_ID_ALREADY_USED'	=> '<b><font color="red">Die gleiche Telegram-ID wird schon von einem anderen User benutzt!</font></b>',

	//Language entries for the ACP-Module
	'ACP_TELEGRAM_TITLE'			=> 'Modul Telegram-Anbindung',
	'ACP_TELEGRAM'					=> 'Einstellungen',

	'EBT_SETTINGS_UPDATED'			=> '<strong>Einstellungen der Telegram-Anbindung geändert.</strong>',

	'EBT_SETTINGS_BOT_TOKEN'		=> '<strong>Telegram Bot-Token</strong>',
	'EBT_SETTINGS_BOT_TOKEN_DESCR'	=> 'API-token. Wird von @BotFather beim Einrichten des Bots vergeben. Format 123456789:AaZz0...AaZz9.',
	'EBT_SETTINGS_SECRET'			=> '<strong>Geheimer Text für WebHook-Aufrufe</strong>',
	'EBT_SETTINGS_SECRET_DESCR'		=> 'Ein beliebiger Text (Zeichen/Ziffern) für die WebHook-Registrierung. Der Bot sendet diesen Text immer mit, um sicherzustellen, dass die Aufrufe nur vom Bot kommen.',

	'EBT_SETTINGS_FOOTER'			=> '<strong>Fußzeile</strong>',
	'EBT_SETTINGS_FOOTER_DESCR'		=> 'Ein Text, der an jeden per Telegram gesendeten Beitrag angehängt wird. Der Text kann auch einen Link auf einen Forumsbeitrag enhalten, in der die Funktion (für andere Benutzer) erklärt wird.',
	'EBT_SETTINGS_FOOTER_DEFAULT'	=> '[size=85][i]Dieser Beitrag wurde per Telegram gesendet.[/i][/size]',

	'EBT_SETTINGS_WEBHOOK'			=> '<strong>URL für die WebHook-Registrierung</strong>',
	'EBT_SETTINGS_WEBHOOK_DESCR'	=> 'Du kannst den WebHook registrieren, indem du einfach auf den Link klickst, nachdem Token und geheimer Text gespeichert wurden.',
	'EBT_SETTINGS_WEBHOOK_TEMPLATE' => 'https://api.telegram.org/bot%s/setWebhook?url=%s/telegram/webhook&secret_token=%s',

	'EBT_SETTINGS_ADMIN_ID'			=> '<strong>Telegram-ID für den Administrator</strong>',
	'EBT_SETTINGS_ADMIN_ID_DESCR'	=> 'Wenn eine ID eingetragen ist, werden alle ungültigen Anfragen, an diese ID weitergeleitet. ' .
									   'Damit können Versuche den Bot zu missbrauchen beobachtet werden, aber auch gültige Aufrufe, die evtl. noch nicht korrekt erkannt werden.',

	'EBT_SETTINGS_ADMIN_ECHO'		=> '<strong>Fehlersuche: Alle Aufrufe zusätzlich an o.g. ID weiterleiten</strong>',
	'EBT_SETTINGS_ADMIN_ECHO_DESCR'	=> 'Zur Fehlersuche können alle Bot-Aufrufe, an die o.g. Telegram-ID weitergeleitet werden. ' .
									   'Um den eigenen Account vor zu vielen Anfragen zu schützen, empfielt es sich hier die ID einer eigens eingerichteten Gruppe oder eines Channels zu nutzen ' .
									   'der die eigene ID enthält.',

]);
