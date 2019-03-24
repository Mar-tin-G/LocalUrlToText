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
			'martin_localurltotext_forum'	=> 'f: {FORUM_NAME}',
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
	* Helper function replacing the URL protocol scheme
	*/
	protected function replace_url_scheme($url, $scheme)
	{
		return preg_replace('/^[a-z0-9.-]+:/', $scheme, $url);
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
			'core.text_formatter_s9e_render_before',
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
		$https_board_url = $this->replace_url_scheme($this->board_url, 'https:');

		/*
		first value: original XML
		second value - if specified: expected XML for users; if not specified: expected XML for users = original XML
		third value - if specified: expected XML for admins; if not specified: expected XML for admins = expected XML for users
		*/
		return array(
			'no local urls' => array(
				'<URL url="http://example.com/"><LINK_TEXT text="some text">some text</LINK_TEXT></URL>',
			),
			'forum url' => array(
				'<URL url="'. $this->board_url .'/viewforum.'. $phpEx .'?f=1"><LINK_TEXT text="viewforum.'. $phpEx .'?f=1">'. $this->board_url .'/viewforum.'. $phpEx .'?f=1</LINK_TEXT></URL>',
				'<URL url="'. $this->board_url .'/viewforum.'. $phpEx .'?f=1"><LINK_TEXT text="f: First forum">'. $this->board_url .'/viewforum.'. $phpEx .'?f=1</LINK_TEXT></URL>',
			),
			'forum url with custom text' => array(
				'<URL url="'. $this->board_url .'/viewforum.'. $phpEx .'?f=1"><s>[url='. $this->board_url .'/viewforum.'. $phpEx .'?f=1]</s>some text<e>[/url]</e></URL>',
			),
			'admin forum url' => array(
				'<URL url="'. $this->board_url .'/viewforum.'. $phpEx .'?f=42"><LINK_TEXT text="viewforum.'. $phpEx .'?f=42">'. $this->board_url .'/viewforum.'. $phpEx .'?f=42</LINK_TEXT></URL>',
				'<URL url="'. $this->board_url .'/viewforum.'. $phpEx .'?f=42"><LINK_TEXT text="viewforum.'. $phpEx .'?f=42">'. $this->board_url .'/viewforum.'. $phpEx .'?f=42</LINK_TEXT></URL>',
				'<URL url="'. $this->board_url .'/viewforum.'. $phpEx .'?f=42"><LINK_TEXT text="f: Admin forum">'. $this->board_url .'/viewforum.'. $phpEx .'?f=42</LINK_TEXT></URL>',
			),
			'admin forum url with custom text' => array(
				'<URL url="'. $this->board_url .'/viewforum.'. $phpEx .'?f=42"><s>[url='. $this->board_url .'/viewforum.'. $phpEx .'?f=42]</s>some text<e>[/url]</e></URL>',
				'<URL url="'. $this->board_url .'/viewforum.'. $phpEx .'?f=42"><s>[url='. $this->board_url .'/viewforum.'. $phpEx .'?f=42]</s>some text<e>[/url]</e></URL>',
				'<URL url="'. $this->board_url .'/viewforum.'. $phpEx .'?f=42"><s>[url='. $this->board_url .'/viewforum.'. $phpEx .'?f=42]</s>some text<e>[/url]</e></URL>',
			),
			'topic url' => array(
				'<URL url="'. $this->board_url .'/viewtopic.'. $phpEx .'?f=1&amp;t=1"><LINK_TEXT text="viewtopic.'. $phpEx .'?f=1&amp;t=1">'. $this->board_url .'/viewtopic.'. $phpEx .'?f=1&amp;t=1</LINK_TEXT></URL>',
				'<URL url="'. $this->board_url .'/viewtopic.'. $phpEx .'?f=1&amp;t=1"><LINK_TEXT text="t: Topic 1 title, f: First forum">'. $this->board_url .'/viewtopic.'. $phpEx .'?f=1&amp;t=1</LINK_TEXT></URL>',
			),
			'topic url with custom text' => array(
				'<URL url="'. $this->board_url .'/viewtopic.'. $phpEx .'?f=1&amp;t=1"><s>[url='. $this->board_url .'/viewtopic.'. $phpEx .'?f=1&amp;t=1]</s>some text<e>[/url]</e></URL>',
			),
			'admin topic url' => array(
				'<URL url="'. $this->board_url .'/viewtopic.'. $phpEx .'?t=42"><LINK_TEXT text="viewtopic.'. $phpEx .'?t=42">'. $this->board_url .'/viewtopic.'. $phpEx .'?t=42</LINK_TEXT></URL>',
				'<URL url="'. $this->board_url .'/viewtopic.'. $phpEx .'?t=42"><LINK_TEXT text="viewtopic.'. $phpEx .'?t=42">'. $this->board_url .'/viewtopic.'. $phpEx .'?t=42</LINK_TEXT></URL>',
				'<URL url="'. $this->board_url .'/viewtopic.'. $phpEx .'?t=42"><LINK_TEXT text="t: Admin topic title, f: Admin forum">'. $this->board_url .'/viewtopic.'. $phpEx .'?t=42</LINK_TEXT></URL>',
			),
			'admin topic url with custom text' => array(
				'<URL url="'. $this->board_url .'/viewtopic.'. $phpEx .'?t=42"><s>[url='. $this->board_url .'/viewtopic.'. $phpEx .'?t=42]</s>some text<e>[/url]</e></URL>',
				'<URL url="'. $this->board_url .'/viewtopic.'. $phpEx .'?t=42"><s>[url='. $this->board_url .'/viewtopic.'. $phpEx .'?t=42]</s>some text<e>[/url]</e></URL>',
				'<URL url="'. $this->board_url .'/viewtopic.'. $phpEx .'?t=42"><s>[url='. $this->board_url .'/viewtopic.'. $phpEx .'?t=42]</s>some text<e>[/url]</e></URL>',
			),
			'post url' => array(
				'<URL url="'. $this->board_url .'/viewtopic.'. $phpEx .'?p=1"><LINK_TEXT text="viewtopic.'. $phpEx .'?p=1">'. $this->board_url .'/viewtopic.'. $phpEx .'?p=1</LINK_TEXT></URL>',
				'<URL url="'. $this->board_url .'/viewtopic.'. $phpEx .'?p=1"><LINK_TEXT text="p: Post 1 subject, t: Topic 1 title, pt: Post 1 subject, f: First forum, u: heinz, uc: #c0ffee">'. $this->board_url .'/viewtopic.'. $phpEx .'?p=1</LINK_TEXT></URL>',
			),
			'post url with custom text' => array(
				'<URL url="'. $this->board_url .'/viewtopic.'. $phpEx .'?p=1"><s>[url='. $this->board_url .'/viewtopic.'. $phpEx .'?p=1]</s>some text<e>[/url]</e></URL>',
			),
			'admin post url' => array(
				'<URL url="'. $this->board_url .'/viewtopic.'. $phpEx .'?x=y#p42"><LINK_TEXT text="viewtopic.'. $phpEx .'?x=y#p42">'. $this->board_url .'/viewtopic.'. $phpEx .'?x=y#p42</LINK_TEXT></URL>',
				'<URL url="'. $this->board_url .'/viewtopic.'. $phpEx .'?x=y#p42"><LINK_TEXT text="viewtopic.'. $phpEx .'?x=y#p42">'. $this->board_url .'/viewtopic.'. $phpEx .'?x=y#p42</LINK_TEXT></URL>',
				'<URL url="'. $this->board_url .'/viewtopic.'. $phpEx .'?x=y#p42"><LINK_TEXT text="p: , t: Admin topic title, pt: Admin topic title, f: Admin forum, u: admin, uc: #ff0000">'. $this->board_url .'/viewtopic.'. $phpEx .'?x=y#p42</LINK_TEXT></URL>',
			),
			'admin post url with custom text' => array(
				'<URL url="'. $this->board_url .'/viewtopic.'. $phpEx .'?x=y#p42"><s>[url='. $this->board_url .'/viewtopic.'. $phpEx .'?x=y#p42]</s>some text<e>[/url]</e></URL>',
				'<URL url="'. $this->board_url .'/viewtopic.'. $phpEx .'?x=y#p42"><s>[url='. $this->board_url .'/viewtopic.'. $phpEx .'?x=y#p42]</s>some text<e>[/url]</e></URL>',
				'<URL url="'. $this->board_url .'/viewtopic.'. $phpEx .'?x=y#p42"><s>[url='. $this->board_url .'/viewtopic.'. $phpEx .'?x=y#p42]</s>some text<e>[/url]</e></URL>',
			),
			'user url' => array(
				'<URL url="'. $this->board_url .'/memberlist.'. $phpEx .'?mode=viewprofile?u=2"><LINK_TEXT text="memberlist.'. $phpEx .'?mode=viewprofile?u=2">'. $this->board_url .'/memberlist.'. $phpEx .'?mode=viewprofile?u=2</LINK_TEXT></URL>',
				'<URL url="'. $this->board_url .'/memberlist.'. $phpEx .'?mode=viewprofile?u=2"><LINK_TEXT text="u: heinz, uc: #c0ffee">'. $this->board_url .'/memberlist.'. $phpEx .'?mode=viewprofile?u=2</LINK_TEXT></URL>',
			),
			'user url with custom text' => array(
				'<URL url="'. $this->board_url .'/memberlist.'. $phpEx .'?mode=viewprofile?u=2"><s>[url='. $this->board_url .'/memberlist.'. $phpEx .'?mode=viewprofile?u=2]</s>some text<e>[/url]</e></URL>',
			),
			'invalid id' => array(
				'<URL url="'. $this->board_url .'/viewtopic.'. $phpEx .'?t=xyz"><LINK_TEXT text="viewtopic.'. $phpEx .'?t=xyz">'. $this->board_url .'/viewtopic.'. $phpEx .'?t=xyz</LINK_TEXT></URL>',
			),
			'nonexistent id' => array(
				'<URL url="'. $this->board_url .'/viewforum.'. $phpEx .'?f=66"><LINK_TEXT text="viewforum.'. $phpEx .'?f=66">'. $this->board_url .'/viewforum.'. $phpEx .'?f=66</LINK_TEXT></URL>',
			),
			'url with different scheme' => array(
				'<URL url="'. $https_board_url .'/viewforum.'. $phpEx .'?f=1"><LINK_TEXT text="viewforum.'. $phpEx .'?f=1">'. $https_board_url .'/viewforum.'. $phpEx .'?f=1</LINK_TEXT></URL>',
				'<URL url="'. $https_board_url .'/viewforum.'. $phpEx .'?f=1"><LINK_TEXT text="f: First forum">'. $https_board_url .'/viewforum.'. $phpEx .'?f=1</LINK_TEXT></URL>',
			),
		);
	}

	/**
	* Test the modify_post_data event
	*
	* @dataProvider modify_post_data_data
	*/
	public function test_modify_post_data($xml, $expected_user = false, $expected_admin = false)
	{
		$this->set_listener();

		$this->set_auth($this->auth_acl_map_user);

		$dispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();
		$dispatcher->addListener('core.modify_text_for_display_after', array($this->listener, 'modify_post_data'));

		$event_data = array('xml');
		$event = new \phpbb\event\data(compact($event_data));
		$dispatcher->dispatch('core.modify_text_for_display_after', $event);

		$event_data_after = $event->get_data_filtered($event_data);
		$this->assertArrayHasKey('xml', $event_data_after);
		$this->assertEquals(($expected_user !== false ? $expected_user : $xml), $event_data_after['xml']);

		if ($expected_admin !== false) {
			$this->create_auth();
			$this->set_listener();
			$this->set_auth($this->auth_acl_map_admin);

			$dispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();
			$dispatcher->addListener('core.modify_text_for_display_after', array($this->listener, 'modify_post_data'));

			$event_data = array('xml');
			$event = new \phpbb\event\data(compact($event_data));
			$dispatcher->dispatch('core.modify_text_for_display_after', $event);

			$event_data_after = $event->get_data_filtered($event_data);
			$this->assertArrayHasKey('xml', $event_data_after);

			$this->assertEquals($expected_admin, $event_data_after['xml']);
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
		$https_board_url = $this->replace_url_scheme($this->board_url, 'https:');

		return array(
			'forum url' => array(
				array(
					'blockrow' => array(
						0 => array(
							'PROFILE_FIELD_TYPE'		=> 'whatever',
							'PROFILE_FIELD_VALUE'		=> 'some field we are not interested in - value that must remain unchanged',
							'PROFILE_FIELD_VALUE_RAW'	=> 'whatever',
						),
						1 => array(
							'PROFILE_FIELD_TYPE'		=> 'profilefields.type.url',
							'PROFILE_FIELD_VALUE'		=> '<a class="postlink-local" href="'. $this->board_url .'/viewforum.'. $phpEx .'?f=1">viewforum.'. $phpEx .'?f=1</a>',
							'PROFILE_FIELD_VALUE_RAW'	=> $this->board_url .'/viewforum.'. $phpEx .'?f=1',
						),
					),
				),
				array(
					'blockrow' => array(
						0 => array(
							'PROFILE_FIELD_TYPE'		=> 'whatever',
							'PROFILE_FIELD_VALUE'		=> 'some field we are not interested in - value that must remain unchanged',
							'PROFILE_FIELD_VALUE_RAW'	=> 'whatever',
						),
						1 => array(
							'PROFILE_FIELD_TYPE'		=> 'profilefields.type.url',
							'PROFILE_FIELD_VALUE'		=> '<!-- l --><a class="postlink-local" href="'. $this->board_url .'/viewforum.'. $phpEx .'?f=1">f: First forum</a><!-- l -->',
							'PROFILE_FIELD_VALUE_RAW'	=> $this->board_url .'/viewforum.'. $phpEx .'?f=1',
						),
					),
				),
			),
			'url with different scheme' => array(
				array(
					'blockrow' => array(
						0 => array(
							'PROFILE_FIELD_TYPE'		=> 'whatever',
							'PROFILE_FIELD_VALUE'		=> 'some field we are not interested in - value that must remain unchanged',
							'PROFILE_FIELD_VALUE_RAW'	=> 'whatever',
						),
						1 => array(
							'PROFILE_FIELD_TYPE'		=> 'profilefields.type.url',
							'PROFILE_FIELD_VALUE'		=> '<a class="postlink-local" href="'. $https_board_url .'/viewforum.'. $phpEx .'?f=1">viewforum.'. $phpEx .'?f=1</a>',
							'PROFILE_FIELD_VALUE_RAW'	=> $https_board_url .'/viewforum.'. $phpEx .'?f=1',
						),
					),
				),
				array(
					'blockrow' => array(
						0 => array(
							'PROFILE_FIELD_TYPE'		=> 'whatever',
							'PROFILE_FIELD_VALUE'		=> 'some field we are not interested in - value that must remain unchanged',
							'PROFILE_FIELD_VALUE_RAW'	=> 'whatever',
						),
						1 => array(
							'PROFILE_FIELD_TYPE'		=> 'profilefields.type.url',
							'PROFILE_FIELD_VALUE'		=> '<!-- l --><a class="postlink-local" href="'. $https_board_url .'/viewforum.'. $phpEx .'?f=1">f: First forum</a><!-- l -->',
							'PROFILE_FIELD_VALUE_RAW'	=> $https_board_url .'/viewforum.'. $phpEx .'?f=1',
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
		$https_board_url = $this->replace_url_scheme($this->board_url, 'https:');

		/*
		first value: original XML
		second value: expected XML for guests
		third value - if specified: expected XML for users; if not specified: expected XML for users = expected XML for guests
		fourth value - if specified: expected XML for admins; if not specified: expected XML for admins = expected XML for users
		*/
		return array(
			'page url without mod_rewrite' => array(
				'<URL url="'. $this->board_url .'/app.'. $phpEx .'/guestpage"><LINK_TEXT text="app.'. $phpEx .'/guestpage">'. $this->board_url .'/app.'. $phpEx .'/guestpage</LINK_TEXT></URL>',
				'<URL url="'. $this->board_url .'/app.'. $phpEx .'/guestpage"><LINK_TEXT text="t: Guest page">'. $this->board_url .'/app.'. $phpEx .'/guestpage</LINK_TEXT></URL>',
			),
			'page url without mod_rewrite with custom text' => array(
				'<URL url="'. $this->board_url .'/app.'. $phpEx .'/guestpage"><s>[url='. $this->board_url .'/app.'. $phpEx .'/guestpage]</s>some text<e>[/url]</e></URL>',
				'<URL url="'. $this->board_url .'/app.'. $phpEx .'/guestpage"><s>[url='. $this->board_url .'/app.'. $phpEx .'/guestpage]</s>some text<e>[/url]</e></URL>',
			),
			'guest visible page url' => array(
				'<URL url="'. $this->board_url .'/guestpage"><LINK_TEXT text="guestpage">'. $this->board_url .'/guestpage</LINK_TEXT></URL>',
				'<URL url="'. $this->board_url .'/guestpage"><LINK_TEXT text="t: Guest page">'. $this->board_url .'/guestpage</LINK_TEXT></URL>',
			),
			'guest visible page url with custom text' => array(
				'<URL url="'. $this->board_url .'/guestpage"><s>[url='. $this->board_url .'/guestpage]</s>some text<e>[/url]</e></URL>',
				'<URL url="'. $this->board_url .'/guestpage"><s>[url='. $this->board_url .'/guestpage]</s>some text<e>[/url]</e></URL>',
			),
			'member visible page url' => array(
				'<URL url="'. $this->board_url .'/memberpage"><LINK_TEXT text="memberpage">'. $this->board_url .'/memberpage</LINK_TEXT></URL>',
				'<URL url="'. $this->board_url .'/memberpage"><LINK_TEXT text="memberpage">'. $this->board_url .'/memberpage</LINK_TEXT></URL>',
				'<URL url="'. $this->board_url .'/memberpage"><LINK_TEXT text="t: Member page">'. $this->board_url .'/memberpage</LINK_TEXT></URL>',
			),
			'member visible page url with custom text' => array(
				'<URL url="'. $this->board_url .'/memberpage"><s>[url='. $this->board_url .'/memberpage]</s>some text<e>[/url]</e></URL>',
				'<URL url="'. $this->board_url .'/memberpage"><s>[url='. $this->board_url .'/memberpage]</s>some text<e>[/url]</e></URL>',
				'<URL url="'. $this->board_url .'/memberpage"><s>[url='. $this->board_url .'/memberpage]</s>some text<e>[/url]</e></URL>',
			),
			'admin visible page url' => array(
				'<URL url="'. $this->board_url .'/adminpage"><LINK_TEXT text="adminpage">'. $this->board_url .'/adminpage</LINK_TEXT></URL>',
				'<URL url="'. $this->board_url .'/adminpage"><LINK_TEXT text="adminpage">'. $this->board_url .'/adminpage</LINK_TEXT></URL>',
				'<URL url="'. $this->board_url .'/adminpage"><LINK_TEXT text="adminpage">'. $this->board_url .'/adminpage</LINK_TEXT></URL>',
				'<URL url="'. $this->board_url .'/adminpage"><LINK_TEXT text="t: Admin page">'. $this->board_url .'/adminpage</LINK_TEXT></URL>',
			),
			'admin visible page url with custom text' => array(
				'<URL url="'. $this->board_url .'/adminpage"><s>[url='. $this->board_url .'/adminpage]</s>some text<e>[/url]</e></URL>',
				'<URL url="'. $this->board_url .'/adminpage"><s>[url='. $this->board_url .'/adminpage]</s>some text<e>[/url]</e></URL>',
				'<URL url="'. $this->board_url .'/adminpage"><s>[url='. $this->board_url .'/adminpage]</s>some text<e>[/url]</e></URL>',
				'<URL url="'. $this->board_url .'/adminpage"><s>[url='. $this->board_url .'/adminpage]</s>some text<e>[/url]</e></URL>',
			),
			'url with different scheme' => array(
				'<URL url="'. $https_board_url .'/guestpage"><LINK_TEXT text="guestpage">'. $https_board_url .'/guestpage</LINK_TEXT></URL>',
				'<URL url="'. $https_board_url .'/guestpage"><LINK_TEXT text="t: Guest page">'. $https_board_url .'/guestpage</LINK_TEXT></URL>',
			),
			'unknown page route' => array(
				'<URL url="'. $this->board_url .'/unknownpage"><LINK_TEXT text="unknownpage">'. $this->board_url .'/unknownpage</LINK_TEXT></URL>',
				'<URL url="'. $this->board_url .'/unknownpage"><LINK_TEXT text="unknownpage">'. $this->board_url .'/unknownpage</LINK_TEXT></URL>',
			),
		);
	}

	/**
	* Test modify_post_data event for integration with Pages extension
	*
	* @dataProvider modify_post_data_pages_data
	*/
	public function test_modify_post_data_pages($xml, $expected_guest, $expected_user = false, $expected_admin = false)
	{
		$this->set_listener();
		$this->set_auth($this->auth_acl_map_guest);
		$this->user->data['user_id'] = ANONYMOUS;

		$dispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();
		$dispatcher->addListener('core.text_formatter_s9e_render_before', array($this->listener, 'modify_post_data'));

		$event_data = array('xml');
		$event = new \phpbb\event\data(compact($event_data));
		$dispatcher->dispatch('core.text_formatter_s9e_render_before', $event);

		$event_data_after = $event->get_data_filtered($event_data);
		$this->assertArrayHasKey('xml', $event_data_after);
		$this->assertEquals($expected_guest, $event_data_after['xml']);

		if ($expected_user !== false) {
			$this->create_auth();
			$this->set_listener();
			$this->set_auth($this->auth_acl_map_user);
			$this->user->data['user_id'] = 2;

			$dispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();
			$dispatcher->addListener('core.text_formatter_s9e_render_before', array($this->listener, 'modify_post_data'));

			$event_data = array('xml');
			$event = new \phpbb\event\data(compact($event_data));
			$dispatcher->dispatch('core.text_formatter_s9e_render_before', $event);

			$event_data_after = $event->get_data_filtered($event_data);
			$this->assertArrayHasKey('xml', $event_data_after);

			$this->assertEquals($expected_user, $event_data_after['xml']);
		}

		if ($expected_admin !== false) {
			$this->create_auth();
			$this->set_listener();
			$this->set_auth($this->auth_acl_map_admin);
			$this->user->data['user_id'] = 42;

			$dispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();
			$dispatcher->addListener('core.text_formatter_s9e_render_before', array($this->listener, 'modify_post_data'));

			$event_data = array('xml');
			$event = new \phpbb\event\data(compact($event_data));
			$dispatcher->dispatch('core.text_formatter_s9e_render_before', $event);

			$event_data_after = $event->get_data_filtered($event_data);
			$this->assertArrayHasKey('xml', $event_data_after);

			$this->assertEquals($expected_admin, $event_data_after['xml']);
		}
	}
}
