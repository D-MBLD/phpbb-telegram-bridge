<?php
/**
 *
 * Telegram Bridge. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Edgar Bolender
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace eb\telegram;

/**
 * Telegram Bridge Extension base
 *
 * It is recommended to remove this file from
 * an extension if it is not going to be used.
 */
class ext extends \phpbb\extension\base
{

	//Add a disable_step() which deletes all entries
	//for telegram-method in the users_notification table.
	//Otherwise the forum will try to load this notification method
	//and fail.
	/**
	* @param mixed $old_state State returned by previous call of this method
	* @return false Indicates no further steps are required
	*/
	public function disable_step($old_state)
	{
		//Move the telegram specific entries from phpbb_user_notifications
		//into a backup table.
		//The implementation is moved into a migration class because the extension\base class
		//does not have the necessary services injected.
		$migration = $this->migrator->get_migration('\eb\telegram\migrations\delete_method');
		$migration->backup_user_notifications();
		return false;
	}

	public function enable_step($old_state)
	{
		$finished = parent::enable_step($old_state);
		$migration = $this->migrator->get_migration('\eb\telegram\migrations\delete_method');
		$migration->restore_user_notifications();
		return $finished;
	}

}
