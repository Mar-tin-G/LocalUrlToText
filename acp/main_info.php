<?php
/**
*
* @package phpBB Extension - martin localurltotext
* @copyright (c) 2018 Martin ( https://github.com/Mar-tin-G )
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace martin\localurltotext\acp;

class main_info
{
	public function module()
	{
		return array(
			'filename'	=> '\martin\localurltotext\acp\main_module',
			'title'		=> 'ACP_LOCALURLTOTEXT_TITLE',
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
