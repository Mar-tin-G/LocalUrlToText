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
	'ACP_LOCALURLTOTEXT_SETTING_SAVED'	=> 'Les paramètres ont été sauvegardés avec succès !',
	'ACP_LOCALURLTOTEXT_FORUM'			=> 'Texte de remplacement des liens des forums',
	'ACP_LOCALURLTOTEXT_FORUM_EXPLAIN'	=> 'Permet de saisir le texte de remplacement des liens vers les forums, le langage HTML est autorisé. La variable disponible est : {FORUM_NAME}.',
	'ACP_LOCALURLTOTEXT_TOPIC'			=> 'Texte de remplacement des liens des sujets',
	'ACP_LOCALURLTOTEXT_TOPIC_EXPLAIN'	=> 'Permet de saisir le texte de remplacement des liens vers les sujets, le langage HTML est autorisé. Les variables disponibles sont : {TOPIC_TITLE}, {FORUM_NAME}.',
	'ACP_LOCALURLTOTEXT_POST'			=> 'Texte de remplacement des liens des messages',
	'ACP_LOCALURLTOTEXT_POST_EXPLAIN'	=> 'Permet de saisir le texte de remplacement des liens vers les messages, le langage HTML est autorisé. Les variables disponibles sont : {USER_NAME}, {USER_COLOUR}, {POST_SUBJECT}, {TOPIC_TITLE}, {POST_OR_TOPIC_TITLE}, {FORUM_NAME}.',
	'ACP_LOCALURLTOTEXT_USER'			=> 'Texte de remplacement des liens des profils des utilisateurs',
	'ACP_LOCALURLTOTEXT_USER_EXPLAIN'	=> 'Permet de saisir le texte de remplacement des liens vers les profils des utilisateurs, le langage HTML est autorisé. Les variables disponibles sont : {USER_NAME}, {USER_COLOUR}.',
	'ACP_LOCALURLTOTEXT_PAGE'			=> 'Texte de remplacement des liens des pages',
	'ACP_LOCALURLTOTEXT_PAGE_EXPLAIN'	=> 'Permet de saisir le texte de remplacement des liens vers les pages générées par l’extension « <a href="https://www.phpbb.com/customise/db/extension/pages/" target="_blank">Pages »</a>, le langage HTML est autorisé. La variable disponible est : {PAGE_TITLE}.',
	'ACP_LOCALURLTOTEXT_CPF'			=> 'Remplacer les liens des champs de profil personnalisés',
	'ACP_LOCALURLTOTEXT_CPF_EXPLAIN'	=> 'Permet de saisir le texte de remplacement des liens des champs de profil personnalisés. Uniquement les champs de profil personnalisés de type « URL (Lien) » sont pris en compte.',
));
