<?php
/**
 * Portfolio — extension phpBB [Français]
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'ACP_PORTFOLIO_TITLE'		=> 'Portfolio',
	'ACP_PORTFOLIO_SETTINGS'	=> 'Réglages',
	'ACP_PORTFOLIO_ALBUMS'		=> 'Albums et droits',
	'ACP_PORTFOLIO_WATERMARK'	=> 'Filigrane',
	'ACP_PORTFOLIO_MIGRATE'	=> 'Bascule phpBB Gallery',
	'ACP_PORTFOLIO_BACKUP'	=> 'Sauvegarde',
	'ACP_PORTFOLIO_TRASH'		=> 'Corbeille',
	'ACP_PORTFOLIO_PAGES'						=> 'Pages dans le forum',
]);
