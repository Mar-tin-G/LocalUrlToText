<?php
/**
*
* @package phpBB Extension - martin localurltotext
* @copyright (c) 2018 Martin ( https://github.com/Mar-tin-G )
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
* French translation by Galixte (http://www.galixte.com)
*
*/

/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine
//
// Some characters you may want to copy&paste:
// ’ « » “ ” …
//

$lang = array_merge($lang, array(
	'ACP_LOCALURLTOTEXT_TITLE'			=> 'Adresse URL locale en texte',
	'ACP_LOCALURLTOTEXT_SETTINGS'		=> 'Paramètres',
	'ACP_LOCALURLTOTEXT_SETTING_SAVED'	=> 'Les paramètres ont été sauvegardés avec succès !',
	'ACP_LOCALURLTOTEXT_FORUM'			=> 'Espace réservé pour le texte du lien du forum',
	'ACP_LOCALURLTOTEXT_FORUM_EXPLAIN'	=> 'Saisir l’espace réservé, HTML est autorisé. La variable disponible est : {FORUM_NAME}.',
	'ACP_LOCALURLTOTEXT_TOPIC'			=> 'Espace réservé pour le texte du lien du sujet',
	'ACP_LOCALURLTOTEXT_TOPIC_EXPLAIN'	=> 'Saisir l’espace réservé, HTML est autorisé. Les variables disponibles sont : {TOPIC_TITLE}, {FORUM_NAME}.',
	'ACP_LOCALURLTOTEXT_POST'			=> 'Espace réservé pour le texte du lien du message',
	'ACP_LOCALURLTOTEXT_POST_EXPLAIN'	=> 'Saisir l’espace réservé, HTML est autorisé. Les variables disponibles sont : {USER_NAME}, {USER_COLOUR}, {POST_SUBJECT}, {TOPIC_TITLE}, {POST_OR_TOPIC_TITLE}, {FORUM_NAME}.',
	'ACP_LOCALURLTOTEXT_USER'			=> 'Espace réservé pour le texte du lien de l’utilisateur',
	'ACP_LOCALURLTOTEXT_USER_EXPLAIN'	=> 'Saisir l’espace réservé, HTML est autorisé. Les variables disponibles sont : {USER_NAME}, {USER_COLOUR}.',
	'ACP_LOCALURLTOTEXT_PAGE'			=> 'Placeholder for Pages link text',
	'ACP_LOCALURLTOTEXT_PAGE_EXPLAIN'	=> 'For the <a href="https://www.phpbb.com/customise/db/extension/pages/" target="_blank">Pages extension</a>. Enter placeholder, HTML is allowed. Available variables: {PAGE_TITLE}',
	'ACP_LOCALURLTOTEXT_CPF'			=> 'Replace links within Custom profile fields',
	'ACP_LOCALURLTOTEXT_CPF_EXPLAIN'	=> 'Whether local links should be replaced within Custom profile fields. Only Custom profile fields of type "URL (Link)" are considered.',
));
