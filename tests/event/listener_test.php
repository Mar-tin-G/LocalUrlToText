<?php
/**
*
* @package phpBB Extension - martin localurltotext
* @copyright (c) 2018 Martin ( https://github.com/Mar-tin-G )
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
	protected $auth_acl_map_admin, $auth_acl_map_user, $auth_acl_map_guest;
	protected $user, $page_operator;

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
			'martin_localurltotext_page'	=> 't: {PAGE_TITLE}',
			'martin_localurltotext_cpf'		=> 1,
		));

		$this->auth_acl_map_guest = array(
			array('f_read', 1, false),
			array('f_read', '1', false),
			array('f_read', 42, false),
			array('f_read', '42', false),
			array('a_', null, false),
		);
		$this->auth_acl_map_user = array(
			array('f_read', 1, true),
			array('f_read', '1', true),
			array('f_read', 42, false),
			array('f_read', '42', false),
			array('a_', null, false),
		);
		$this->auth_acl_map_admin = array(
			array('f_read', 1, true),
			array('f_read', '1', true),
			array('f_read', 42, true),
			array('f_read', '42', true),
			array('a_', null, true),
		);

		$this->create_auth();

		$this->db = $this->new_dbal();
		$this->php_ext = $phpEx;

		$this->user = $this->getMockBuilder('\phpbb\user')
			->disableOriginalConstructor()
			->getMock();

		$this->page_operator = $this->getMockBuilder('\phpbb\pages\operators\page')
			->disableOriginalConstructor()
			->setMethods(array('get_page_links'))
			->getMock();

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
			$this->php_ext,
			$this->user,
			$this->page_operator
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
	* Helper function for tests, needed for generate_board_url() call
	*/
	protected function generate_board_url()
	{
		global $user, $request, $config;

		$user = new \phpbb_mock_user();
		$request = new \phpbb_mock_request();
		$config = array(
			'force_server_vars'	=> true,
			'server_protocol'	=> 'http://',
			'server_name'		=> 'my-forum.com',
			'server_port'		=> 80,
			'script_path'		=> '/',
			'cookie_secure'		=> false,
		);

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
			'core.generate_profile_fields_template_data',
		), array_keys(\martin\localurltotext\event\listener::getSubscribedEvents()));
	}

	/**
	* Data set for test_modify_post_data
	*
	* @return array Array of test data
	*/
	public function modify_post_data_data()
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
				'<a class="postlink" href="http://example.com/">some text</a>',
			),
			'forum url' => array(
				1,
				'<a class="postlink-local" href="'. $this->board_url .'/viewforum.'. $phpEx .'?f=1">viewforum.'. $phpEx .'?f=1</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewforum.'. $phpEx .'?f=1">f: <i>First forum</i></a>',
			),
			'forum url with custom text' => array(
				0,
				'<a class="postlink-local" href="'. $this->board_url .'/viewforum.'. $phpEx .'?f=1">some text</a>',
			),
			'admin forum url' => array(
				1,
				'<a class="postlink-local" href="'. $this->board_url .'/viewforum.'. $phpEx .'?x=y&f=42">viewforum.'. $phpEx .'?f=42</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewforum.'. $phpEx .'?x=y&f=42">viewforum.'. $phpEx .'?f=42</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewforum.'. $phpEx .'?x=y&f=42">f: <i>Admin forum</i></a>',
			),
			'admin forum url with custom text' => array(
				0,
				'<a class="postlink-local" href="'. $this->board_url .'/viewforum.'. $phpEx .'?x=y&f=42">some text</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewforum.'. $phpEx .'?x=y&f=42">some text</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewforum.'. $phpEx .'?x=y&f=42">some text</a>',
			),
			'topic url' => array(
				1,
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?f=1&amp;t=1">viewtopic.'. $phpEx .'?f=1&amp;t=1</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?f=1&amp;t=1">t: Topic 1 title, f: First forum</a>',
			),
			'topic url with custom text' => array(
				0,
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?f=1&amp;t=1">some text</a>',
			),
			'admin topic url' => array(
				1,
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?t=42">viewtopic.'. $phpEx .'?t=42</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?t=42">viewtopic.'. $phpEx .'?t=42</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?t=42">t: Admin topic title, f: Admin forum</a>',
			),
			'admin topic url with custom text' => array(
				0,
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?t=42">some text</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?t=42">some text</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?t=42">some text</a>',
			),
			'post url' => array(
				1,
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?p=1">viewtopic.'. $phpEx .'?p=1</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?p=1">p: Post 1 subject, t: Topic 1 title, pt: Post 1 subject, f: First forum, u: heinz, uc: #c0ffee</a>',
			),
			'post url with custom text' => array(
				0,
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?p=1">some text</a>',
			),
			'admin post url' => array(
				1,
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?x=y#p42">viewtopic.'. $phpEx .'?x=y#p42</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?x=y#p42">viewtopic.'. $phpEx .'?x=y#p42</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?x=y#p42">p: , t: Admin topic title, pt: Admin topic title, f: Admin forum, u: admin, uc: #ff0000</a>',
			),
			'admin post url with custom text' => array(
				0,
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?x=y#p42">some text</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?x=y#p42">some text</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?x=y#p42">some text</a>',
			),
			'user url' => array(
				1,
				'<a class="postlink-local" href="'. $this->board_url .'/memberlist.'. $phpEx .'?mode=viewprofile?u=2">memberlist.'. $phpEx .'?mode=viewprofile?u=2</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/memberlist.'. $phpEx .'?mode=viewprofile?u=2">u: heinz, uc: #c0ffee</a>',
			),
			'user url with custom text' => array(
				0,
				'<a class="postlink-local" href="'. $this->board_url .'/memberlist.'. $phpEx .'?mode=viewprofile?u=2">some text</a>',
			),
			'invalid and nonexistent ids' => array(
				1,
				'blah <a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?t=xyz">viewtopic.'. $phpEx .'?t=xyz</a>' .
					'blah <a class="postlink-local" href="'. $this->board_url .'/viewforum.'. $phpEx .'?f=66">viewforum.'. $phpEx .'?f=66</a> blah',
			),
			'caching' => array(
				1,
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?p=1">viewtopic.'. $phpEx .'?p=1</a>' .
					'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?f=1&amp;t=1">viewtopic.'. $phpEx .'?f=1&amp;t=1</a>' .
					'<a class="postlink-local" href="'. $this->board_url .'/viewforum.'. $phpEx .'?f=1">viewforum.'. $phpEx .'?f=1</a>' .
					'<a class="postlink-local" href="'. $this->board_url .'/memberlist.'. $phpEx .'?mode=viewprofile?u=2">memberlist.'. $phpEx .'?mode=viewprofile?u=2</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?p=1">p: Post 1 subject, t: Topic 1 title, pt: Post 1 subject, f: First forum, u: heinz, uc: #c0ffee</a>' .
					'<a class="postlink-local" href="'. $this->board_url .'/viewtopic.'. $phpEx .'?f=1&amp;t=1">t: Topic 1 title, f: First forum</a>' .
					'<a class="postlink-local" href="'. $this->board_url .'/viewforum.'. $phpEx .'?f=1">f: <i>First forum</i></a>' .
					'<a class="postlink-local" href="'. $this->board_url .'/memberlist.'. $phpEx .'?mode=viewprofile?u=2">u: heinz, uc: #c0ffee</a>',
			),
		);
	}

	/**
	* Test the modify_post_data event
	*
	* @dataProvider modify_post_data_data
	*/
	public function test_modify_post_data($sql_query_count, $text, $expected_user = false, $expected_admin = false)
	{
		$this->set_listener();

		$this->set_auth($this->auth_acl_map_user);

		$dispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();
		$dispatcher->addListener('core.modify_text_for_display_after', array($this->listener, 'modify_post_data'));

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
			$dispatcher->addListener('core.modify_text_for_display_after', array($this->listener, 'modify_post_data'));

			$event_data = array('text');
			$event = new \phpbb\event\data(compact($event_data));
			$dispatcher->dispatch('core.modify_text_for_display_after', $event);

			$event_data_after = $event->get_data_filtered($event_data);
			$this->assertArrayHasKey('text', $event_data_after);

			$this->assertEquals($expected_admin, $event_data_after['text']);
		}
	}

	/**
	* Data set for test_modify_custom_profile_field_data
	*
	* @return array Array of test data
	*/
	public function modify_custom_profile_field_data_data()
	{
		global $phpEx;

		$this->generate_board_url();

		return array(
			'forum url' => array(
				array(
					'blockrow' => array(
						0 => array(
							'PROFILE_FIELD_TYPE'	=> 'whatever',
							'PROFILE_FIELD_VALUE'	=> 'some field we are not interested in - value that must remain unchanged',
						),
						1 => array(
							'PROFILE_FIELD_TYPE'	=> 'profilefields.type.url',
							'PROFILE_FIELD_VALUE'	=> '<a class="postlink-local" href="'. $this->board_url .'/viewforum.'. $phpEx .'?f=1">viewforum.'. $phpEx .'?f=1</a>',
						),
					),
				),
				array(
					'blockrow' => array(
						0 => array(
							'PROFILE_FIELD_TYPE'	=> 'whatever',
							'PROFILE_FIELD_VALUE'	=> 'some field we are not interested in - value that must remain unchanged',
						),
						1 => array(
							'PROFILE_FIELD_TYPE'	=> 'profilefields.type.url',
							'PROFILE_FIELD_VALUE'	=> '<a class="postlink-local" href="'. $this->board_url .'/viewforum.'. $phpEx .'?f=1">f: <i>First forum</i></a>',
						),
					),
				),
			),
		);
	}

	/**
	* Test the modify_custom_profile_field_data event
	* Since modify_custom_profile_field_data calls the same private function as modify_post_data, we keep this test simple
	*
	* @dataProvider modify_custom_profile_field_data_data
	*/
	public function test_modify_custom_profile_field_data($tpl_fields, $expected_tpl_fields)
	{
		$this->set_listener();

		$this->set_auth($this->auth_acl_map_user);

		$dispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();
		$dispatcher->addListener('core.generate_profile_fields_template_data', array($this->listener, 'modify_custom_profile_field_data'));

		$event_data = array('tpl_fields');
		$event = new \phpbb\event\data(compact($event_data));
		$dispatcher->dispatch('core.generate_profile_fields_template_data', $event);

		$event_data_after = $event->get_data_filtered($event_data);
		$this->assertArrayHasKey('tpl_fields', $event_data_after);
		$this->assertEquals($expected_tpl_fields, $event_data_after['tpl_fields']);
	}

	/**
	* Data set for test_modify_post_data_pages
	*/
	public function modify_post_data_pages_data()
	{
		global $phpEx;

		$this->generate_board_url();

		/*
		first value: original text
		second value: expected text for guests
		third value - if specified: expected text for users; if not specified: expected text for users = expected text for guests
		fourth value - if specified: expected text for admins; if not specified: expected text for admins = expected text for users
		*/
		return array(
			'page url without mod_rewrite' => array(
				'<a class="postlink-local" href="'. $this->board_url .'/app.'. $phpEx .'/page/guestpage">/app.'. $phpEx .'/page/guestpage</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/app.'. $phpEx .'/page/guestpage">t: Guest page</a>',
			),
			'page url without mod_rewrite with custom text' => array(
				'<a class="postlink-local" href="'. $this->board_url .'/app.'. $phpEx .'/page/guestpage">some text</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/app.'. $phpEx .'/page/guestpage">some text</a>',
			),
			'guest visible page url' => array(
				'<a class="postlink-local" href="'. $this->board_url .'/page/guestpage">/page/guestpage</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/page/guestpage">t: Guest page</a>',
			),
			'guest visible page url with custom text' => array(
				'<a class="postlink-local" href="'. $this->board_url .'/page/guestpage">some text</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/page/guestpage">some text</a>',
			),
			'member visible page url' => array(
				'<a class="postlink-local" href="'. $this->board_url .'/page/memberpage">/page/memberpage</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/page/memberpage">/page/memberpage</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/page/memberpage">t: Member page</a>',
			),
			'member visible page url with custom text' => array(
				'<a class="postlink-local" href="'. $this->board_url .'/page/memberpage">some text</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/page/memberpage">some text</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/page/memberpage">some text</a>',
			),
			'admin visible page url' => array(
				'<a class="postlink-local" href="'. $this->board_url .'/page/adminpage">/page/adminpage</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/page/adminpage">/page/adminpage</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/page/adminpage">/page/adminpage</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/page/adminpage">t: Admin page</a>',
			),
			'admin visible page url with custom text' => array(
				'<a class="postlink-local" href="'. $this->board_url .'/page/adminpage">some text</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/page/adminpage">some text</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/page/adminpage">some text</a>',
				'<a class="postlink-local" href="'. $this->board_url .'/page/adminpage">some text</a>',
			),
		);
	}

	/**
	* Test modify_post_data event for integration with Pages extension
	*
	* @dataProvider modify_post_data_pages_data
	*/
	public function test_modify_post_data_pages($text, $expected_guest, $expected_user = false, $expected_admin = false)
	{
		$this->page_operator
			->method('get_page_links')
			->willReturn(array(
				array(
					'page_route'				=> 'guestpage',
					'page_display'				=> true,
					'page_display_to_guests'	=> true,
					'page_title'				=> 'Guest page',
				),
				array(
					'page_route'				=> 'memberpage',
					'page_display'				=> true,
					'page_display_to_guests'	=> false,
					'page_title'				=> 'Member page',
				),
				array(
					'page_route'				=> 'adminpage',
					'page_display'				=> false,
					'page_display_to_guests'	=> false,
					'page_title'				=> 'Admin page',
				),
			));

		$this->set_listener();
		$this->set_auth($this->auth_acl_map_guest);
		$this->user->data['user_id'] = ANONYMOUS;

		$dispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();
		$dispatcher->addListener('core.modify_text_for_display_after', array($this->listener, 'modify_post_data'));

		$event_data = array('text');
		$event = new \phpbb\event\data(compact($event_data));
		$dispatcher->dispatch('core.modify_text_for_display_after', $event);

		$event_data_after = $event->get_data_filtered($event_data);
		$this->assertArrayHasKey('text', $event_data_after);
		$this->assertEquals($expected_guest, $event_data_after['text']);

		if ($expected_user !== false) {
			$this->create_auth();
			$this->set_listener();
			$this->set_auth($this->auth_acl_map_user);
			$this->user->data['user_id'] = 2;

			$dispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();
			$dispatcher->addListener('core.modify_text_for_display_after', array($this->listener, 'modify_post_data'));

			$event_data = array('text');
			$event = new \phpbb\event\data(compact($event_data));
			$dispatcher->dispatch('core.modify_text_for_display_after', $event);

			$event_data_after = $event->get_data_filtered($event_data);
			$this->assertArrayHasKey('text', $event_data_after);

			$this->assertEquals($expected_user, $event_data_after['text']);
		}

		if ($expected_admin !== false) {
			$this->create_auth();
			$this->set_listener();
			$this->set_auth($this->auth_acl_map_admin);
			$this->user->data['user_id'] = 42;

			$dispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();
			$dispatcher->addListener('core.modify_text_for_display_after', array($this->listener, 'modify_post_data'));

			$event_data = array('text');
			$event = new \phpbb\event\data(compact($event_data));
			$dispatcher->dispatch('core.modify_text_for_display_after', $event);

			$event_data_after = $event->get_data_filtered($event_data);
			$this->assertArrayHasKey('text', $event_data_after);

			$this->assertEquals($expected_admin, $event_data_after['text']);
		}
	}
}
