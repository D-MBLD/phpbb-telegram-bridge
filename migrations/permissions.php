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

class permissions extends \phpbb\db\migration\migration
{
	public function update_data()
	{
		return [
			['permission.add', ['u_ebt_notify']], // May be notified via TG
			['permission.add', ['u_ebt_browse']], // Can browse forum via TG
			['permission.add', ['u_ebt_post']], // Can post via TG (Note: There is also f_reply !!!)
			['permission.permission_set', ['REGISTERED', 'u_ebt_notify', 'group']],
			['permission.permission_set', ['REGISTERED', 'u_ebt_browse', 'group']],
			['permission.permission_set', ['REGISTERED', 'u_ebt_post', 'group']],
			['permission.permission_set', ['ROLE_USER_STANDARD', 'u_ebt_notify']],
			['permission.permission_set', ['ROLE_USER_STANDARD', 'u_ebt_browse']],
			['permission.permission_set', ['ROLE_USER_STANDARD', 'u_ebt_post']],
	   ];
	}

	public function revert_data()
	{
		return [
			['permission.remove', ['u_ebt_notify']], // May be notified via TG
			['permission.remove', ['u_ebt_browse']], // Can browse forum via TG
			['permission.remove', ['u_ebt_post']], // Can post via TG
			['permission.remove', ['u_new']], // New global user permission
	   ];
	}
}
