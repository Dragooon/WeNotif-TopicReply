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
function wenotif_create_post_after(&$msgOptions, &$topicOptions, &$posterOptions, &$new_topic)
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
	$notifiers = WeNotif::getNotifiers();
	Notification::issue($id_member, $notifiers['topicreply'], $id_topic,
							array('subject' => $subject, 'id_msg' => $msgOptions['id'], 'member' => $posterOptions['name']));
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

		return $scripturl . '?action=topic=' . $notification->getObject() . '.msg' . $data['id_msg'] . '#msg' . $data['id_msg'];
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

		return sprintf($txt['notification_topicreply'], $data['member'], shorten_subject($data['subject'], 25));
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
}