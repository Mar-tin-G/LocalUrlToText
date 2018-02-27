<?php
/**
*
* @package phpBB Extension - martin localurltotext
* @copyright (c) 2018 Martin ( https://github.com/Mar-tin-G )
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace martin\localurltotext\event;

/**
* @ignore
*/
use phpbb\config\config;
use phpbb\auth\auth;
use phpbb\db\driver\driver_interface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use phpbb\user;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
	static public function getSubscribedEvents()
	{
		return array(
			'core.modify_text_for_display_after'			=> 'modify_post_data',
			'core.modify_format_display_text_after'			=> 'modify_post_data',
			'core.generate_profile_fields_template_data'	=> 'modify_custom_profile_field_data',
		);
	}

	/** @var config */
	protected $config;

	/** @var auth */
	protected $auth;

	/** @var driver_interface */
	protected $db;

	/** @var string */
	protected $php_ext;

	/** @var user */
	protected $user;

	/** @var \phpbb\pages\operators\page */
	protected $page_operator;

	/** @var array */
	protected $ids_to_fetch;

	/** @var array */
	protected $infos;

	/**
	* Constructor
	*
	* @param config							$config
	* @param auth							$auth
	* @param driver_interface				$db
	* @param string							$php_ext
	* @param user							$user
	* @param \phpbb\pages\operators\page	$page_operator
	*/
	public function __construct(config $config, auth $auth, driver_interface $db, $php_ext, $user, \phpbb\pages\operators\page $page_operator = null)
	{
		$this->config = $config;
		$this->auth = $auth;
		$this->db = $db;
		$this->php_ext = $php_ext;
		$this->user = $user;
		$this->page_operator = $page_operator;

		$this->ids_to_fetch = $this->infos = array();
		foreach (array('forum', 'topic', 'post', 'user', 'page') as $resource)
		{
			$this->ids_to_fetch[$resource]	= array();
			$this->infos[$resource]			= array();
		}

		define('LOCALURLTOTEXT_TYPE_FORUM', 1);
		define('LOCALURLTOTEXT_TYPE_TOPIC', 2);
		define('LOCALURLTOTEXT_TYPE_POST', 3);
		define('LOCALURLTOTEXT_TYPE_USER', 4);
		define('LOCALURLTOTEXT_TYPE_PAGE', 5);
	}

	/**
	* Event handler for post modifications
	*
	* @param	object		$event	The event object
	* @return	null
	* @access	public
	*/
	public function modify_post_data($event)
	{
		$event['text'] = $this->local_url_to_text($event['text']);
	}

	/**
	* Event handler for custom profile field modifications
	*
	* @param	object		$event	The event object
	* @return	null
	* @access	public
	*/
	public function modify_custom_profile_field_data($event)
	{
		$profile_data = $event['tpl_fields'];

		if ($this->config['martin_localurltotext_cpf'] && isset($profile_data) && isset($profile_data['blockrow']))
		{
			foreach ($profile_data['blockrow'] as $idx => $row)
			{
				if ($row['PROFILE_FIELD_TYPE'] == 'profilefields.type.url')
				{
					$profile_data['blockrow'][$idx]['PROFILE_FIELD_VALUE'] = $this->local_url_to_text($row['PROFILE_FIELD_VALUE']);
				}
			}
		}

		$event['tpl_fields'] = $profile_data;
	}

	/**
	* Replace the text value of links referencing a local URL
	*
	* Only links that phpBB automatically parsed are handled, denoted by the format
	* <a class="postlink-local" href="URL">TEXT</a>. The replacement text is made up of
	* configurable placeholders. Works for forum, topic, post and member profile links.
	* NB: only the output to the visitors user agent is altered, the data in the
	* database is unchanged.
	*
	* @param	string	$text	The text containing the links to be replaced
	* @return	string
	* @access	private
	*/
	private function local_url_to_text($text)
	{
		$board_url = generate_board_url();
		$matches = array();

		/*
		 * search for all links on the whole page with one expensive preg_match_all() call.
		 * the regular expression matches:
		 * <a ... class="... postlink-local ..." ... href="{YOUR BOARD URL}/{SCRIPT FILE}.{PHP FILE EXTENSION}{PARAMETERS}">{TEXT}</a>
		 *
		 * the $matches array then contains for each single match:
		 * [0]        the full match
		 * ['anchor'] the full opening anchor tag '<a ... >' of the matched link
		 * ['script'] the linked phpBB SCRIPT FILE - either 'viewforum', 'viewtopic' or 'memberlist'
		 *            also, if extension Pages is installed (see https://www.phpbb.com/customise/db/extension/pages/),
		 *            look for 'app.php/page/' (without mod_rewrite) and 'page/' (with mod_rewrite)
		 * ['params'] the PARAMETERS like '?f=123&t=456' or '?p=789'
		 * ['text']   the TEXT content of the <a> element (to skip replacing custom text)
		 * ['type']   filled later with link type
		 * ['id']     filled later with resource ID
		 */

		// construct the regular expression:
		// all characters that are not separators (i.e. that do not end attribute or element)
		$not_sep = '[^"<>]*?';
		// the class attribute must contain 'postlink-local', but there may be other classes appended or prepended
		$class = $not_sep . 'postlink-local'. $not_sep;
		// the href attribute must contain the board url,
		$href =  preg_quote($board_url) .'/'.
			// followed by one of the scripts we are looking for,
			'(?|'.
				// which is either one of the default scripts in the phpBB root,
				// NB: (?P<foo>) returns the match of that group as named subpattern 'foo'
				'(?P<script>viewforum|viewtopic|memberlist)\.'. $this->php_ext .
				'|'.
				// or that is the Pages extension script that may either
				'(?P<script>'.
					// be called as app.php/page/... (when mod_rewrite is disabled)
					'app\.'. $this->php_ext .'/page/'.
					'|'.
					// or as page/... (when mod_rewrite is enabled)
					'page/'.
				')'.
			')'.
			// followed by the parameters
			'(?P<params>'. $not_sep .')';

		// that was hard work - now do some regular expression magic and fetch us some links!
		if (preg_match_all('#'.
			// we need the full opening anchor tag <a ... >
			'(?P<anchor><a'.
				// the attributes are in arbitrary order, separated by whitespace
				'(?:\s+'.
					// the attribute may be one of:
					'(?:'.
						// class: we need to match this
						'class="'. $class .'"'.
						'|'.
						// href= we need to match this too
						'href="'. $href .'"'.
						'|'.
						// other attributes are not of interest to us
						'\w+="'. $not_sep . '"'.
					')'.
				')+'.
			'>)'.
			// we also need the link text <a ...>text</a>
			// NB: do not use $not_sep here as the text may contain the " character
			'(?P<text>[^<]+?)'.
			'</a>#si',
			$text, $matches, PREG_SET_ORDER)
		)
		{
			// get all forum, post, topic and user ids that need to by fetched from the DB
			foreach ($matches as $k => $match)
			{
				/*
				* if the link contains custom text, do not replace it!
				* e.g. a user added an _internal_ link with the [url] bbcode and custom text,
				* like this: [url=http://myforum.com/viewforum.php?f=1]custom text[/url]
				*/
				if ($match['script'] !== '' && strpos($match['text'], $match['script']) === false)
				{
					continue;
				}

				// we store link type and resource id so we don't need to preg_match() again later
				$matches[$k]['type'] = 0;
				$matches[$k]['id'] = 0;
				switch ($match['script'])
				{
					case 'viewforum':
						// after all of the regular expression magic, this one is fairly easy:
						// one of '?', '&' or '&amp' followed by 'f=' and the numerical id we are looking for
						if (preg_match('/(?:\?|&|&amp;)f=(\d+)/', $match['params'], $id))
						{
							$this->ids_to_fetch['forum'][] = $id[1];
							$matches[$k]['type'] = LOCALURLTOTEXT_TYPE_FORUM;
							$matches[$k]['id'] = $id[1];
						}
					break;

					case 'viewtopic':
						if (preg_match('/(?:\?|&|&amp;)p=(\d+)/', $match['params'], $id))
						{
							$this->ids_to_fetch['post'][] = $id[1];
							$matches[$k]['type'] = LOCALURLTOTEXT_TYPE_POST;
							$matches[$k]['id'] = $id[1];
						}
						// handle link 'viewtopic?...#pxxx' too, see https://github.com/Mar-tin-G/LocalUrlToText/issues/7
						else if (preg_match('/#p(\d+)/', $match['params'], $id))
						{
							$this->ids_to_fetch['post'][] = $id[1];
							$matches[$k]['type'] = LOCALURLTOTEXT_TYPE_POST;
							$matches[$k]['id'] = $id[1];
						}
						else if (preg_match('/(?:\?|&|&amp;)t=(\d+)/', $match['params'], $id))
						{
							$this->ids_to_fetch['topic'][] = $id[1];
							$matches[$k]['type'] = LOCALURLTOTEXT_TYPE_TOPIC;
							$matches[$k]['id'] = $id[1];
						}
					break;

					case 'memberlist':
						if (strpos($match['params'], 'mode=viewprofile') !== false && preg_match('/(?:\?|&|&amp;)u=(\d+)/', $match['params'], $id))
						{
							$this->ids_to_fetch['user'][] = $id[1];
							$matches[$k]['type'] = LOCALURLTOTEXT_TYPE_USER;
							$matches[$k]['id'] = $id[1];
						}
					break;

					// for Pages extension
					case 'app.' . $this->php_ext . '/page/':
					case 'page/':
						// page routes may contain: letters, numbers, hyphens and underscores
						if (preg_match('/([a-zA-Z0-9_-]+)/', $match['params'], $id))
						{
							$this->ids_to_fetch['page'][] = $id[1];
							$matches[$k]['type'] = LOCALURLTOTEXT_TYPE_PAGE;
							$matches[$k]['id'] = $id[1];
						}
					break;
				}
			}

			foreach (array('forum', 'topic', 'post', 'user', 'page') as $resource)
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

			// fetch information about pages from Pages extension
			if (sizeof($this->ids_to_fetch['page']) && $this->page_operator)
			{
				$ids_to_remove = array();

				$rowset = $this->page_operator->get_page_links();
				foreach ($rowset as $row)
				{
					// Skip page if it should not be displayed (admins always have access to a page)
					if ((!$row['page_display'] && !$this->auth->acl_get('a_', null)) || (!$row['page_display_to_guests'] && $this->user->data['user_id'] == ANONYMOUS))
					{
						continue;
					}
					$this->infos['page'][$row['page_route']] = array(
						'page_title' => $row['page_title'],
					);
					$ids_to_remove[] = $row['page_route'];
				}
				$this->ids_to_fetch['page'] = array_diff($this->ids_to_fetch['page'], $ids_to_remove);
			}

			// now that all topic titles, forum names and user names are fetched, we begin
			// and replace the local links with the configured text replacements
			foreach ($matches as $match)
			{
				switch ($match['type'])
				{
					case LOCALURLTOTEXT_TYPE_FORUM:
						if (isset($this->infos['forum'][$match['id']]))
						{
							$text = str_replace(
								$match[0],
								$match['anchor'] . str_replace(
									'{FORUM_NAME}',
									$this->infos['forum'][$match['id']]['name'],
									htmlspecialchars_decode($this->config['martin_localurltotext_forum'])
								) . '</a>',
								$text
							);
						}
					break;

					case LOCALURLTOTEXT_TYPE_POST:
						if (isset($this->infos['post'][$match['id']]))
						{
							$text = str_replace(
								$match[0],
								$match['anchor'] . str_replace(
									array(
										'{USER_NAME}',
										'{USER_COLOUR}',
										'{POST_SUBJECT}',
										'{TOPIC_TITLE}',
										'{FORUM_NAME}',
										'{POST_OR_TOPIC_TITLE}',
									),
									array(
										$this->infos['user'][$this->infos['post'][$match['id']]['user_id']]['username'],
										$this->infos['user'][$this->infos['post'][$match['id']]['user_id']]['usercolour'],
										$this->infos['post'][$match['id']]['subject'],
										$this->infos['topic'][$this->infos['post'][$match['id']]['topic_id']]['title'],
										$this->infos['forum'][$this->infos['post'][$match['id']]['forum_id']]['name'],
										($this->infos['post'][$match['id']]['subject'] == '' ? $this->infos['topic'][$this->infos['post'][$match['id']]['topic_id']]['title'] : $this->infos['post'][$match['id']]['subject']),
									),
									htmlspecialchars_decode($this->config['martin_localurltotext_post'])
								) . '</a>',
								$text
							);
						}
					break;

					case LOCALURLTOTEXT_TYPE_TOPIC:
						if (isset($this->infos['topic'][$match['id']]))
						{
							$text = str_replace(
								$match[0],
								$match['anchor'] . str_replace(
									array(
										'{TOPIC_TITLE}',
										'{FORUM_NAME}',
									),
									array(
										$this->infos['topic'][$match['id']]['title'],
										$this->infos['forum'][$this->infos['topic'][$match['id']]['forum_id']]['name'],
									),
									htmlspecialchars_decode($this->config['martin_localurltotext_topic'])
								) . '</a>',
								$text
							);
						}
					break;

					case LOCALURLTOTEXT_TYPE_USER:
						if (isset($this->infos['user'][$match['id']]))
						{
							$text = str_replace(
								$match[0],
								$match['anchor'] . str_replace(
									array(
										'{USER_NAME}',
										'{USER_COLOUR}',
									),
									array(
										$this->infos['user'][$match['id']]['username'],
										$this->infos['user'][$match['id']]['usercolour'],
									),
									htmlspecialchars_decode($this->config['martin_localurltotext_user'])
								) . '</a>',
								$text
							);
						}
					break;

					case LOCALURLTOTEXT_TYPE_PAGE:
						if (isset($this->infos['page'][$match['id']]))
						{
							$text = str_replace(
								$match[0],
								$match['anchor'] . str_replace(
									array(
										'{PAGE_TITLE}',
									),
									array(
										$this->infos['page'][$match['id']]['page_title'],
									),
									htmlspecialchars_decode($this->config['martin_localurltotext_page'])
								) . '</a>',
								$text
							);
						}
					break;
				}
			}
		}

		return $text;
	}

	/**
	* Fetch information about resources from the database
	*
	* Saves the fetched information to $infos property and removes ids of
	* already fetched resources from $ids_to_fetch property.
	*
	* @param	array		$sql_ary	Valid array for DBAL sql_build_query()
	* @return	null
	* @access	private
	*/
	private function fetch_from_db($sql_ary)
	{
		$ids_to_remove = array();
		foreach (array('forum', 'topic', 'post', 'user') as $resource)
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
						'user_id'	=> $row['user_id'],
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

		foreach (array('forum', 'topic', 'post', 'user') as $resource)
		{
			$this->ids_to_fetch[$resource] = array_diff($this->ids_to_fetch[$resource], $ids_to_remove[$resource]);
		}
	}
}
