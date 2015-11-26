<?php
/**
*
* @package phpBB Extension - martin localurltotext
* @copyright (c) 2015 Martin ( https://github.com/Mar-tin-G )
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace martin\localurltotext\migrations;

class release_1_0_0 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['martin_localurltotext_version']) && version_compare($this->config['martin_localurltotext_version'], '1.0.0', '>=');
	}

	static public function depends_on()
	{
		return array('\phpbb\db\migration\data\v310\alpha2');
	}

	public function update_data()
	{
		return array(
			array('config.add', array('martin_localurltotext_version', '1.0.0')),
			array('config.add', array('martin_localurltotext_forum', '{FORUM_NAME}')),
			array('config.add', array('martin_localurltotext_topic', '{TOPIC_TITLE}')),
			array('config.add', array('martin_localurltotext_post', '{USER_NAME} @ {TOPIC_TITLE}')),
			array('config.add', array('martin_localurltotext_user', '{USER_NAME}')),

			array('module.add', array(
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_LOCALURLTOTEXT_TITLE'
			)),
			array('module.add', array(
				'acp',
				'ACP_LOCALURLTOTEXT_TITLE',
				array(
					'module_basename'	=> '\martin\localurltotext\acp\main_module',
					'modes'				=> array('settings'),
				),
			)),
		);
	}
}
