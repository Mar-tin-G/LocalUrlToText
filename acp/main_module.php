<?php
/**
*
* @package phpBB Extension - martin localurltotext
* @copyright (c) 2015 Martin ( https://github.com/Martin-G- )
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace martin\localurltotext\acp;

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
		add_form_key('martin/localurltotext');

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key('martin/localurltotext'))
			{
				trigger_error('FORM_INVALID');
			}

			$config->set('martin_localurltotext_forum', $request->variable('martin_localurltotext_forum', ''));
			$config->set('martin_localurltotext_topic', $request->variable('martin_localurltotext_topic', ''));
			$config->set('martin_localurltotext_post', $request->variable('martin_localurltotext_post', ''));
			$config->set('martin_localurltotext_user', $request->variable('martin_localurltotext_user', ''));

			trigger_error($user->lang('ACP_LOCALURLTOTEXT_SETTING_SAVED') . adm_back_link($this->u_action));
		}

		$template->assign_vars(array(
			'U_ACTION'						=> $this->u_action,
			'MARTIN_LOCALURLTOTEXT_FORUM'	=> $config['martin_localurltotext_forum'],
			'MARTIN_LOCALURLTOTEXT_TOPIC'	=> $config['martin_localurltotext_topic'],
			'MARTIN_LOCALURLTOTEXT_POST'	=> $config['martin_localurltotext_post'],
			'MARTIN_LOCALURLTOTEXT_USER'	=> $config['martin_localurltotext_user'],
		));
	}
}
