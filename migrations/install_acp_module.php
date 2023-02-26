<?php
/**
 *
 * Telegram Bridge. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Edgar Bolender
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace eb\telegram\migrations;

class install_acp_module extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return false; //Always install
	}

	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v320\v320'];
	}

	public function update_data()
	{
		return [
			['config.add', ['eb_telegram_bot_token', '']],
			['config.add', ['eb_telegram_secret', '']],
			['config.add', ['eb_telegram_footer', '']],
			['config.add', ['eb_telegram_admin_telegram_id', '']],
			['config.add', ['eb_telegram_admin_echo', '']],

			['module.add', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_TELEGRAM_TITLE'
			]],
			['module.add', [
				'acp',
				'ACP_TELEGRAM_TITLE',
				[
					'module_basename'	=> '\eb\telegram\acp\main_module',
					'modes'				=> ['settings'],
				],
			]],
		];
	}

	public function revert_data()
	{
		return [
			['config.remove', ['eb_telegram_admin_user']], //Could exist from older version
			['config.remove', ['eb_telegram_admin_pw']], //Could exist from older version
			['config.remove', ['eb_telegram_bot_token']],
			['config.remove', ['eb_telegram_secret']],
			['config.remove', ['eb_telegram_footer']],
			['config.remove', ['eb_telegram_admin_telegram_id']],
			['config.remove', ['eb_telegram_admin_echo']],
			// Remove the modules
			['module.remove', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_TELEGRAM_TITLE'
			]],
			// Add our main_module to the parent module (ACP_TELEGRAM_TITLE)
			['module.remove', [
				'acp',
				0,
				'ACP_TELEGRAM_TITLE',
			]],
		];
	}
}
