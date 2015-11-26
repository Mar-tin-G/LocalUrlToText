<?php
/**
*
* @package phpBB Extension - martin localurltotext
* @copyright (c) 2015 Martin ( https://github.com/Mar-tin-G )
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
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
));
