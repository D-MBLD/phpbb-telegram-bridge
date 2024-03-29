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

/** When this extension is deactivated, the method entries in the
 * user_notification table must be deleted. Otherwise the forum
 * tries to load the notification.method-class and fails.
 * (As soon as a new post is saved)
 * The class contains a method (not defined in the base class)
 * which is called in ext.php when deactivating the extension.
 */
class delete_method extends \phpbb\db\migration\migration
{

	/** @var \phpbb\db\driver\driver_interface */
	// protected $db; availabe from base-class

	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v320\v320'];
	}

	public function delete_notification_method()
	{
		$sql = 'DELETE FROM '. USER_NOTIFICATIONS_TABLE;
		$sql .= " WHERE method = 'eb.telegram.notification.method.telegram'";
		$result = $this->db->sql_query($sql);
	}

	public function backup_user_notifications()
	{
		$table_exists = $this->db_tools->sql_table_exists($this->table_prefix . 'eb_user_notifications');

		if ($table_exists)
		{
			$sql = 'DELETE FROM '. $this->table_prefix . 'eb_user_notifications';
			$result = $this->db->sql_query($sql);

			$sql = 'INSERT INTO ' . $this->table_prefix . 'eb_user_notifications';
			$sql .= ' SELECT * FROM '. USER_NOTIFICATIONS_TABLE;
			$sql .= " WHERE method = 'eb.telegram.notification.method.telegram'";
			$result = $this->db->sql_query($sql);
		}

		$sql = 'DELETE FROM '. USER_NOTIFICATIONS_TABLE;
		$sql .= " WHERE method = 'eb.telegram.notification.method.telegram'";
		$result = $this->db->sql_query($sql);
	}

	public function restore_user_notifications()
	{
		$table_exists = $this->db_tools->sql_table_exists($this->table_prefix . 'eb_user_notifications');

		if ($table_exists)
		{
			$sql = 'DELETE FROM '. USER_NOTIFICATIONS_TABLE;
			$sql .= " WHERE method = 'eb.telegram.notification.method.telegram'";
			$result = $this->db->sql_query($sql);

			$sql = 'INSERT INTO ' . USER_NOTIFICATIONS_TABLE;
			$sql .= ' SELECT * FROM ' . $this->table_prefix . 'eb_user_notifications';
			$result = $this->db->sql_query($sql);
		}
	}

}
