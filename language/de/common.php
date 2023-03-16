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
	'EB_TELEGRAM_NOTIFICATION_METHOD_TELEGRAM'	=> 'Telegram',
	'EB_TELEGRAM_NOTIFICATION_METHOD_TELEGRAM_NP'	=> 'Telegram<br>(Keine Berechtigung)',

	//Notification
	'EBT_FULL_TOPIC'	=> 'Beitrag vollst. anzeigen',
	'EBT_SHOW_FORUM'	=> 'Forum anzeigen',

	//Forum list
	'EBT_FORUM_LIST_TITLE'	=> '<u>In %1$s gibt es %2$s Foren:</u>',
	'EBT_SELECT_A_FORUM'	=> 'Nutze die Tasten um ein Forum auszuwählen.',
	'EBT_LAST_POST'	=> 'Letzter Eintrag am %1$s von <b>%2$s</b>:',
	'EBT_READ_ONLY' => ' (nur lesen)',
	'EBT_BACK'	=> 'Zurück',
	'EBT_ILLEGAL_INPUT' => " \xE2\x9A\xA0 Die Eingabe <b>%s</b> ist ungültig.", //"\xE2\x9A\xA0" = ⚠

	//Topic list
	'EBT_TOPIC_LIST_TITLE' => 'Im Forum <a href="%3$s"><b>%1$s</b></a> gibt es %2$s Beiträge:',
	'EBT_TOPIC_LIST_TITLE_EMPTY' => 'Im Forum <a href="%3$s"><b>%1$s</b></a> gibt es momentan keine Beiträge.',
	'EBT_SELECT_TOPIC' => 'Nutze die Tasten um einen Beitrag anzuzeigen.',
	'EBT_SELECT_NEXT_PAGE' => 'Sende ein \'+\'- oder \'-\'-Zeichen um zur nächsten/vorherigen Seite zu blättern.',
	'EBT_FORUM_NOT_FOUND' => 'Das Forum konnte nicht gelesen werden (!?). Bitte versuche es erneut.',
	'EBT_SHOW_FORUMS' => 'Alle Foren anzeigen',
	'EBT_ADD_TOPIC' => 'Neuen Beitrag senden',

	//Post list
	'EBT_TOPIC_AT_BY' => '<b>%1$s:</b> Beitrag angelegt von <b>%2$s</b>', //time, user
	'EBT_TOPIC_TITLE' => 'Titel: <a href="%2$s"><b>%1$s</b></a>',
	'EBT_REPLY_AT_BY' => '<b>%1$s:</b> Antwort von <b>%2$s</b>', //time, user
	'EBT_TOPIC_SHORTENED' => '<b>Achtung: Der Beitrag ist zu lang und wird nicht vollständig angezeigt. Telegram erlaubt maximal 4096 Zeichen !</b>',
	'EBT_NEW_REPLY' => 'Antwort senden',
	'EBT_ILLEGAL_TOPIC_ID' => 'Beitrag mit ID %s existiert nicht',

	//Helpscreen unregistered user
	//Placeholders: Sitename, Site-URL, telegram-id
	'EBT_HELP_SCREEN_NON_MEMBER' => 'Dieser Service kann nur von registrierten Benutzern des Forums ' .
									'<a href="%2$s">%1$s</a> benutzt werden.' .
									'<br>Falls du registrierter Benutzer bist, trage deine Telegram-Id (<code>%3$s</code>) in deinem ' .
									'Forums-Profil ein.',

	//Registration Screens
	'EBT_HELP_SCREEN_REGISTERED' => '<b>Deine Telegram-ID wurde erfolgreich verifiziert.</b>',

	'EBT_ILLEGAL_CODE' => " \xE2\x9A\xA0 Der Code war falsch. Bitte fordere einen neuen Code an.", //"\xE2\x9A\xA0" = ⚠
	'EBT_HELP_SCREEN_REGISTRATION_FAILED' => '<b>Deine Telegram-ID ist noch nicht verifiziert.</b>' .
								'<br>Nutze die Taste um eine E-Mail an deine hinterlegte Adresse zu senden. ' .
								'Die E-Mail enthält einen Code, den du per Telegram senden musst.',

	'EBT_HELP_SCREEN_EMAILED' => '<b>E-Mail wurde gesendet.</b>' .
								'<br>Prüfe deine Inbox und evtl. auch die Spam-Ordner. ' .
								'<br>Sende dann sofort den Code als Nachricht.' .
								'<br>Sende dazwischen keine anderen Nachrichten, sonst musst du erneut einen Code anfordern.' ,

	'EBT_REQUEST_EMAIL'	=> 'E-Mail anfordern',

	//Permissions
	'EBT_PERMISSION_TITEL' => '<b>Du hast folgende Berechtigungen:</b>',
	'EBT_PERMISSION_NOTIFY_YES' => ' - Du kannst Benachrichtigungen per Telegram erhalten.',
	'EBT_PERMISSION_NOTIFY_NO' => ' - Du kannst <b>keine</b> Benachrichtigungen per Telegram erhalten.',
	'EBT_PERMISSION_BROWSE_YES' => ' - Du kannst das Forum per Telegram durchblättern und lesen.',
	'EBT_PERMISSION_BROWSE_NO' => ' - Du kannst das Forum per Telegram <b>nicht</b> lesen.',
	'EBT_PERMISSION_POST_YES' => ' - Du kannst neue Beiträge und Antworten per Telegram schreiben.',
	'EBT_PERMISSION_POST_NO' => ' - Du kannst <b>keine</b> neuen Beiträge per Telegram schreiben.',
	'EBT_SELECT_NOTIFICATIONS' => 'Vergiss nicht in deinem Profil die Ereignisse auszuwählen, für die du ' .
								'Benachrichtigungen erhalten möchtest. ' .
								'Gehe hierzu in dein Profil, wähle "Profil ändern", dann "Einstellungen->Benachrichtigungen einstellen" und ' .
								'wähle in der Telegram-Spalte die Ereignisse aus.',

	//Button outdated
	'EBT_BUTTON_OUTDATED' => 'Bitte nutze nur die Tasten der letzten Telegram-Nachricht.',
	'EBT_OK' => 'OK',

	//Illegal Forum was somehow set
	'EBT_ILLEGAL_FORUM' => 'Du hast auf das ausgewählte Forum keinen Zugriff (mehr?).',

	//Send new posts and topics
	'EBT_REQUEST_POST' => 'Sende deine Antwort oder nutze die Abbrechen-Taste.',
	'EBT_REQUEST_TITLE' => 'Sende den Titel deines Beitrags, oder nutze die Abbrechen-Taste.',
	'EBT_REQUEST_TEXT_FOR_TITLE' => 'Sende den Text zu deinem neuen Beitrag <b>%s</b>, oder nutze die Abbrechen-Taste.',
	'EBT_CANCEL' => 'Abbrechen',
	'EBT_TOPIC_SAVED' => 'Der folgende Beitrag wurde gespeichert:',
	'EBT_TOPIC_SAVE_FAILED' => 'Der neue Beitrag konnte nicht gespeichert werden. (!?)',

	//Illegal call
	'EBT_GROUP_OR_CHANNEL_CALL' => 'Das Forum kann nicht über Gruppen oder Kanäle aufgerufen werden.',

]);
