<?php
/**
*
* @package phpBB Extension - Martin LocalUrlToText
* @copyright (c) 2014 Martin
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace Martin\LocalUrlToText\event;

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
	protected $bd;

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

		if (preg_match_all('#(<a\s+?class="postlink-local"\s+?href="' . preg_quote($board_url) . '/(viewforum|viewtopic|memberlist)\.' . $this->php_ext . '([^"]*?)"[^>]*?>)([^<]+?)</a>#si', $text, $matches, PREG_SET_ORDER))
		{
			$forum_ids = $forum_names = $topic_ids = $topic_infos = $post_ids = $post_infos =  $user_ids = $user_names = array();

			// get all forum, post, topic and user ids that need to by fetched from the DB
			foreach ($matches as $k => $match)
			{
				// we store type and ID of the link, so we don't need to preg_match() again later
				// [5] stores the type of the link (forum, post, topic, user);
				// [6] stores the ID
				$matches[$k][5] = '';
				$matches[$k][6] = 0;
				switch ($match[2])
				{
					case 'viewforum':
						if (preg_match('/(?:\?|&|&amp;)f=(\d+)/', $match[3], $id))
						{
							// auth: check if the logged in user is allowed to read in this forum
							if ($this->auth->acl_get('f_read', $id[1]))
							{
								$forum_ids[] = $id[1];
								$matches[$k][5] = 'f';
								$matches[$k][6] = $id[1];
							}
						}
						break;
					case 'viewtopic':
						if (preg_match('/(?:\?|&|&amp;)p=(\d+)/', $match[3], $id))
						{
							$post_ids[] = $id[1];
							$matches[$k][5] = 'p';
							$matches[$k][6] = $id[1];
						}
						else if (preg_match('/(?:\?|&|&amp;)t=(\d+)/', $match[3], $id))
						{
							$topic_ids[] = $id[1];
							$matches[$k][5] = 't';
							$matches[$k][6] = $id[1];
						}
						break;
					case 'memberlist':
						if (strpos($match[3], 'mode=viewprofile') !== false && preg_match('/(?:\?|&|&amp;)u=(\d+)/', $match[3], $id))
						{
							$user_ids[] = $id[1];
							$matches[$k][5] = 'u';
							$matches[$k][6] = $id[1];
						}
						break;
				}
			}

			$forum_ids = array_unique($forum_ids);
			$topic_ids = array_unique($topic_ids);
			$post_ids = array_unique($post_ids);
			$user_ids = array_unique($user_ids);

			// first fetch the posts from the DB, since if we're lucky we get all needed topic titles,
			// forum names and user names from one single query
			if (count($post_ids))
			{
				$forum_ids_to_remove = array();
				$topic_ids_to_remove = array();
				$user_ids_to_remove = array();

				$sql_ary = array(
					'SELECT'		=> 'p.post_id, p.post_subject, t.topic_id, t.topic_title, f.forum_id, f.forum_name, u.user_id, u.username',
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
								'forum_id'	=> $row['forum_id']
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
						$user_names[$row['user_id']] = $row['username'];
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

			// if there are topic IDs left to be fetched, we execute this query next, because
			// perhaps this query fetches also the missing forum names
			if (count($topic_ids))
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
							'forum_id'	=> $row['forum_id']
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
			if (count($forum_ids))
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
					if ($row['forum_id'] != null)
					{
						$forum_names[$row['forum_id']] = $row['forum_name'];
					}
				}
				$this->db->sql_freeresult($result);
			}

			// ... and a fourth time for the missing user names
			if (count($user_ids))
			{
				$sql_ary = array(
					'SELECT'	=> 'u.user_id, u.username',
					'FROM'		=> array(USERS_TABLE => 'u'),
					'WHERE'		=> $this->db->sql_in_set('u.user_id', $user_ids),
				);
				$sql = $this->db->sql_build_query('SELECT', $sql_ary);
				$result = $this->db->sql_query($sql);
				while ($row = $this->db->sql_fetchrow($result))
				{
					if ($row['user_id'] != null)
					{
						$user_names[$row['user_id']] = $row['username'];
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
					case 'f':
						if (isset($forum_names[$match[6]]))
						{
							$text = str_replace($match[0], $match[1] . str_replace('{FORUM_NAME}', $forum_names[$match[6]], htmlspecialchars_decode($this->config['Martin_LocalUrlToText_forum'])) . '</a>', $text);
						}
						break;
					case 'p':
						if (isset($post_infos[$match[6]]))
						{
							$text = str_replace($match[0], $match[1] . str_replace(
								array('{USER_NAME}', '{POST_SUBJECT}', '{TOPIC_TITLE}', '{FORUM_NAME}', '{POST_OR_TOPIC_TITLE}'),
								array($user_names[$post_infos[$match[6]]['user_id']], $post_infos[$match[6]]['subject'], $topic_infos[$post_infos[$match[6]]['topic_id']]['title'], $forum_names[$post_infos[$match[6]]['forum_id']], ($post_infos[$match[6]]['subject'] == '' ? $topic_infos[$post_infos[$match[6]]['topic_id']]['title'] : $post_infos[$match[6]]['subject'])),
								htmlspecialchars_decode($this->config['Martin_LocalUrlToText_post'])
							) . '</a>', $text);
						}
						break;
					case 't':
						if (isset($topic_infos[$match[6]]))
						{
							$text = str_replace($match[0], $match[1] . str_replace(
								array('{TOPIC_TITLE}', '{FORUM_NAME}'),
								array($topic_infos[$match[6]]['title'], $forum_names[$topic_infos[$match[6]]['forum_id']]),
								htmlspecialchars_decode($this->config['Martin_LocalUrlToText_topic'])
							) . '</a>', $text);
							break;
						}
					case 'u':
						if (isset($user_names[$match[6]]))
						{
							$text = str_replace($match[0], $match[1] . str_replace('{USER_NAME}', $user_names[$match[6]], htmlspecialchars_decode($this->config['Martin_LocalUrlToText_user'])) . '</a>', $text);
							break;
						}
				}
			}

			$event['text'] = $text;
		}
	}
}
