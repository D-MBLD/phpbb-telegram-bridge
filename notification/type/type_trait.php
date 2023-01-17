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

/* Functions used by and injected into all the type decorators. */

trait type_trait
{
	private static $POST = 1;
	private static $TOPIC = 2;

	/** For Telegram the template may be different from the email template.
	 *  Therefore we provide a separate method used by notification/method/telegram.
	*/
	public function get_telegram_template()
	{
		if ($this->is_post_or_topic() == static::$POST)
		{
			return '@eb_telegram/forum_notify';
		} else if ($this->is_post_or_topic() == static::$TOPIC)
		{
			return '@eb_telegram/newtopic_notify';
		} else
		{
			return $this->inner->get_email_template();
		}
	}

	/**
	 * Get email template variables.
	 *
	 * phpBB core already defines some default variables:
	 * USERNAME
	 * U_NOTIFICATION_SETTINGS
	 * EMAIL_SIG
	 * SITENAME
	 * U_BOARD
	 *
	 * @return array  Array of variables that can be used in the email template
	 */
	public function get_email_template_variables()
	{
		$template_vars = $this->inner->get_email_template_variables();
		if ($this->is_post_or_topic())
		{
			$post_body = utf8_decode_ncr(censor_text($this->inner->get_data('telegram_body')));
			$template_vars['TELEGRAM_MESSAGE'] = htmlspecialchars_decode($post_body);
			$template_vars['NOTIFICATION_TYPE'] = get_class($this) . ' decorates ' . get_class($this->inner);

			$template_vars['FORUM_NAME'] = $this->inner->get_data('forum_name');
			$template_vars['POSTER_ID'] = $this->inner->get_data('poster_id');
			if ($this->is_post_or_topic() == static::$TOPIC)
			{
				//new topic
				$template_vars['FORUM_ID'] = $this->inner->item_parent_id;
				$template_vars['TOPIC_ID'] = $this->inner->item_id;
			} else
			{
				//New post
				$template_vars['FORUM_ID'] = $this->inner->get_data('forum_id');
				$template_vars['TOPIC_ID'] = $this->inner->item_parent_id;
				$template_vars['POST_ID'] = $this->inner->item_id;
			}
		}

		return $template_vars;
	}

	/**
	 * Function for preparing the data for insertion in an SQL query.
	 * (The service handles insertion)
	 *
	 * This function is responsible for inserting data specific to this notification type.
	 * This data will be stored in the database and used when displaying the notification.
	 * The $data parameter will contain the array that is being sent when Sending a Notification.
	 * So, all data that is needed for creating and displaying the notification has to be included.
	 *
	 * @param array  $data             The type specific data
	 * @param array  $pre_create_data  Data from pre_create_insert_array()
	 * @return void
	 */
	public function create_insert_array($data, $pre_create_data = [])
	{
		/** Note: Unfortunately the manager sets directly the property user_id of the notification type,
		 * without using an interface method, before calling create_insert_array. (around line 407)
		 * This means, that the user_id is not set in the inner-object, and thus the following
		 * call of array_merge cannot fill the user_id.
		 * Therefore we need to copy the value here.
		 */
		$this->inner->user_id = $this->user_id;

		// Set the message so it can be retrieved via get_data() in get_email_template_variables()
		$this->inner->set_data('telegram_body', $data['message']);

		return $this->inner->create_insert_array($data, $pre_create_data);
	}

	private function is_post_or_topic()
	{
		$type = $this->inner->get_type();
		if ($type == 'notification.type.bookmark' ||
			$type == 'notification.type.post' ||
			$type == 'notification.type.forum' )
		{
			return static::$POST;
		} else if ($type == 'notification.type.topic')
		{
			return static::$TOPIC;
		} else
		{
			return 0;
		}
	}

}
