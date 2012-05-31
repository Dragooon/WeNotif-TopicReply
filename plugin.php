<?php
/**
 * WeNotif-TopicReply's main plugin file
 * 
 * @package Dragooon:WeNotif-TopicReply
 * @author Shitiz "Dragooon" Garg <Email mail@dragooon.net> <Url http://smf-media.com>
 * @copyright 2012, Shitiz "Dragooon" Garg <mail@dragooon.net>
 * @license
 *		Licensed under "New BSD License (3-clause version)"
 *		http://www.opensource.org/licenses/BSD-3-Clause
 * @version 1.0
 */

if (!defined('WEDGE'))
	die('File cannot be requested directly');

/**
 * Callback for the hook, "notification_callback", registers this as a verified notifier for notifications
 *
 * @param array &$notifiers
 * @return void
 */
function wenotif_topicreply_callback(array &$notifiers)
{
	$notifiers['topicreply'] = new TopicReplyNotifier();
}

/**
 * Callback for the hook, "create_post_after", actually issues the notification
 *
 * @param array $msgOptions
 * @param array $topicOptions
 * @param array $posterOptions
 * @param bool $new_topic
 * @return void
 */
function wenotif_topicreply_create_post_after(&$msgOptions, &$topicOptions, &$posterOptions, &$new_topic)
{
	// Don't bother with new topics
	if ($new_topic)
		return;
	
	// Load some basic topic info
	$request = wesql::query('
		SELECT t.id_topic, t.id_member_started, m.subject
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
		WHERE t.id_topic = {int:topic}
		LIMIT 1', array(
			'topic' => $topicOptions['id'],
		)
	);
	list ($id_topic, $id_member, $subject) = wesql::fetch_row($request);
	wesql::free_result($request);

	// If this poster's the starter or the topic is started by a guest, don't bother
	if (empty($id_member) || $posterOptions['id'] == $id_member)
		return;
	
	// Issue the notification
	Notification::issue($id_member, WeNotif::getNotifiers('topicreply'), $id_topic,
							array('subject' => $subject, 'id_msg' => $msgOptions['id'], 'members' => array($posterOptions['name']), 'num' => 1));
}

/**
 * Hook callback for "displaay_main", doesn't do much except mark the topic's notifications as read
 *
 * @return void
 */
function wenotif_topicreply_display_main()
{
	global $topic, $user_info;

	Notification::markReadForNotifier($user_info['id'], WeNotif::getNotifiers('topicreply'), $topic);
}

class TopicReplyNotifier implements Notifier
{
	/**
	 * Constructor, loads the language file for some strings we use
	 *
	 * @access public
	 * @return void
	 */
	public function __construct()
	{
		loadPluginLanguage('Dragooon:WeNotif-TopicReply', 'plugin');
	}

	/**
	 * Callback for getting the URL of the object
	 *
	 * @access public
	 * @param Notification $notification
	 * @return string A fully qualified HTTP URL
	 */
	public function getURL(Notification $notification)
	{
		global $scripturl;

		$data = $notification->getData();

		return $scripturl . '?topic=' . $notification->getObject() . '.msg' . $data['id_msg'] . '#msg' . $data['id_msg'];
	}

	/**
	 * Callback for getting the text to display on the notification screen
	 *
	 * @access public
	 * @param Notification $notification
	 * @return string The text this notification wants to display
	 */
	public function getText(Notification $notification)
	{
		global $txt;

		$data = $notification->getData();

		// Only one member?
		if ($data['num'] == 1)
			return sprintf($txt['notification_topicreply'], $data['members'][0], shorten_subject($data['subject'], 25));
		else
			return sprintf($txt['notification_topicreply_multiple'], $data['members'][0], count(array_unique($data['members'])) - 1,
							$data['num'], shorten_subject($data['subject'], 20));
	}

	/**
	 * Returns the name of this notifier
	 *
	 * @access public
	 * @return string
	 */
	public function getName()
	{
		return 'topicreply';
	}

	/**
	 * Callback for handling multiple notifications on the same object
	 * We just bump the old notification
	 *
	 * @access public
	 * @param Notification $notification
	 * @param array &$data Reference to the new notification's data, if something needs to be altered
	 * @return bool, if false then a new notification is not created but the current one's time is updated
	 */
	public function handleMultiple(Notification $notification, array &$data)
	{
		$existing_data = $notification->getData();
		$existing_data['members'][] = $data['members'][0];
		$existing_data['num']++;
		$notification->updateData($existing_data);

		return false;
	}

	/**
	 * Returns the elements for notification's profile area
	 *
	 * @access public
	 * @param int $id_member The ID of the member whose profile is currently being accessed
	 * @return array(title, description, config_vars)
	 */
	public function getProfile($id_member)
	{
		global $txt;

		return array($txt['notification_topicreply_profile'], $txt['notification_topicreply_profile_desc'], array());
	}

	/**
	 * Callback for profile area, called when saving the profile area
	 *
	 * @access public
	 * @param int $id_member The ID of the member whose profile is currently being accessed
	 * @param array $settings A key => value pair of the fed settings
	 * @return void
	 */
	public function saveProfile($id_member, array $settings)
	{
		// We do nothing, we save nothing...
	}
}