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

	/** @var array */
	protected $ids_to_fetch;

	/** @var array */
	protected $infos;

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

		$this->ids_to_fetch = $this->infos = array();
		foreach (['forum', 'topic', 'post', 'user'] as $resource)
		{
			$this->ids_to_fetch[$resource]	= array();
			$this->infos[$resource]			= array();
		}

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
		 * [0] the full match
		 * [1] the full opening anchor tag '<a ...>' of the matched link
		 * [2] the linked phpBB SCRIPT FILE - either 'viewforum', 'viewtopic' or 'memberlist'
		 * [3] the PARAMETERS like '?f=123&t=456' or '?p=789'
		 * [4] the TEXT content of the <a> element (unused, this is what we replace)
		 * [5] filled later with link type
		 * [6] filled later with resource ID
		 */
		if (preg_match_all('#(<a\s+?class="postlink-local"\s+?href="' . preg_quote($board_url) . '/(viewforum|viewtopic|memberlist)\.' . $this->php_ext . '([^"]*?)"[^>]*?>)([^<]+?)</a>#si', $text, $matches, PREG_SET_ORDER))
		{
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
							$this->ids_to_fetch['forum'][] = $id[1];
							$matches[$k][5] = LOCALURLTOTEXT_TYPE_FORUM;
							$matches[$k][6] = $id[1];
						}
						break;
					case 'viewtopic':
						if (preg_match('/(?:\?|&|&amp;)p=(\d+)/', $match[3], $id))
						{
							$this->ids_to_fetch['post'][] = $id[1];
							$matches[$k][5] = LOCALURLTOTEXT_TYPE_POST;
							$matches[$k][6] = $id[1];
						}
						// handle link 'viewtopic?...#pxxx' too, see https://github.com/Mar-tin-G/LocalUrlToText/issues/7
						else if (preg_match('/#p(\d+)/', $match[3], $id))
						{
							$this->ids_to_fetch['post'][] = $id[1];
							$matches[$k][5] = LOCALURLTOTEXT_TYPE_POST;
							$matches[$k][6] = $id[1];
						}
						else if (preg_match('/(?:\?|&|&amp;)t=(\d+)/', $match[3], $id))
						{
							$this->ids_to_fetch['topic'][] = $id[1];
							$matches[$k][5] = LOCALURLTOTEXT_TYPE_TOPIC;
							$matches[$k][6] = $id[1];
						}
						break;
					case 'memberlist':
						if (strpos($match[3], 'mode=viewprofile') !== false && preg_match('/(?:\?|&|&amp;)u=(\d+)/', $match[3], $id))
						{
							$this->ids_to_fetch['user'][] = $id[1];
							$matches[$k][5] = LOCALURLTOTEXT_TYPE_USER;
							$matches[$k][6] = $id[1];
						}
						break;
				}
			}

			foreach (['forum', 'topic', 'post', 'user'] as $resource)
			{
				$this->ids_to_fetch[$resource] = array_unique($this->ids_to_fetch[$resource]);
			}

			// first fetch the posts from the DB, since if we're lucky we get all needed topic titles,
			// forum names and user names from one single query
			if (sizeof($this->ids_to_fetch['post']))
			{
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
					'WHERE'			=> $this->db->sql_in_set('p.post_id', $this->ids_to_fetch['post']),
				);
				$this->fetch_from_db($sql_ary);
			}

			// if there are topic IDs left to be fetched, we execute this query next, because
			// perhaps this query fetches also the missing forum names
			if ($this->ids_to_fetch['topic'])
			{
				$sql_ary = array(
					'SELECT'		=> 't.topic_id, t.topic_title, f.forum_id, f.forum_name',
					'FROM'			=> array(TOPICS_TABLE => 't'),
					'LEFT_JOIN'		=> array(
						array(
							'FROM'	=> array(FORUMS_TABLE => 'f'),
							'ON'	=> 't.forum_id = f.forum_id',
						),
					),
					'WHERE'			=> $this->db->sql_in_set('t.topic_id', $this->ids_to_fetch['topic']),
				);
				$this->fetch_from_db($sql_ary);
			}

			// bad luck, still forum IDs left, we have to query a third time...
			if (sizeof($this->ids_to_fetch['forum']))
			{
				$sql_ary = array(
					'SELECT'	=> 'f.forum_id, f.forum_name',
					'FROM'		=> array(FORUMS_TABLE => 'f'),
					'WHERE'		=> $this->db->sql_in_set('f.forum_id', $this->ids_to_fetch['forum']),
				);
				$this->fetch_from_db($sql_ary);
			}

			// ... and a fourth time for the missing user names
			if (sizeof($this->ids_to_fetch['user']))
			{
				$sql_ary = array(
					'SELECT'	=> 'u.user_id, u.username, u.user_colour',
					'FROM'		=> array(USERS_TABLE => 'u'),
					'WHERE'		=> $this->db->sql_in_set('u.user_id', $this->ids_to_fetch['user']),
				);
				$this->fetch_from_db($sql_ary);
			}

			// now that all topic titles, forum names and user names are fetched, we begin
			// and replace the local links with the configured text replacements
			foreach ($matches as $match)
			{
				switch ($match[5])
				{
					case LOCALURLTOTEXT_TYPE_FORUM:
						if (isset($this->infos['forum'][$match[6]]))
						{
							$text = str_replace(
								$match[0],
								$match[1] . str_replace(
									'{FORUM_NAME}',
									$this->infos['forum'][$match[6]]['name'],
									htmlspecialchars_decode($this->config['martin_localurltotext_forum'])
								) . '</a>',
								$text
							);
						}
					break;

					case LOCALURLTOTEXT_TYPE_POST:
						if (isset($this->infos['post'][$match[6]]))
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
										$this->infos['user'][$this->infos['post'][$match[6]]['user_id']]['username'],
										$this->infos['user'][$this->infos['post'][$match[6]]['user_id']]['usercolour'],
										$this->infos['post'][$match[6]]['subject'],
										$this->infos['topic'][$this->infos['post'][$match[6]]['topic_id']]['title'],
										$this->infos['forum'][$this->infos['post'][$match[6]]['forum_id']]['name'],
										($this->infos['post'][$match[6]]['subject'] == '' ? $this->infos['topic'][$this->infos['post'][$match[6]]['topic_id']]['title'] : $this->infos['post'][$match[6]]['subject']),
									),
									htmlspecialchars_decode($this->config['martin_localurltotext_post'])
								) . '</a>',
								$text
							);
						}
					break;

					case LOCALURLTOTEXT_TYPE_TOPIC:
						if (isset($this->infos['topic'][$match[6]]))
						{
							$text = str_replace(
								$match[0],
								$match[1] . str_replace(
									array(
										'{TOPIC_TITLE}',
										'{FORUM_NAME}',
									),
									array(
										$this->infos['topic'][$match[6]]['title'],
										$this->infos['forum'][$this->infos['topic'][$match[6]]['forum_id']]['name'],
									),
									htmlspecialchars_decode($this->config['martin_localurltotext_topic'])
								) . '</a>',
								$text
							);
						}
					break;

					case LOCALURLTOTEXT_TYPE_USER:
						if (isset($this->infos['user'][$match[6]]))
						{
							$text = str_replace(
								$match[0],
								$match[1] . str_replace(
									array(
										'{USER_NAME}',
										'{USER_COLOUR}',
									),
									array(
										$this->infos['user'][$match[6]]['username'],
										$this->infos['user'][$match[6]]['usercolour'],
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

	/**
	* Fetch information about resources from the database.
	* Saves the fetched information to $infos property and removes ids of
	* already fetched resources from $ids_to_fetch property.
	*
	* @param	array		$sql_ary	Valid array for DBAL sql_build_query()
	* @return	null
	* @access	private
	*/
	private function fetch_from_db($sql_ary) {
		$ids_to_remove = array();
		foreach (['forum', 'topic', 'post', 'user'] as $resource)
		{
			$ids_to_remove[$resource] = array();
		}

		$sql = $this->db->sql_build_query('SELECT', $sql_ary);
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			if (isset($row['forum_id']))
			{
				if ($this->auth->acl_get('f_read', $row['forum_id']))
				{
					$this->infos['forum'][$row['forum_id']] = array(
						'name'	=> $row['forum_name'],
					);
				}
				$ids_to_remove['forum'][] = $row['forum_id'];
			}
			if (isset($row['topic_id']))
			{
				if (isset($row['forum_id']) && $this->auth->acl_get('f_read', $row['forum_id']))
				{
					$this->infos['topic'][$row['topic_id']] = array(
						'title'		=> $row['topic_title'],
						'forum_id'	=> $row['forum_id'],
					);
				}
				$ids_to_remove['topic'][] = $row['topic_id'];
			}
			if (isset($row['post_id']))
			{
				if (isset($row['forum_id']) && $this->auth->acl_get('f_read', $row['forum_id']))
				{
					$this->infos['post'][$row['post_id']] = array(
						'user_id'	=> $row['user_id'] ,
						'topic_id'	=> $row['topic_id'],
						'forum_id'	=> $row['forum_id'],
						'subject'	=> $row['post_subject'],
					);
				}
				$ids_to_remove['post'][] = $row['post_id'];
			}
			if (isset($row['user_id']))
			{
				$this->infos['user'][$row['user_id']] = array(
					'username'		=> $row['username'],
					'usercolour'	=> ($row['user_colour'] == '' ? 'inherit' : '#' . $row['user_colour']),
				);
				$ids_to_remove['user'][] = $row['user_id'];
			}
		}
		$this->db->sql_freeresult($result);

		foreach (['forum', 'topic', 'post', 'user'] as $resource)
		{
			$this->ids_to_fetch[$resource] = array_diff($this->ids_to_fetch[$resource], $ids_to_remove[$resource]);
		}
	}
}
