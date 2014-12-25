<?php
/**
*
* @package phpBB Extension - Martin LocalUrlToText
* @copyright (c) 2014 Martin
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace Martin\LocalUrlToText\acp;

class main_module
{
	var $u_action;

	function main($id, $mode)
	{
		global $db, $user, $auth, $template, $cache, $request;
		global $config, $phpbb_root_path, $phpbb_admin_path, $phpEx;

		$user->add_lang('acp/common');
		$this->tpl_name = 'localurltotext_body';
		$this->page_title = $user->lang('ACP_LOCALURLTOTEXT_TITLE');
		add_form_key('Martin/LocalUrlToText');

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key('Martin/LocalUrlToText'))
			{
				trigger_error('FORM_INVALID');
			}

			$config->set('Martin_LocalUrlToText_forum', $request->variable('Martin_LocalUrlToText_forum', ''));
			$config->set('Martin_LocalUrlToText_topic', $request->variable('Martin_LocalUrlToText_topic', ''));
			$config->set('Martin_LocalUrlToText_post', $request->variable('Martin_LocalUrlToText_post', ''));
			$config->set('Martin_LocalUrlToText_user', $request->variable('Martin_LocalUrlToText_user', ''));

			trigger_error($user->lang('ACP_LOCALURLTOTEXT_SETTING_SAVED') . adm_back_link($this->u_action));
		}

		$template->assign_vars(array(
			'U_ACTION'						=> $this->u_action,
			'MARTIN_LOCALURLTOTEXT_FORUM'	=> $config['Martin_LocalUrlToText_forum'],
			'MARTIN_LOCALURLTOTEXT_TOPIC'	=> $config['Martin_LocalUrlToText_topic'],
			'MARTIN_LOCALURLTOTEXT_POST'	=> $config['Martin_LocalUrlToText_post'],
			'MARTIN_LOCALURLTOTEXT_USER'	=> $config['Martin_LocalUrlToText_user'],
		));
	}
}
