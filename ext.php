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

    //TODO: Add a disable_step() which deletes all entries
    //for telegram-method in the users_notification table.
    //Otherwise the forum will try to load this notification method
    //and fail.
   	/**
	* @param mixed $old_state State returned by previous call of this method
	* @return false Indicates no further steps are required
	*/
	public function disable_step($old_state)
	{
        //Delete from phpbb_user_notifications where method = 'notification.method.telegram'.
        //Maybe this needs to be implemented in a migration class with a special disable-method
        //which is called here. The base class only has db/migration injected, but not DB itself.

        
        $migration = $this->migrator->get_migration('\eb\telegram\migrations\delete_method');
        $migration->delete_notification_method();
        return false;
        // Only have the finder search in this extension path directory
        $migrations = $this->extension_finder
        ->extension_directory('/migrations')
        ->find_from_extension($this->extension_name, $this->extension_path);

        $migrations = $this->extension_finder->get_classes_from_files($migrations);

        foreach ($migrations as $migration) {
            if (method_exists($migration, 'delete_notification_method')) {
                //echo "<br>Found matching $migration in disable_step<br>";
                $migration->delete_notification_method();
            }
        }
	}
}
