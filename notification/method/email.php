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

/**
* Overwrites notification method email. (No decorator!)
*
* This class just avoids that telegram messages are only sent,
* if posts of previous notifications have been read.
*/
class email extends \phpbb\notification\method\email
{
	use method_trait;
}
