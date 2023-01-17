<?php
/**
 *
 * Telegram Bridge. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Edgar Bolender
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace eb\telegram\notification\method;

/* Functions used by and injected into all the method decorators. */

trait method_trait
{

    /**
	* {@inheritdoc}
	* We want to inform the users about every new post, such that she can follow the
	* whole conversation on telegram. 
	* Therefore we never return the information about the topics
	* where the user was already informed about.
	*/
	public function get_notified_users($notification_type_id, array $options)
	{
		return array();
	}

}