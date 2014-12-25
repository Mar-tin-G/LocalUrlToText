<?php
/**
*
* @package phpBB Extension - Martin LocalUrlToText
* @copyright (c) 2014 Martin
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace Martin\LocalUrlToText\acp;

class main_info
{
	function module()
	{
		return array(
			'filename'	=> '\Martin\LocalUrlToText\acp\main_module',
			'title'		=> 'ACP_LOCALURLTOTEXT_TITLE',
			'version'	=> '1.0.0',
			'modes'		=> array(
				'settings'	=> array(
					'title'	=> 'ACP_LOCALURLTOTEXT_SETTINGS',
					'auth'	=> 'ext_Martin/LocalUrlToText && acl_a_board',
					'cat'	=> array('ACP_LOCALURLTOTEXT_TITLE'),
				),
			),
		);
	}
}
