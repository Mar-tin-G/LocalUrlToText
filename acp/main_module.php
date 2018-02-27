<?php
/**
*
* @package phpBB Extension - martin localurltotext
* @copyright (c) 2018 Martin ( https://github.com/Mar-tin-G )
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace martin\localurltotext\acp;

class main_module
{
	public $u_action;
	public $tpl_name;
	public $page_title;

	public function main($id, $mode)
	{
		global $phpbb_container, $template, $request, $config;

		/* @var \phpbb\language\language $lang */
		$lang = $phpbb_container->get('language');

		$this->tpl_name = 'localurltotext_body';
		$this->page_title = $lang->lang('ACP_LOCALURLTOTEXT_TITLE');
		add_form_key('martin/localurltotext');

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key('martin/localurltotext'))
			{
				$lang->add_lang('acp/common');
				trigger_error('FORM_INVALID', E_USER_WARNING);
			}

			$config->set('martin_localurltotext_forum',	$request->variable('martin_localurltotext_forum', '', true));
			$config->set('martin_localurltotext_topic',	$request->variable('martin_localurltotext_topic', '', true));
			$config->set('martin_localurltotext_post',	$request->variable('martin_localurltotext_post', '', true));
			$config->set('martin_localurltotext_user',	$request->variable('martin_localurltotext_user', '', true));
			$config->set('martin_localurltotext_page',	$request->variable('martin_localurltotext_page', '', true));
			$config->set('martin_localurltotext_cpf',	$request->variable('martin_localurltotext_cpf', 0));

			trigger_error($lang->lang('ACP_LOCALURLTOTEXT_SETTING_SAVED'). adm_back_link($this->u_action));
		}

		$template->assign_vars(array(
			'U_ACTION'						=> $this->u_action,
			'MARTIN_LOCALURLTOTEXT_FORUM'	=> $config['martin_localurltotext_forum'],
			'MARTIN_LOCALURLTOTEXT_TOPIC'	=> $config['martin_localurltotext_topic'],
			'MARTIN_LOCALURLTOTEXT_POST'	=> $config['martin_localurltotext_post'],
			'MARTIN_LOCALURLTOTEXT_USER'	=> $config['martin_localurltotext_user'],
			'MARTIN_LOCALURLTOTEXT_PAGE'	=> $config['martin_localurltotext_page'],
			'MARTIN_LOCALURLTOTEXT_CPF'		=> $config['martin_localurltotext_cpf'],
		));
	}
}
