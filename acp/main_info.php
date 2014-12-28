<?php
/**
*
* @package phpBB Extension - martin localurltotext
* @copyright (c) 2014 Martin ( https://github.com/Martin-G- )
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace martin\localurltotext\acp;

class main_info
{
	function module()
	{
		return array(
			'filename'	=> '\martin\localurltotext\acp\main_module',
			'title'		=> 'ACP_LOCALURLTOTEXT_TITLE',
			'version'	=> '1.0.0',
			'modes'		=> array(
				'settings'	=> array(
					'title'	=> 'ACP_LOCALURLTOTEXT_SETTINGS',
					'auth'	=> 'ext_martin/localurltotext && acl_a_board',
					'cat'	=> array('ACP_LOCALURLTOTEXT_TITLE'),
				),
			),
		);
	}
}
