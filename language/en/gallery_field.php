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
	// Profile field left by phpBB Gallery (“GALLERY: USERS_PERSONAL_ALBUMS”), v1.10.6
	'GALLERY'						=> 'Portfolio',
	'USERS_PERSONAL_ALBUMS'			=> 'See their photos',
]);
