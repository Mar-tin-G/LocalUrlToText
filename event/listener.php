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
use martin\localurltotext\constants;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
	static public function getSubscribedEvents()
	{
		return array(
			'core.text_formatter_s9e_render_before'			=> 'modify_post_data',
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

	/** @var string */
	protected $local_url_regexp;

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

		$this->local_url_regexp = $this->create_local_url_regexp();
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
		$xml = $event['xml'];

		$dom = new \DOMDocument;
		$dom->loadXML($xml);

		// query via XPath for <URL> containing <LINK_TEXT>
		$xpath = new \DOMXPath($dom);
		foreach ($xpath->query('//URL/LINK_TEXT') as $link_text)
		{
			// check content of <LINK_TEXT> (not "text" attribute) for match
			$this->local_url_to_text($link_text->textContent, function($text) use ($link_text) {
				// if there is a match, set the "text" attribute
				$link_text->setAttribute('text', $text);
			});
		}

		$event['xml'] = $dom->saveXML($dom->documentElement);
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
				// there is some special handling of contact profile fields, which do not have an "<a class='postlink-local'>...</a>"
				// in the $row['PROFILE_FIELD_VALUE'], but the raw URL value, the same as in $row['PROFILE_FIELD_VALUE_RAW'];
				// skip replacing the link in this case
				if ($row['PROFILE_FIELD_TYPE'] == 'profilefields.type.url' && strpos($row['PROFILE_FIELD_VALUE'], 'postlink-local') !== false)
				{
					// if there is a match, create postlink with the given text
					$this->local_url_to_text($row['PROFILE_FIELD_VALUE'], function($text) use (&$profile_data, $idx) {
						$profile_data['blockrow'][$idx]['PROFILE_FIELD_VALUE'] = '<!-- l --><a class="postlink-local" href="'.
							$profile_data['blockrow'][$idx]['PROFILE_FIELD_VALUE_RAW'] .'">'.
							$text .'</a><!-- l -->';
					});
				}
			}
		}

		$event['tpl_fields'] = $profile_data;
	}

	/**
	* Check if the given text contains a local URL, execute callback with replacement text
	*
	* @param	string		$text		The text to be checked for local URLs
	* @param	function	$callback	Called if the given text contains a local URL
	* @return	null
	* @access	private
	*/
	private function local_url_to_text($text, $callback)
	{
		if (preg_match('#'. $this->local_url_regexp .'#si', $text, $match))
		{
			switch ($match['script'])
			{
				case 'viewforum':
					// check for forum link: one of '?', '&' or '&amp' followed by 'f=' and the numerical forum id
					if (preg_match('/(?:\?|&|&amp;)f=(\d+)/', $match['params'], $id))
					{
						$forum_link_text = $this->get_forum_link_text($id[1]);

						if (isset($forum_link_text))
						{
							$callback($forum_link_text);
						}
					}
				break;

				case 'viewtopic':
					// check for post link: one of '?', '&' or '&amp' followed by 'p=' and the numerical post id
					if (preg_match('/(?:\?|&|&amp;)p=(\d+)/', $match['params'], $id))
					{
						$post_link_text = $this->get_post_link_text($id[1]);

						if (isset($post_link_text))
						{
							$callback($post_link_text);
						}
					}
					// handle link 'viewtopic?...#pxxx' too, see https://github.com/Mar-tin-G/LocalUrlToText/issues/7
					else if (preg_match('/#p(\d+)/', $match['params'], $id))
					{
						$post_link_text = $this->get_post_link_text($id[1]);

						if (isset($post_link_text))
						{
							$callback($post_link_text);
						}
					}
					// check for topic link: one of '?', '&' or '&amp' followed by 't=' and the numerical topic id
					else if (preg_match('/(?:\?|&|&amp;)t=(\d+)/', $match['params'], $id))
					{
						$topic_link_text = $this->get_topic_link_text($id[1]);

						if (isset($topic_link_text))
						{
							$callback($topic_link_text);
						}
					}
				break;

				case 'memberlist':
					// check for user link: one of '?', '&' or '&amp' followed by 'u=' and the numerical user id
					if (strpos($match['params'], 'mode=viewprofile') !== false && preg_match('/(?:\?|&|&amp;)u=(\d+)/', $match['params'], $id))
					{
						$user_link_text = $this->get_user_link_text($id[1]);

						if (isset($user_link_text))
						{
							$callback($user_link_text);
						}
					}
				break;

				// for Pages extension: check if the given route is a vaid page
				default:
					if ($this->page_operator)
					{
						$page_route = str_replace('app.' . $this->php_ext . '/', '', $match['script']);

						$page_link_text = $this->get_page_link_text($page_route);

						if (isset($page_link_text))
						{
							$callback($page_link_text);
						}
					}
				break;
			}
		}
	}

	/**
	* Fetch forum information from database and create replacement text
	*
	* Checks that the logged in user is authorized to view the given forum.
	* Applies the replacement template set in the ACP.
	*
	* @return	string|null
	* @access	private
	*/
	private function get_forum_link_text($forum_id)
	{
		$sql_ary = array(
			'SELECT'	=> 'f.forum_id, f.forum_name',
			'FROM'		=> array(FORUMS_TABLE => 'f'),
			'WHERE'		=> 'f.forum_id = ' . $this->db->sql_escape($forum_id),
		);

		$sql	= $this->db->sql_build_query('SELECT', $sql_ary);
		$result	= $this->db->sql_query($sql);
		$row	= $this->db->sql_fetchrow($result);

		$forum_name = isset($row['forum_id']) && $this->auth->acl_get('f_read', $row['forum_id']) ? $row['forum_name'] : null;

		$this->db->sql_freeresult($result);

		if (isset($forum_name))
		{
			return str_replace(
				'{FORUM_NAME}',
				$forum_name,
				htmlspecialchars_decode($this->config['martin_localurltotext_forum'])
			);
		}

		return null;
	}

	/**
	* Fetch post information from database and create replacement text
	*
	* Checks that the logged in user is authorized to view the forum the given post is in.
	* Applies the replacement template set in the ACP.
	*
	* @return	string|null
	* @access	private
	*/
	private function get_post_link_text($post_id)
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
			'WHERE'			=> 'p.post_id = ' . $this->db->sql_escape($post_id),
		);

		$sql	= $this->db->sql_build_query('SELECT', $sql_ary);
		$result	= $this->db->sql_query($sql);
		$row	= $this->db->sql_fetchrow($result);

		$post_subject	= isset($row['post_id']) && isset($row['forum_id']) && $this->auth->acl_get('f_read', $row['forum_id']) ? $row['post_subject'] : null;
		$topic_title	= isset($row['topic_id']) && isset($row['forum_id']) && $this->auth->acl_get('f_read', $row['forum_id']) ? $row['topic_title'] : null;
		$forum_name		= isset($row['forum_id']) && $this->auth->acl_get('f_read', $row['forum_id']) ? $row['forum_name'] : null;
		$user_name		= isset($row['user_id']) ? $row['username'] : null;
		$user_colour	= isset($row['user_id']) ? ($row['user_colour'] == '' ? 'inherit' : '#' . $row['user_colour']) : null;

		$this->db->sql_freeresult($result);

		if (isset($post_subject))
		{
			return str_replace(
				array(
					'{POST_SUBJECT}',
					'{TOPIC_TITLE}',
					'{FORUM_NAME}',
					'{USER_NAME}',
					'{USER_COLOUR}',
					'{POST_OR_TOPIC_TITLE}',
				),
				array(
					$post_subject,
					$topic_title,
					$forum_name,
					$user_name,
					$user_colour,
					$post_subject !== '' ? $post_subject : $topic_title,
				),
				htmlspecialchars_decode($this->config['martin_localurltotext_post'])
			);
		}

		return null;
	}

	/**
	* Fetch topic information from database and create replacement text
	*
	* Checks that the logged in user is authorized to view the forum the given topic is in.
	* Applies the replacement template set in the ACP.
	*
	* @return	string|null
	* @access	private
	*/
	private function get_topic_link_text($topic_id)
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
			'WHERE'			=> 't.topic_id = ' . $this->db->sql_escape($topic_id),
		);

		$sql	= $this->db->sql_build_query('SELECT', $sql_ary);
		$result	= $this->db->sql_query($sql);
		$row	= $this->db->sql_fetchrow($result);

		$topic_title	= isset($row['topic_id']) && isset($row['forum_id']) && $this->auth->acl_get('f_read', $row['forum_id']) ? $row['topic_title'] : null;
		$forum_name		= isset($row['forum_id']) && $this->auth->acl_get('f_read', $row['forum_id']) ? $row['forum_name'] : null;

		$this->db->sql_freeresult($result);

		if (isset($topic_title))
		{
			return str_replace(
				array(
					'{TOPIC_TITLE}',
					'{FORUM_NAME}',
				),
				array(
					$topic_title,
					$forum_name,
				),
				htmlspecialchars_decode($this->config['martin_localurltotext_topic'])
			);
		}

		return null;
	}

	/**
	* Fetch user information from database and create replacement text
	*
	* Applies the replacement template set in the ACP.
	*
	* @return	string|null
	* @access	private
	*/
	private function get_user_link_text($user_id)
	{
		$sql_ary = array(
			'SELECT'	=> 'u.user_id, u.username, u.user_colour',
			'FROM'		=> array(USERS_TABLE => 'u'),
			'WHERE'		=> 'u.user_id = ' . $this->db->sql_escape($user_id),
		);

		$sql	= $this->db->sql_build_query('SELECT', $sql_ary);
		$result	= $this->db->sql_query($sql);
		$row	= $this->db->sql_fetchrow($result);

		$user_name		= isset($row['user_id']) ? $row['username'] : null;
		$user_colour	= isset($row['user_id']) ? ($row['user_colour'] == '' ? 'inherit' : '#' . $row['user_colour']) : null;

		$this->db->sql_freeresult($result);

		if (isset($user_name))
		{
			return str_replace(
				array(
					'{USER_NAME}',
					'{USER_COLOUR}',
				),
				array(
					$user_name,
					$user_colour,
				),
				htmlspecialchars_decode($this->config['martin_localurltotext_user'])
			);
		}

		return null;
	}

	/**
	* Fetch page information from page operator and create replacement text
	*
	* Checks that the logged in user is authorized to view the given page.
	* Applies the replacement template set in the ACP.
	*
	* @return	string|null
	* @access	private
	*/
	private function get_page_link_text($page_route)
	{
		if ($this->page_operator)
		{
			$pages = $this->page_operator->get_page_links();

			foreach ($pages as $page)
			{
				if ($page['page_route'] !== $page_route)
				{
					continue;
				}

				// Skip page if it should not be displayed (admins always have access to a page)
				if ((!$page['page_display'] && !$this->auth->acl_get('a_', null)) || (!$page['page_display_to_guests'] && $this->user->data['user_id'] == ANONYMOUS))
				{
					continue;
				}

				return str_replace(
					'{PAGE_TITLE}',
					$page['page_title'],
					htmlspecialchars_decode($this->config['martin_localurltotext_page'])
				);
			}
		}

		return null;
	}

	/**
	* Create a regular expression to match for local URLs
	*
	* @return	string
	* @access	private
	*/
	private function create_local_url_regexp()
	{
		$board_url = $this->board_url_to_regexp(generate_board_url());

		// create a regular expression from all available page routes if the Pages extension is active;
		// the expression looks like this:
		// (?P<script>app\.php/page-route-1|page-route-1|app\.php/page-route-2|page-route-2)
		if ($this->page_operator)
		{
			$pages = $this->page_operator->get_page_links();

			$pages_regexp = '(?P<script>';

			$build_route_regexp = function($page)
			{
				return 'app\.' . $this->php_ext . '/' . $page['page_route'] . '|' . $page['page_route'];
			};

			$pages_regexp .= implode(array_map($build_route_regexp, $pages), '|');
			$pages_regexp .= ')';
		}

		// the local URL must contain the board url,
		return $board_url .'/'.
			// followed by one of the scripts we are looking for,
			'(?|'.
				// which is either one of the default scripts in the phpBB root,
				// NB: (?P<foo>) returns the match of that group as named subpattern 'foo'
				'(?P<script>viewforum|viewtopic|memberlist)\.'. $this->php_ext .
				// or a page from the Pages extension,
				($this->page_operator ? '|'. $pages_regexp : '') .
			')'.
			// followed by the parameters
			'(?P<params>.*)';
	}

	/**
	* Create regular expression from given URL, including common URL protocol
	* schemes "http:" and "https:"
	*
	* @param	string		$url	URL to be converted to regular expression
	* @return	string				URL in regular epxression format with common schemes
	* @access	private
	*/
	private function board_url_to_regexp($url)
	{
		$url_without_scheme = preg_replace('/^[a-z0-9.-]+:/', '', $url);
		return 'https?\:' . preg_quote($url_without_scheme);
	}
}
