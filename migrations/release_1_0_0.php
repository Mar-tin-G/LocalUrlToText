<?php
/**
*
* @package phpBB Extension - Martin LocalUrlToText
* @copyright (c) 2014 Martin
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace Martin\LocalUrlToText\migrations;

class release_1_0_0 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['Martin_LocalUrlToText_forum']);
	}

	static public function depends_on()
	{
		return array('\phpbb\db\migration\data\v310\alpha2');
	}

	public function update_data()
	{
		return array(
			array('config.add', array('Martin_LocalUrlToText_forum', '{FORUM_NAME}')),
			array('config.add', array('Martin_LocalUrlToText_topic', '{TOPIC_TITLE}')),
			array('config.add', array('Martin_LocalUrlToText_post', '{USER_NAME} @ {TOPIC_TITLE}')),
			array('config.add', array('Martin_LocalUrlToText_user', '{USER_NAME}')),

			array('module.add', array(
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_LOCALURLTOTEXT_TITLE'
			)),
			array('module.add', array(
				'acp',
				'ACP_LOCALURLTOTEXT_TITLE',
				array(
					'module_basename'	=> '\Martin\LocalUrlToText\acp\main_module',
					'modes'				=> array('settings'),
				),
			)),
		);
	}
}
