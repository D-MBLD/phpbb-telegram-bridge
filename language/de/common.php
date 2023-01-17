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

	'EB_TELEGRAM_NOTIFICATION'	=> 'Telegram Nachrichten',
	//Automatically compiled as column title in notification method selection
	'NOTIFICATION_METHOD_TELEGRAM'	=> 'Telegram',
	//Used in the html events (Prefixed with L_)
	'TELEGRAM_ID'			=> 'Deine Telegram ID',
	'TELEGRAM_ID_DESCR'		=> 'Nimm den passenden bot in deine Telegram Kontakte auf und gib hier deine Telegram-ID ein.',
	'TELEGRAM_ID_NOT_NUMERIC'	=> 'Die Telegram-ID darf nur aus Ziffern bestehen.',

	//Notification
	'FULL_TOPIC'	=> 'Beitrag vollst. anzeigen',

	//Forum list
	'FORUM_LIST_TITLE'	=> '<u>In %1$s gibt es %2$s Foren:</u>',
	'SELECT_A_FORUM'	=> 'Nutze die Tasten um ein Forum auszuwählen.',
	'LAST_POST'	=> 'Letzter Eintrag am %1$s von <b>%2$s</b>:',
	'READ_ONLY' => ' (nur lesen)',
	'BACK'	=> 'Zurück',
	'ILLEGAL_INPUT' => " \xE2\x9A\xA0 Die Eingabe <b>%s</b> ist ungültig.", //"\xE2\x9A\xA0" = ⚠

	//Topic list
	'TOPIC_LIST_TITLE' => 'Im Forum <b>%1$s</b> gibt es %2$s Beiträge:',
	'TOPIC_LIST_TITLE_EMPTY' => 'Im Forum <b>%s</b> gibt es momentan keine Beiträge.',
	'SELECT_TOPIC' => 'Nutze die Tasten um einen Beitrag anzuzeigen.',
	'SELECT_NEXT_PAGE' => 'Sende ein \'+\'- oder \'-\'-Zeichen um zur nächsten/vorherigen Seite zu blättern.',
	'FORUM_NOT_FOUND' => 'Das Forum konnte nicht gelesen werden (!?). Bitte versuche es erneut.',
	'SHOW_FORUMS' => 'Alle Foren anzeigen',
	'ADD_TOPIC' => 'Neuen Beitrag senden',

	//Post list
	'TOPIC_AT_BY' => '<b>%1$s:</b> Beitrag angelegt von <b>%2$s</b>:', //time, user
	'TOPIC_TITLE' => 'Titel: <b>%s</b>',
	'REPLY_AT_BY' => '<b>%1$s:</b> Antwort von <b>%2$s</b>', //time, user
	'TOPIC_SHORTENED' => '<b>Achtung: Der Beitrag ist zu lang und wird nicht vollständig angezeigt. Telegram erlaubt maximal 4096 Zeichen !</b>',
	'NEW_REPLY' => 'Antwort senden',
	'ILLEGAL_TOPIC_ID' => 'Beitrag mit ID %s existiert nicht',

	//Helpscreen unregistered user
	//Placeholders: Sitename, Site-URL, telegram-id
	'HELP_SCREEN_NON_MEMBER' => 'Dieser Service kann nur von registrierten Benutzern des Forums ' .
								'<a href="%2$s">%1$s</a> benutzt werden.' .
								'<br>Falls du registrierter Benutzer bist, gib deine Telegram-Id (%3$s) in deinem ' .
								'Forums-Profil ein.' .
								'<br>Um Benachrichtigungen zu erhalten, musst du außerdem unter den ' .
								'Notifikations-Einstellungen die gewünschten Telegram-Notifikationen auswählen.',

	//Button outdated
	'BUTTON_OUTDATED' => 'Bitte nutze nur die Tasten der letzten Telegram-Nachricht.',
	'OK' => 'OK',

	//Illegal Forum was somehow set
	'ILLEGAL_FORUM' => 'Du hast auf das ausgewählte Forum keinen Zugriff (mehr?).',

	//Send new posts and topics
	'REQUEST_POST' => 'Sende deine Antwort oder nutze die Abbrechen-Taste.',
	'REQUEST_TITEL' => 'Sende den Titel deines Beitrags, oder nutze die Abbrechen-Taste.',
	'REQUEST_TEXT_FOR_TITLE' => 'Sende den Text zu deinem neuen Beitrag <b>%s</b>, oder nutze die Abbrechen-Taste.',
	'CANCEL' => 'Abbrechen',
	'TOPIC_SAVED' => 'Der folgende Beitrag wurde gespeichert:',
	'TOPIC_SAVE_FAILED' => 'Der neue Beitrag konnte nicht gespeichert werden. (!?)',

	//Illegal call
	'GROUP_OR_CHANNEL_CALL' => 'Das Forum kann nicht über Gruppen oder Kanäle aufgerufen werden.',

]);
