<?php
/**
 * Portfolio — phpBB extension [English]
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
	'ACP_PORTFOLIO_SETTINGS'	=> 'Settings',
	'ACP_PORTFOLIO_ALBUMS'		=> 'Albums & permissions',
	'ACP_PORTFOLIO_WATERMARK'	=> 'Watermark',
	'ACP_PORTFOLIO_MIGRATE'	=> 'phpBB Gallery switch',
	'ACP_PORTFOLIO_BACKUP'	=> 'Backup',
	'ACP_PORTFOLIO_TRASH'		=> 'Trash',
	'ACP_PORTFOLIO_PAGES'						=> 'Pages in the board',
	'ACP_PORTFOLIO_DEPARTED'					=> 'Departed members',
]);
