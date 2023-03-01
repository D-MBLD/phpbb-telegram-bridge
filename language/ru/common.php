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

	//Notification
	'EBT_FULL_TOPIC'	=> 'Показать тему',

	//Forum list
	'EBT_FORUM_LIST_TITLE'	=> '<u>%1$s имеет %2$s раздела(ов):</u>',
	'EBT_SELECT_A_FORUM'	=> 'Используйте одну из кнопок, чтобы выбрать раздел.',
	'EBT_LAST_POST'	=> 'Последнее сообщение %1$s от <b>%2$s</b>:',
	'EBT_READ_ONLY' => ' (только чтение)',
	'EBT_BACK'	=> 'Назад',
	'EBT_ILLEGAL_INPUT' => " \xE2\x9A\xA0 Команда <b>%s</b> не существует.",  //"\xE2\x9A\xA0" = ⚠

	//Topic list
	'EBT_TOPIC_LIST_TITLE' => 'Раздел <b>%1$s</b> имеет %2$s тем(ы):',
	'EBT_TOPIC_LIST_TITLE_EMPTY' => 'В настоящее время в разделе <b>%s</b> нет тем.',
	'EBT_SELECT_TOPIC' => 'Используйте одну из кнопок, чтобы выбрать тему.',
	'EBT_SELECT_NEXT_PAGE' => 'Отправьте знак \'+\'- или \'-\', чтобы показать следующую/предыдущую страницу.',
	'EBT_FORUM_NOT_FOUND' => 'Could not read the forum (!?). Please try again',
	'EBT_SHOW_FORUMS' => 'Показать другие разделы',
	'EBT_ADD_TOPIC' => 'Добавить тему',

	//Post list
	'EBT_TOPIC_AT_BY' => '<b>%1$s:</b> Тема создана <b>%2$s</b>:', //time, user
	'EBT_TOPIC_TITLE' => 'Заголовок: <b>%s</b>',
	'EBT_REPLY_AT_BY' => '<b>%1$s:</b> Ответ от <b>%2$s</b>', //time, user
	'EBT_TOPIC_SHORTENED' => '<b>Внимание: Тема слишком длинная и была обрезана. Telegram не допускает более 4096 символов!</b>',
	'EBT_NEW_REPLY' => 'Ответить',
	'EBT_ILLEGAL_TOPIC_ID' => 'Illegal attempt to read topic with ID %s',

	//Helpscreen unregistered user
	//Placeholders: Sitename, Site-URL, telegram-id
	'EBT_HELP_SCREEN_NON_MEMBER' => 'Этот сервис может использоваться только зарегистрированными ' .
	'пользователями форума <a href="%2$s">%1$s</a>. ' .
	'Если вы зарегистрированы, то необходимо указать свой <b>Telegram ID</b> в настройках профиля на форуме' .
	'<br><br>Ваш Telegram ID: <code>%3$s</code>' .
	'<br><br>После этого вам также следует выбрать события, о которых вы хотите получать уведомления ' .
	'через Telegram в настройках уведомлений вашего профиля.',

	//Registration screens
	'EBT_HELP_SCREEN_REGISTERED' => '<b>Ваш Telegram ID был успешно подтвержден.</b>' .
								'<br>Не забудьте выбрать события, о которых вы хотите получать уведомления. ' .
								'Перейдите в свой профиль, далее в разделе ' .
								'"Личные настройки -> Изменить настройки уведомлений" отметьте нужные события в столбце Telegram.',
	'EBT_ILLEGAL_CODE' => " \xE2\x9A\xA0 Введенный код неверен. Пожалуйста, запросите новый.<br>", //"\xE2\x9A\xA0" = ⚠
	'EBT_HELP_SCREEN_REGISTRATION_FAILED' => '<b>Ваш идентификатор Telegram еще не подтвержден.</b>' .
								'<br>Используйте кнопку ниже, чтобы запросить электронное письмо на адрес, указанный в вашем профиле на форуме. ' .
								'Электронное письмо будет содержать код, который вам нужно отправить один раз через telegram в бота, ' .
								'чтобы подтвердить свой Telegram ID.',

	'EBT_HELP_SCREEN_EMAILED' => 	'<b>Письмо отправлено.</b>' .
								'<br>Проверьте свой почтовый ящик и, при необходимости, также папку со спамом. ' .
								'<br>Затем отправьте код сюда.' .
								'<br>Не отправляйте другие сообщения до этого. В противном случае вам придется запросить новый код.',

	'EBT_REQUEST_EMAIL'	=> 'Отправить письмо',

	//Button outdated
	'EBT_BUTTON_OUTDATED' => 'Пожалуйста, используйте только кнопки последнего сообщения.',
	'EBT_OK' => 'OK',

	//Illegal Forum was somehow set
	'EBT_ILLEGAL_FORUM' => 'У вас нет доступа к выбранному разделу.',

	//Send new posts and topics
	'EBT_REQUEST_POST' => 'Отправьте текст своего ответа или воспользуйтесь кнопкой отмены.',
	'EBT_REQUEST_TITLE' => 'Отправьте заголовок для вашей новой темы или воспользуйтесь кнопкой отмены.',
	'EBT_REQUEST_TEXT_FOR_TITLE' => 'Отправьте текст сообщения для вашей новой темы с заголовком <b>%s</b> или воспользуйтесь кнопкой отмены.',
	'EBT_CANCEL' => 'Отмена',
	'EBT_TOPIC_SAVED' => 'Тема создана:',
	'EBT_TOPIC_SAVE_FAILED' => 'The new post could not be saved. (!?)',

	//Illegal call
	'EBT_GROUP_OR_CHANNEL_CALL' => 'К форуму нельзя обратиться через группы или каналы.',

]);
