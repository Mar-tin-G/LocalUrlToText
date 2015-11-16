<?php
/**
*
* @package phpBB Extension - martin localurltotext
* @copyright (c) 2015 Martin ( https://github.com/Martin-G- )
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
* French translation by Galixte (http://www.galixte.com)
*
*/

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

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
));