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

class telegram_table_v2 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return $this->db_tools->sql_column_exists($this->table_prefix . 'eb_telegram_chat', 'page');
	}

	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v320\v320'];
	}

	/**
	 * Update database schema.
	 *
	 * https://area51.phpbb.com/docs/dev/3.2.x/migrations/schema_changes.html
	 *	add_tables: Add tables
	 *	drop_tables: Drop tables
	 *	add_columns: Add columns to a table
	 *	drop_columns: Removing/Dropping columns
	 *	change_columns: Column changes (only type, not name)
	 *	add_primary_keys: adding primary keys
	 *	add_unique_index: adding an unique index
	 *	add_index: adding an index (can be column:index_size if you need to provide size)
	 *	drop_keys: Dropping keys
	 *
	 * @return array Array of schema changes
	 */
	public function update_schema()
	{
		if ($this->db_tools->sql_table_exists($this->table_prefix . 'eb_telegram_chat'))
		{
			return [
				'add_columns' => [
					$this->table_prefix . 'eb_telegram_chat' => [
						'page'    => ['UINT', 0, 'after' => 'title'],
					],
				],
				'change_columns' => [
					$this->table_prefix . 'eb_telegram_chat' => [
						'title'        => ['VCHAR:120', ''],
					],
				],
			];
		} else
		{
			return [
				'add_tables' => [
					$this->table_prefix . 'eb_telegram_chat' => [
						'COLUMNS'=> [
							'chat_id'    => ['VCHAR:50', ''],
							'message_id' => ['VCHAR:50', ''],
							'forum_id'   => ['VCHAR:50', ''],
							'topic_id'   => ['VCHAR:50', ''],
							'state'      => ['CHAR:1', ''],
							'title'      => ['VCHAR:120', ''],
							'page'       => ['UINT', 0],
						],
						'PRIMARY_KEY' => 'chat_id',
					],
				],
			];
		}
	}

	/**
	 * Revert database schema changes. This method is almost always required
	 * to revert the changes made above by update_schema.
	 *
	 * https://area51.phpbb.com/docs/dev/3.2.x/migrations/schema_changes.html
	 *	add_tables: Add tables
	 *	drop_tables: Drop tables
	 *	add_columns: Add columns to a table
	 *	drop_columns: Removing/Dropping columns
	 *	change_columns: Column changes (only type, not name)
	 *	add_primary_keys: adding primary keys
	 *	add_unique_index: adding an unique index
	 *	add_index: adding an index (can be column:index_size if you need to provide size)
	 *	drop_keys: Dropping keys
	 *
	 * @return array Array of schema changes
	 */
	public function revert_schema()
	{
		return [
			'drop_tables'		=> [
				$this->table_prefix . 'eb_telegram_chat',
			],
		];
	}
}
