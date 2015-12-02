<?php
/**
*
* @package phpBB Extension - martin localurltotext
* @copyright (c) 2015 Martin ( https://github.com/Mar-tin-G )
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace martin\localurltotext\event;

/**
* @ignore
*/
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
	static public function getSubscribedEvents()
	{
		return array(
			'core.modify_text_for_display_after'	=> 'local_url_to_text',
			'core.modify_format_display_text_after'	=> 'local_url_to_text',
		);
	}

	/* @var \phpbb\config\config */
	protected $config;

	/* @var \phpbb\auth */
	protected $auth;

	/* @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var string */
	protected $php_ext;

	/**
	* Constructor
	*
	* @param \phpbb\config\config				$config
	* @param \phpbb\auth\auth					$auth
	* @param \phpbb\db\driver\driver_interface	$db
	* @param string								$php_ext
	*/
	public function __construct(\phpbb\config\config $config, \phpbb\auth\auth $auth, \phpbb\db\driver\driver_interface $db, $php_ext)
	{
		$this->config = $config;
		$this->auth = $auth;
		$this->db = $db;
		$this->php_ext = $php_ext;

		define('LOCALURLTOTEXT_TYPE_FORUM', 1);
		define('LOCALURLTOTEXT_TYPE_TOPIC', 2);
		define('LOCALURLTOTEXT_TYPE_POST', 3);
		define('LOCALURLTOTEXT_TYPE_USER', 4);
	}

	/**
	* Function to replace the text of html links that phpBB automatically parsed
	* (<a class="postlink-local" href="URL">TEXT</a>) with a custom text, made up of
	* configurable placeholders. Works for forum, topic, post and member profile links.
	* NB: only the output to the visitors user agent is altered, the data in the
	* database is unchanged.
	*
	* @param	object		$event	The event object
	* @return	null
	* @access	public
	*/
	public function local_url_to_text($event)
	{
		$text = $event['text'];
		$board_url = generate_board_url();
		$matches = array();

		/*
		 * search for all links on the whole page with one expensive preg_match_all() call.
		 * the regular expression matches:
		 * <a class="postlink-local" href="{YOUR BOARD URL}/{SCRIPT FILE}.{PHP FILE EXTENSION}{PARAMETERS}">{TEXT}</a>
		 *
		 * the $matches array then contains for each single match:
		 * [0] the matched links opening anchor tag '<a ...>'
		 * [1] the TEXT content of the <a> element
		 * [2] the linked phpBB SCRIPT FILE - either 'viewforum', 'viewtopic' or 'memberlist'
		 * [3] the PARAMETERS like '?f=123&t=456' or '?p=789'
		 * [4] (unused)
		 * [5] filled later with link type
		 * [6] filled later with resource ID
		 */
		if (preg_match_all('#(<a\s+?class="postlink-local"\s+?href="' . preg_quote($board_url) . '/(viewforum|viewtopic|memberlist)\.' . $this->php_ext . '([^"]*?)"[^>]*?>)([^<]+?)</a>#si', $text, $matches, PREG_SET_ORDER))
		{
			$forum_ids = $forum_names = $topic_ids = $topic_infos = $post_ids = $post_infos =  $user_ids = $user_infos = array();

			// get all forum, post, topic and user ids that need to by fetched from the DB
			foreach ($matches as $k => $match)
			{
				// we store link type and resource id so we don't need to preg_match() again later
				$matches[$k][5] = 0;
				$matches[$k][6] = 0;
				switch ($match[2])
				{
					case 'viewforum':
						if (preg_match('/(?:\?|&|&amp;)f=(\d+)/', $match[3], $id))
						{
							$forum_ids[] = $id[1];
							$matches[$k][5] = LOCALURLTOTEXT_TYPE_FORUM;
							$matches[$k][6] = $id[1];
						}
						break;
					case 'viewtopic':
						if (preg_match('/(?:\?|&|&amp;)p=(\d+)/', $match[3], $id))
						{
							$post_ids[] = $id[1];
							$matches[$k][5] = LOCALURLTOTEXT_TYPE_POST;
							$matches[$k][6] = $id[1];
						}
						// handle link 'viewtopic?...#pxxx' too, see https://github.com/Mar-tin-G/LocalUrlToText/issues/7
						else if (preg_match('/#p(\d+)/', $match[3], $id))
						{
							$post_ids[] = $id[1];
							$matches[$k][5] = LOCALURLTOTEXT_TYPE_POST;
							$matches[$k][6] = $id[1];
						}
						else if (preg_match('/(?:\?|&|&amp;)t=(\d+)/', $match[3], $id))
						{
							$topic_ids[] = $id[1];
							$matches[$k][5] = LOCALURLTOTEXT_TYPE_TOPIC;
							$matches[$k][6] = $id[1];
						}
						break;
					case 'memberlist':
						if (strpos($match[3], 'mode=viewprofile') !== false && preg_match('/(?:\?|&|&amp;)u=(\d+)/', $match[3], $id))
						{
							$user_ids[] = $id[1];
							$matches[$k][5] = LOCALURLTOTEXT_TYPE_USER;
							$matches[$k][6] = $id[1];
						}
						break;
				}
			}

			$forum_ids = array_unique($forum_ids);
			$topic_ids = array_unique($topic_ids);
			$post_ids = array_unique($post_ids);
			$user_ids = array_unique($user_ids);

			// TODO: possible to get all information in one query? where post_id in (...) OR topic_id in (...) ...
			// first fetch the posts from the DB, since if we're lucky we get all needed topic titles,
			// forum names and user names from one single query
			if (sizeof($post_ids))
			{
				$forum_ids_to_remove = $topic_ids_to_remove = $user_ids_to_remove = array();

				$sql_ary = array(
					'SELECT'		=> 'p.post_id, p.post_subject, t.topic_id, t.topic_title, f.forum_id, f.forum_name, u.user_id, u.username, u.user_colour',
					'FROM'			=> array(POSTS_TABLE => 'p'),
					'LEFT_JOIN'		=> array(
						array(
							'FROM'	=> array(TOPICS_TABLE => 't'),
							'ON'	=> 'p.topic_id = t.topic_id',
						),
						array(
							'FROM'	=> array(FORUMS_TABLE => 'f'),
							'ON'	=> 't.forum_id = f.forum_id',
						),
						array(
							'FROM'	=> array(USERS_TABLE => 'u'),
							'ON'	=> 'p.poster_id = u.user_id',
						),
					),
					'WHERE'			=> $this->db->sql_in_set('p.post_id', $post_ids),
				);
				$sql = $this->db->sql_build_query('SELECT', $sql_ary);
				$result = $this->db->sql_query($sql);
				while ($row = $this->db->sql_fetchrow($result))
				{
					if ($row['topic_id'] != null)
					{
						if ($row['forum_id'] != null && $this->auth->acl_get('f_read', $row['forum_id']))
						{
							$topic_infos[$row['topic_id']] = array(
								'title'		=> $row['topic_title'],
								'forum_id'	=> $row['forum_id'],
							);
						}
						$topic_ids_to_remove[] = $row['topic_id'];
					}
					if ($row['forum_id'] != null)
					{
						if ($this->auth->acl_get('f_read', $row['forum_id']))
						{
							$forum_names[$row['forum_id']] = $row['forum_name'];
						}
						$forum_ids_to_remove[] = $row['forum_id'];
					}
					if ($row['user_id'] != null)
					{
						$user_infos[$row['user_id']] = array(
							'username'		=> $row['username'],
							'usercolour'	=> ($row['user_colour'] == '' ? 'inherit' : '#' . $row['user_colour']),
						);
						$user_ids_to_remove[] = $row['user_id'];
					}
					if ($row['forum_id'] != null && $this->auth->acl_get('f_read', $row['forum_id']))
					{
						$post_infos[$row['post_id']] = array(
							'user_id'	=> $row['user_id'] ,
							'topic_id'	=> $row['topic_id'],
							'forum_id'	=> $row['forum_id'],
							'subject'	=> $row['post_subject'],
						);
					}
				}
				$this->db->sql_freeresult($result);

				$forum_ids = array_diff($forum_ids, $forum_ids_to_remove);
				$topic_ids = array_diff($topic_ids, $topic_ids_to_remove);
				$user_ids = array_diff($user_ids, $user_ids_to_remove);
			}

			// TODO: refactor? seems similar to above code
			// if there are topic IDs left to be fetched, we execute this query next, because
			// perhaps this query fetches also the missing forum names
			if (sizeof($topic_ids))
			{
				$forum_ids_to_remove = array();

				$sql_ary = array(
					'SELECT'		=> 't.topic_id, t.topic_title, f.forum_id, f.forum_name',
					'FROM'			=> array(TOPICS_TABLE => 't'),
					'LEFT_JOIN'		=> array(
						array(
							'FROM'	=> array(FORUMS_TABLE => 'f'),
							'ON'	=> 't.forum_id = f.forum_id',
						),
					),
					'WHERE'			=> $this->db->sql_in_set('t.topic_id', $topic_ids),
				);
				$sql = $this->db->sql_build_query('SELECT', $sql_ary);
				$result = $this->db->sql_query($sql);
				while ($row = $this->db->sql_fetchrow($result))
				{
					if ($row['topic_id'] != null && $row['forum_id'] != null && $this->auth->acl_get('f_read', $row['forum_id']))
					{
						$topic_infos[$row['topic_id']] = array(
							'title'		=> $row['topic_title'],
							'forum_id'	=> $row['forum_id'],
						);
					}
					if ($row['forum_id'] != null)
					{
						if ($this->auth->acl_get('f_read', $row['forum_id']))
						{
							$forum_names[$row['forum_id']] = $row['forum_name'];
						}
						$forum_ids_to_remove[] = $row['forum_id'];
					}
				}
				$this->db->sql_freeresult($result);

				$forum_ids = array_diff($forum_ids, $forum_ids_to_remove);
			}

			// bad luck, still forum IDs left, we have to query a third time...
			if (sizeof($forum_ids))
			{
				$sql_ary = array(
					'SELECT'	=> 'f.forum_id, f.forum_name',
					'FROM'		=> array(FORUMS_TABLE => 'f'),
					'WHERE'		=> $this->db->sql_in_set('f.forum_id', $forum_ids),
				);
				$sql = $this->db->sql_build_query('SELECT', $sql_ary);
				$result = $this->db->sql_query($sql);
				while ($row = $this->db->sql_fetchrow($result))
				{
					if ($row['forum_id'] != null && $this->auth->acl_get('f_read', $row['forum_id']))
					{
						$forum_names[$row['forum_id']] = $row['forum_name'];
					}
				}
				$this->db->sql_freeresult($result);
			}

			// ... and a fourth time for the missing user names
			if (sizeof($user_ids))
			{
				$sql_ary = array(
					'SELECT'	=> 'u.user_id, u.username, u.user_colour',
					'FROM'		=> array(USERS_TABLE => 'u'),
					'WHERE'		=> $this->db->sql_in_set('u.user_id', $user_ids),
				);
				$sql = $this->db->sql_build_query('SELECT', $sql_ary);
				$result = $this->db->sql_query($sql);
				while ($row = $this->db->sql_fetchrow($result))
				{
					if ($row['user_id'] != null)
					{
						$user_infos[$row['user_id']] = array(
							'username'		=> $row['username'],
							'usercolour'	=> ($row['user_colour'] == '' ? 'inherit' : '#' . $row['user_colour']),
						);
					}
				}
				$this->db->sql_freeresult($result);
			}

			// now that all topic titles, forum names and user names are fetched, we begin
			// and replace the local links with the configured text replacements
			foreach ($matches as $match)
			{
				switch ($match[5])
				{
					case LOCALURLTOTEXT_TYPE_FORUM:
						if (isset($forum_names[$match[6]]))
						{
							$text = str_replace(
								$match[0],
								$match[1] . str_replace(
									'{FORUM_NAME}',
									$forum_names[$match[6]],
									htmlspecialchars_decode($this->config['martin_localurltotext_forum'])
								) . '</a>',
								$text
							);
						}
					break;

					case LOCALURLTOTEXT_TYPE_POST:
						if (isset($post_infos[$match[6]]))
						{
							$text = str_replace(
								$match[0],
								$match[1] . str_replace(
									array(
										'{USER_NAME}',
										'{USER_COLOUR}',
										'{POST_SUBJECT}',
										'{TOPIC_TITLE}',
										'{FORUM_NAME}',
										'{POST_OR_TOPIC_TITLE}',
									),
									array(
										$user_infos[$post_infos[$match[6]]['user_id']]['username'],
										$user_infos[$post_infos[$match[6]]['user_id']]['usercolour'],
										$post_infos[$match[6]]['subject'],
										$topic_infos[$post_infos[$match[6]]['topic_id']]['title'],
										$forum_names[$post_infos[$match[6]]['forum_id']],
										($post_infos[$match[6]]['subject'] == '' ? $topic_infos[$post_infos[$match[6]]['topic_id']]['title'] : $post_infos[$match[6]]['subject']),
									),
									htmlspecialchars_decode($this->config['martin_localurltotext_post'])
								) . '</a>',
								$text
							);
						}
					break;

					case LOCALURLTOTEXT_TYPE_TOPIC:
						if (isset($topic_infos[$match[6]]))
						{
							$text = str_replace(
								$match[0],
								$match[1] . str_replace(
									array(
										'{TOPIC_TITLE}',
										'{FORUM_NAME}',
									),
									array(
										$topic_infos[$match[6]]['title'],
										$forum_names[$topic_infos[$match[6]]['forum_id']],
									),
									htmlspecialchars_decode($this->config['martin_localurltotext_topic'])
								) . '</a>',
								$text
							);
						}
					break;

					case LOCALURLTOTEXT_TYPE_USER:
						if (isset($user_infos[$match[6]]))
						{
							$text = str_replace(
								$match[0],
								$match[1] . str_replace(
									array(
										'{USER_NAME}',
										'{USER_COLOUR}',
									),
									array(
										$user_infos[$match[6]]['username'],
										$user_infos[$match[6]]['usercolour'],
									),
									htmlspecialchars_decode($this->config['martin_localurltotext_user'])
								) . '</a>',
								$text
							);
						}
					break;

					default:
						// unknown match type - no replacements
					break;
				}
			}

			$event['text'] = $text;
		}
	}
}
