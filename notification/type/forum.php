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

/* Used as notification type, when a new post is added to a topic in a forum, the user has
   registered for.

   The decorator pattern is implemented here, such that other extensions can also
   extend this service.
*/

class forum extends \phpbb\notification\type\forum
{
	use type_interface_decorator;
	/* "Inject" the methods get_email_template_variables and create_insert_array */
	use type_trait;

}
