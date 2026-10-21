<?php
/**
 * Portfolio — phpBB extension
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
	'UCP_PORTFOLIO_TITLE'		=> 'Portfolio',
	'UCP_PORTFOLIO_WATERMARK'	=> 'Watermark on my photos',
]);
