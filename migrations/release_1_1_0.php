<?php
/**
*
* @package phpBB Extension - martin localurltotext
* @copyright (c) 2018 Martin ( https://github.com/Mar-tin-G )
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace martin\localurltotext\migrations;

use phpbb\db\migration\migration;

class release_1_1_0 extends migration
{
	public function effectively_installed()
	{
		return isset($this->config['martin_localurltotext_version']) && version_compare($this->config['martin_localurltotext_version'], '1.1.0', '>=');
	}

	static public function depends_on()
	{
		return array('\martin\localurltotext\migrations\release_1_0_0');
	}

	public function update_data()
	{
		return array(
			array('config.update', array('martin_localurltotext_version', '1.1.0')),
			array('config.add', array('martin_localurltotext_page', '{PAGE_TITLE}')),
			array('config.add', array('martin_localurltotext_cpf', 0)),
		);
	}
}
