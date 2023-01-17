<?php
/**
 *
 * Telegram Bridge. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Edgar Bolender
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace eb\telegram\notification\type;

/* Used as notification type, when a new post is added to a topic, where the user
	has bookmarked the topic.

	The decorator pattern is implemented here, such that other extensions can also
	extend this service.
*/
class bookmark extends \phpbb\notification\type\bookmark
{
	use type_interface_decorator;
	use type_trait;

}
