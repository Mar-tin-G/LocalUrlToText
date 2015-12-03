<?php
/**
*
* @package phpBB Extension - martin localurltotext
* @copyright (c) 2015 Martin ( https://github.com/Mar-tin-G )
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace martin\localurltotext\tests\event;

class event_listener_test extends \phpbb_test_case
{
    /** @var \martin\localurltotext\event\listener */
    protected $listener;

    protected $config, $auth, $db, $php_ext;

	/**
	* Setup test environment
	*/
	public function setUp()
	{
		global $phpEx;

		parent::setUp();

		$this->config = $this->getMockBuilder('\phpbb\config\config')
			->disableOriginalConstructor()
			->getMock();
		$this->auth = $this->getMockBuilder('\phpbb\auth\auth')
			->disableOriginalConstructor()
			->getMock();
		// TODO da fehlt ja wohl was... s. database testing
		$this->db = $this->getMockBuilder('\phpbb\db\driver\driver_interface')
			->disableOriginalConstructor()
			->getMock();
		$this->php_ext = $phpEx;

		$this->listener = new \martin\localurltotext\event\listener(
			$this->config,
			$this->auth,
			$this->db,
			$this->php_ext
		);
	}

	/**
	* Test the event listener is constructed correctly
	*/
	public function test_construct()
	{
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

	// TODO
	public function test_local_url_to_text()
	{
		// TODO
	}
}
