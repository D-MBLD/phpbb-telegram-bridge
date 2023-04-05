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

	'EB_TELEGRAM_NOTIFICATION'	=> 'Уведомления Telegram Bridge',
	//Automatically compiled as column title in notification method selection
	'EB_TELEGRAM_NOTIFICATION_METHOD_TELEGRAM'	=> 'Telegram',
	'EB_TELEGRAM_NOTIFICATION_METHOD_TELEGRAM_NP'	=> 'Telegram<br>(Нет доступа)',

	//Notification
	'EBT_FULL_TOPIC'	=> 'Показать тему',
	'EBT_SHOW_FORUM'	=> 'Показать форум',

	//Forum list
	'EBT_FORUM_LIST_TITLE'	=> '<u>Конференция %1$s имеет %2$s форума(ов):</u>',
	'EBT_SELECT_A_FORUM'	=> 'Используйте одну из кнопок, чтобы выбрать форум.',
	'EBT_LAST_POST'	=> 'Последнее сообщение %1$s от <b>%2$s</b>:',
	'EBT_READ_ONLY' => ' (только чтение)',
	'EBT_BACK'	=> 'Назад',
	'EBT_ILLEGAL_INPUT' => " \xE2\x9A\xA0 Команда <b>%s</b> не существует.",  //"\xE2\x9A\xA0" = ⚠

	//Topic list
	'EBT_TOPIC_LIST_TITLE' => 'Форум <a href="%3$s"><b>%1$s</b></a> имеет %2$s тем(ы):',
	'EBT_TOPIC_LIST_TITLE_EMPTY' => 'В настоящее время в форуме <a href="%3$s"><b>%s</b></a> нет тем.',
	'EBT_SELECT_TOPIC' => 'Используйте одну из кнопок, чтобы выбрать тему.',
	'EBT_SELECT_NEXT_PAGE' => 'Отправьте знак \'+\'- или \'-\', чтобы показать следующую/предыдущую страницу.',
	'EBT_SHOW_FORUMS' => 'Показать другие форумы',
	'EBT_ADD_TOPIC' => 'Добавить тему',

	//Post list
	'EBT_TOPIC_AT_BY' => '<b>%1$s:</b> Тема создана <b>%2$s</b>:', //time, user
	'EBT_TOPIC_TITLE' => 'Заголовок: <a href="%2$s"><b>%1$s</b></a>',
	'EBT_REPLY_AT_BY' => '<b>%1$s:</b> Ответ от <b>%2$s</b>', //time, user
	'EBT_TOPIC_SHORTENED' => '<b>Внимание: Тема слишком длинная и была обрезана. Telegram не допускает более 4096 символов!</b>',
	'EBT_NEW_REPLY' => 'Ответить',
	'EBT_ILLEGAL_TOPIC_ID' => 'Неправомерная попытка прочитать тему с идентификатором %s',

	//Helpscreen unregistered user
	//Placeholders: Sitename, Site-URL, telegram-id
	'EBT_HELP_SCREEN_NON_MEMBER' => 'Этот сервис может использоваться только зарегистрированными ' .
	'пользователями конференции <a href="%2$s">%1$s</a>. ' .
	'Если вы зарегистрированы, то необходимо указать свой <b>Telegram ID</b> в настройках профиля на конференции' .
	'<br><br>Ваш Telegram ID: <code>%3$s</code>',

	//Registration screens
	'EBT_HELP_SCREEN_REGISTERED' => '<b>Ваш Telegram ID был успешно подтвержден.</b>',
	'EBT_ILLEGAL_CODE' => " \xE2\x9A\xA0 Введенный код неверен. Пожалуйста, запросите новый.<br>", //"\xE2\x9A\xA0" = ⚠
	'EBT_HELP_SCREEN_REGISTRATION_FAILED' => '<b>Ваш идентификатор Telegram еще не подтвержден.</b>' .
								'<br>Используйте кнопку ниже, чтобы запросить электронное письмо на адрес, указанный в вашем профиле на конференции. ' .
								'Электронное письмо будет содержать код, который вам нужно отправить один раз через Telegram в бота, ' .
								'чтобы подтвердить свой Telegram ID.',

	'EBT_HELP_SCREEN_EMAILED' => 	'<b>Письмо отправлено.</b>' .
								'<br>Проверьте свой почтовый ящик и, при необходимости, также папку со спамом. ' .
								'<br>Затем отправьте код сюда.' .
								'<br>Не отправляйте другие сообщения до этого. В противном случае вам придется запросить новый код.',

	'EBT_REQUEST_EMAIL'	=> 'Отправить письмо',
	
	//Permissions
	'EBT_PERMISSION_TITLE' => '<b>Права доступа:</b>',
	'EBT_PERMISSION_NOTIFY_YES' => ' - Вы можете получать уведомления через Telegram.',
	'EBT_PERMISSION_NOTIFY_NO' => ' - Вы <b>не можете</b> получать уведомления через Telegram.',
	'EBT_PERMISSION_BROWSE_YES' => ' - Вы можете просматривать и читать конференцию через Telegram.',
	'EBT_PERMISSION_BROWSE_NO' => ' - Вы <b>не можете</b> просматривать и читать конференцию через Telegram.',
	'EBT_PERMISSION_POST_YES' => ' - Вы можете публиковать новые темы и ответы через Telegram.',
	'EBT_PERMISSION_POST_NO' => ' - Вы <b>не можете</b> публиковать новые темы и ответы через Telegram.',
	'EBT_SELECT_NOTIFICATIONS' => '<br>Не забудьте выбрать события, о которых вы хотите получать уведомления. ' .
								'Перейдите в свой профиль, далее в разделе ' .
								'"Личные настройки -> Изменить настройки уведомлений" отметьте нужные события в столбце Telegram.',

	//Button outdated
	'EBT_BUTTON_OUTDATED' => 'Пожалуйста, используйте только кнопки последнего сообщения.',
	'EBT_OK' => 'OK',

	//Illegal Forum was somehow set
	'EBT_ILLEGAL_FORUM' => 'У вас нет доступа к выбранному форуму.',

	//Send new posts and topics
	'EBT_REQUEST_POST' => 'Отправьте текст своего ответа или воспользуйтесь кнопкой отмены.',
	'EBT_REQUEST_TITLE' => 'Отправьте заголовок для вашей новой темы или воспользуйтесь кнопкой отмены.',
	'EBT_REQUEST_TEXT_FOR_TITLE' => 'Отправьте текст сообщения для вашей новой темы с заголовком <b>%s</b> или воспользуйтесь кнопкой отмены.',
	'EBT_CANCEL' => 'Отмена',
	'EBT_TOPIC_SAVED' => 'Тема создана:',
	'EBT_TOPIC_SAVE_FAILED' => 'Новая тема не может быть сохранена. (Ошибка!?)',
	'EBT_NO_COMMAND_HERE' => "<b>Команды Telegram здесь ввести нельзя!</b>\n", //Translated with deepl

	//Illegal call
	'EBT_GROUP_OR_CHANNEL_CALL' => 'К конференции нельзя обратиться через группы или каналы.',

]);
