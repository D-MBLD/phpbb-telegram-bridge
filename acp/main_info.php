<?php
/**
 *
 * Telegram Bridge. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Edgar Bolender
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace eb\telegram\acp;

/**
 * Telegram Bridge ACP module info.
 */
class main_info
{
	public function module()
	{
		return [
			'filename'	=> '\eb\telegram\acp\main_module',
			'title'		=> 'ACP_TELEGRAM_TITLE',
			'modes'		=> [
				'settings'	=> [
					'title'	=> 'ACP_TELEGRAM',
					'auth'	=> 'ext_eb/telegram && acl_a_board',
					'cat'	=> ['ACP_TELEGRAM_TITLE'],
				],
			],
		];
	}
}
