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
	'EBT_TELEGRAM_ID'					=> 'Ваш Telegram ID',
	'EBT_TELEGRAM_ID_DESCR'				=> 'введите свой цифровой Telegram ID (не путать с @username!) и добавьте бота (<a href="https://t.me/%s">@%s</a>) в свои контакты в Telegram. ' .
									   'Если вы не знаете свой цифровой ID - отправьте любое сообщение боту и он вам его сообщит.',

	//Used in the event acp_users_profile_after.html
	'EBT_ACP_UP_TELEGRAM_ID'			=> 'Telegram ID',
	'EBT_ACP_UP_TELEGRAM_ID_DESCR'		=> 'Цифровой Telegram ID пользователей.',

	//Used in both events acp_users_profile_after.html and ucp_profile_profile_info_after.html
	'EBT_TELEGRAM_ID_NOT_NUMERIC'		=> 'Telegram ID должен состоять только из цифр.',
	'EBT_TELEGRAM_ID_ALREADY_USED'		=> '<b><font color="red">Указанный Telegram ID уже используется другим пользователем!</font></b>',

	//Language entries for the ACP-Module
	'ACP_TELEGRAM_TITLE'			=> 'Telegram Bridge Module',
	'ACP_TELEGRAM'					=> 'Настройки',

	'EBT_SETTINGS_UPDATED'		=> '<strong>Настройки Telegram Bridge обновлены</strong>',

	'EBT_SETTINGS_BOT_TOKEN'		=> '<strong>Telegram Bot-Token</strong>',
	'EBT_SETTINGS_BOT_TOKEN_DESCR'	=> 'Токен API бота, предоставленный @BotFather в формате: 123456789:AaZz0...AaZz9.',
	'EBT_SETTINGS_SECRET'			=> '<strong>Secret-string для запросов webhook</strong>',
	'EBT_SETTINGS_SECRET_DESCR'		=> 'Произвольная секретная строка, используемая при регистрации webhook. Бот будет отправлять это значение в заголовке каждого запроса, поэтому гарантируется, что принимаются только запросы от бота.',

	'EBT_SETTINGS_FOOTER'			=> '<strong>Футер сообщений</strong>',
	'EBT_SETTINGS_FOOTER_DESCR'		=> 'Строка, которая добавляется к каждому сообщению, отправленному через Telegram. Вы можете добавить ссылку на описание того, как пользователи могут зарегистрироваться в telegram bridge.',
	'EBT_SETTINGS_FOOTER_DEFAULT'	=> '[size=85][i]Это сообщение отправлено через telegram.[/i][/size]',

	'EBT_SETTINGS_WEBHOOK'			=> '<strong>URL для регистрации webhook</strong>',
	'EBT_SETTINGS_WEBHOOK_DESCR'	=> 'После сохранения токена и секретной строки, вы можете зарегистрировать webhook, просто щелкнув по ссылке.',
	'EBT_SETTINGS_WEBHOOK_TEMPLATE' => 'https://api.telegram.org/bot%s/setWebhook?url=%s/telegram/webhook&secret_token=%s',

	'EBT_SETTINGS_ADMIN_ID'			=> '<strong>Telegram ID администратора</strong>',
	'EBT_SETTINGS_ADMIN_ID_DESCR'	=> 'Если задано, все запросы, отправленные на webhook, которые не могут быть интерпретированы, пересылаются этому пользователю в Telegram. ' .
									   'Это может помочь выявить попытки неправильного использования бота, а также верные запросы, которые расширение еще не обработало.',

	'EBT_SETTINGS_ADMIN_ECHO'		=> '<strong>Повторять все вводимые данные на ID администратора</strong>',
	'EBT_SETTINGS_ADMIN_ECHO_DESCR'	=> 'Для отладки все запросы к боту могут быть отправлены по указанному выше Telegram ID. ' .
									   'Чтобы избежать рассылки спама в вашу собственную учетную запись, ' .
									   'вы можете создать группу или канал и использовать идентификатор этой учетной записи.',


]);
