<?php
/**
*
* @package phpBB Extension - martin localurltotext
* @copyright (c) 2018 Martin ( https://github.com/Mar-tin-G )
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
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
	'ACP_LOCALURLTOTEXT_TITLE'			=> 'Local URL To Text',
	'ACP_LOCALURLTOTEXT_SETTINGS'		=> 'Einstellungen',
	'ACP_LOCALURLTOTEXT_SETTING_SAVED'	=> 'Die Einstellungen wurden erfolgreich gespeichert!',
	'ACP_LOCALURLTOTEXT_FORUM'			=> 'Platzhalter für den Forums-Linktext',
	'ACP_LOCALURLTOTEXT_FORUM_EXPLAIN'	=> 'Eingabe des Platzhalters, HTML ist möglich. Mögliche Variablen: {FORUM_NAME}',
	'ACP_LOCALURLTOTEXT_TOPIC'			=> 'Platzhalter für den Themen-Linktext',
	'ACP_LOCALURLTOTEXT_TOPIC_EXPLAIN'	=> 'Eingabe des Platzhalters, HTML ist möglich. Mögliche Variablen: {TOPIC_TITLE}, {FORUM_NAME}',
	'ACP_LOCALURLTOTEXT_POST'			=> 'Platzhalter für den Beitrags-Linktext',
	'ACP_LOCALURLTOTEXT_POST_EXPLAIN'	=> 'Eingabe des Platzhalters, HTML ist möglich. Mögliche Variablen: {USER_NAME}, {USER_COLOUR}, {POST_SUBJECT}, {TOPIC_TITLE}, {POST_OR_TOPIC_TITLE}, {FORUM_NAME}',
	'ACP_LOCALURLTOTEXT_USER'			=> 'Platzhalter für den Mitglieds-Linktext',
	'ACP_LOCALURLTOTEXT_USER_EXPLAIN'	=> 'Eingabe des Platzhalters, HTML ist möglich. Mögliche Variablen: {USER_NAME}, {USER_COLOUR}',
	'ACP_LOCALURLTOTEXT_PAGE'			=> 'Platzhalter für den Pages-Linktext',
	'ACP_LOCALURLTOTEXT_PAGE_EXPLAIN'	=> 'Für die <a href="https://www.phpbb.com/customise/db/extension/pages/" target="_blank">Pages-Extension</a>. Eingabe des Platzhalters, HTML ist möglich. Mögliche Variablen: {PAGE_TITLE}',
	'ACP_LOCALURLTOTEXT_CPF'			=> 'Links in Benutzerdefinierten Profilfeldern ersetzen',
	'ACP_LOCALURLTOTEXT_CPF_EXPLAIN'	=> 'Gibt an, ob lokale Links innerhalb von Benutzerdefinierten Profilfeldern ersetzt werden sollen. Es werden nur Benutzerdefinierte Profilfelder vom Typ "URL (Link)" beachtet.',
));
