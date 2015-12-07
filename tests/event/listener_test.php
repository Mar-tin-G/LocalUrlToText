<?php
/**
*
* @package phpBB Extension - martin localurltotext
* @copyright (c) 2015 Martin ( https://github.com/Mar-tin-G )
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace martin\localurltotext\tests\event;

require_once dirname(__FILE__) . '/../../../../../includes/functions.php';

class listener_test extends \phpbb_database_test_case
{
	/** @var \martin\localurltotext\event\listener */
	protected $listener;

	protected $config, $auth, $db, $php_ext, $board_url;
	protected $auth_acl_map_admin, $auth_acl_map_user;

	/**
	* Define the extensions to be tested
	*
	* @return array vendor/name of extension(s) to test
	*/
	static protected function setup_extensions()
	{
		return array('martin/localurltotext');
	}

	/**
	* Setup test environment
	*/
	public function setUp()
	{
		parent::setUp();

		global $phpEx;

		$this->config = new \phpbb\config\config(array(
			'martin_localurltotext_forum'	=> 'f: <i>{FORUM_NAME}</i>',
			'martin_localurltotext_topic'	=> 't: {TOPIC_TITLE}, f: {FORUM_NAME}',
			'martin_localurltotext_post'	=> 'p: {POST_SUBJECT}, t: {TOPIC_TITLE}, pt: {POST_OR_TOPIC_TITLE}, f: {FORUM_NAME}, u: {USER_NAME}, uc: {USER_COLOUR}',
			'martin_localurltotext_user'	=> 'u: {USER_NAME}, uc: {USER_COLOUR}',
		));

		$this->auth_acl_map_user = array(
			array('f_read', 1, true),
			array('f_read', 42, false),
		);
		$this->auth_acl_map_admin = array(
			array('f_read', 1, true),
			array('f_read', 42, true),
		);

		$this->create_auth();

		$this->db = $this->new_dbal();
		$this->php_ext = $phpEx;

		$this->generate_board_url();
	}

	/**
	* Get an instance of the event listener to test
	*/
	protected function set_listener()
	{
		$this->listener = new \martin\localurltotext\event\listener(
			$this->config,
			$this->auth,
			$this->db,
			$this->php_ext
		);
	}

	/**
	* Create auth object for tests. Needed to test different auth maps within one test.
	*/
	protected function create_auth()
	{
		$this->auth = $this->getMockBuilder('\phpbb\auth\auth')
			->disableOriginalConstructor()
			->getMock();
	}

	/**
	* Setup auth object with auth map
	*/
	protected function set_auth($map)
	{
		$this->auth->expects($this->any())
			->method('acl_get')
			->with($this->stringContains('_'),
				$this->anything())
			->will($this->returnValueMap($map));
	}

	/**
	* Helper function for tests
	*/
	protected function generate_board_url()
	{
		global $user, $request;

		// needed for generate_board_url() call.
		$user = new \phpbb_mock_user();
		$request = new \phpbb_mock_request();

		$this->board_url = generate_board_url();
	}

	/**
	* Get test data from fixtures
	*/
	public function getDataSet()
	{
		return $this->createXMLDataSet(dirname(__FILE__) . '/fixtures/resources.xml');
	}

	/**
	* Test the event listener is constructed correctly
	*/
	public function test_construct()
	{
		$this->set_listener();
		$this->assertInstanceOf('\Symfony\Component\EventDispatcher\EventSubscriberInterface', $this->listener);
	}

	/**
	* Test the event listener is subscribing events
	*/
	public function test_getSubscribedEvents()
	{
		$this->assertEquals(array(
			'core.modify_text_for_display_after',
			'core.modify_format_display_text_after',
		), array_keys(\martin\localurltotext\event\listener::getSubscribedEvents()));
	}

	/**
	* Data set for test_local_url_to_text
	*
	* @return array Array of test data
	*/
	public function local_url_to_text_data()
	{
		global $phpEx;

		$this->generate_board_url();

		/*
		first value: expected sql_query() count
		second value: original text
		third value - if specified: expected text for users; if not specified: expected text for users = original text
		fourth value - if specified: expected text for admins; if not specified: expected text for admins = expected text for users
		*/
		return array(
			'no local urls' => array(
				0,
				'blah blah',
			),
			'forum url' => array(
				1,
				'<a class="postlink-local" href="'. $this->board_url .'/viewforum.'. $phpEx .'?f=1">some text</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewforum.'. $phpEx .'?f=1">f: <i>First forum</i></a>',
			),
			'admin forum url' => array(
				1,
				'<a class="postlink-local" href="'. $this->board_url .'/viewforum.'. $phpEx .'?x=y&f=42">some text</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewforum.'. $phpEx .'?x=y&f=42">some text</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewforum.'. $phpEx .'?x=y&f=42">f: <i>Admin forum</i></a>',
			),
			'topic url' => array(
				1,
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?f=1&amp;t=1">some text</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?f=1&amp;t=1">t: Topic 1 title, f: First forum</a>',
			),
			'admin topic url' => array(
				1,
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?t=42">some text</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?t=42">some text</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?t=42">t: Admin topic title, f: Admin forum</a>',
			),
			'post url' => array(
				1,
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?p=1">some text</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?p=1">p: Post 1 subject, t: Topic 1 title, pt: Post 1 subject, f: First forum, u: heinz, uc: #c0ffee</a>',
			),
			'admin post url' => array(
				1,
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?x=y#p42">some text</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?x=y#p42">some text</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?x=y#p42">p: , t: Admin topic title, pt: Admin topic title, f: Admin forum, u: admin, uc: #ff0000</a>',
			),
			'user url' => array(
				1,
				'<a class="postlink-local" href="'. $this->board_url .'/memberlist.'. $phpEx .'?mode=viewprofile?u=1">some text</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/memberlist.'. $phpEx .'?mode=viewprofile?u=1">u: heinz, uc: #c0ffee</a>',
			),
			'invalid and nonexistent ids' => array(
				1,
				'blah <a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?t=xyz">some text</a> blah <a class="postlink-local" href="'. $this->board_url .'/viewforum.'. $phpEx .'?f=66">some text</a> blah',
			),
			'caching' => array(
				1,
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?p=1">some text</a>' .
					'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?f=1&amp;t=1">some text</a>' .
					'<a class="postlink-local" href="'. $this->board_url .'/viewforum.'. $phpEx .'?f=1">some text</a>' .
					'<a class="postlink-local" href="'. $this->board_url .'/memberlist.'. $phpEx .'?mode=viewprofile?u=1">some text</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?p=1">p: Post 1 subject, t: Topic 1 title, pt: Post 1 subject, f: First forum, u: heinz, uc: #c0ffee</a>' .
					'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?f=1&amp;t=1">t: Topic 1 title, f: First forum</a>' .
					'<a class="postlink-local" href="'. $this->board_url .'/viewforum.'. $phpEx .'?f=1">f: <i>First forum</i></a>' .
					'<a class="postlink-local" href="'. $this->board_url .'/memberlist.'. $phpEx .'?mode=viewprofile?u=1">u: heinz, uc: #c0ffee</a>',
			),
		);
	}

	/**
	* Test the local_url_to_text event
	*
	* @dataProvider local_url_to_text_data
	*/
	public function test_local_url_to_text($sql_query_count, $text, $expected_user = false, $expected_admin = false)
	{
		$this->set_listener();

		$this->set_auth($this->auth_acl_map_user);

		$dispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();
		$dispatcher->addListener('core.modify_text_for_display_after', array($this->listener, 'local_url_to_text'));

		$event_data = array('text');
		$event = new \phpbb\event\data(compact($event_data));
		$dispatcher->dispatch('core.modify_text_for_display_after', $event);

		$event_data_after = $event->get_data_filtered($event_data);
		$this->assertArrayHasKey('text', $event_data_after);
		$this->assertEquals(($expected_user !== false ? $expected_user : $text), $event_data_after['text']);

		$this->assertEquals($sql_query_count, $this->db->num_queries['total']);

		if ($expected_admin !== false) {
			$this->create_auth();
			$this->set_listener();
			$this->set_auth($this->auth_acl_map_admin);

			$dispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();
			$dispatcher->addListener('core.modify_text_for_display_after', array($this->listener, 'local_url_to_text'));

			$event_data = array('text');
			$event = new \phpbb\event\data(compact($event_data));
			$dispatcher->dispatch('core.modify_text_for_display_after', $event);

			$event_data_after = $event->get_data_filtered($event_data);
			$this->assertArrayHasKey('text', $event_data_after);

			$this->assertEquals($expected_admin, $event_data_after['text']);
		}
	}
}
