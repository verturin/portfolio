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
	'ACL_CAT_PORTFOLIO'				=> 'Portfolio',
	'ACL_U_PORTFOLIO_VIEW'			=> 'Can view the portfolio',
	'ACL_U_PORTFOLIO_CREATE_ALBUM'	=> 'Can create own albums and sub-albums',
	'ACL_U_PORTFOLIO_UPLOAD'		=> 'Can upload photos',
	'ACL_U_PORTFOLIO_NOAPPROVE'		=> 'Can publish photos without approval',
	'ACL_U_PORTFOLIO_RATE'			=> 'Can rate photos',
	'ACL_U_PORTFOLIO_COMMENT'		=> 'Can comment on photos',
	'ACL_U_PORTFOLIO_SHARE'			=> 'Can share own albums with co-managers',
	'ACL_U_PORTFOLIO_RESTORE'		=> 'Can restore own photos from the trash',
	'ACL_U_PORTFOLIO_DOWNLOAD'		=> 'Can download original photos',
	'ACL_U_PORTFOLIO_EXPORT'		=> 'Can export own albums as ZIP',
	'ACL_U_PORTFOLIO_UNLIMITED'		=> 'Is not subject to quotas (photos and albums)',
	'ACL_M_PORTFOLIO_APPROVE'		=> 'Can approve pending photos',
	'ACL_M_PORTFOLIO_EDIT'			=> 'Can view, edit and move all photos',
	'ACL_M_PORTFOLIO_DELETE'		=> 'Can trash all photos (no deletion limit)',
	'ACL_M_PORTFOLIO_COMMENTS'		=> 'Can delete all comments',
	'ACL_A_PORTFOLIO_MANAGE'		=> 'Can manage the portfolio (settings, public albums, group permissions)',
	'ACL_A_PORTFOLIO_TRASH'			=> 'Can permanently delete and restore photos from the trash',
	// v1.5.0
	'ACL_U_PORTFOLIO_REACT'					=> 'Can react to photos (👍 ❤️ 😀…)',
	'ACL_U_PORTFOLIO_PROFILE_LINK'			=> 'Sees the gallery link in profiles and posts',
	// v1.6.0
	'ACL_U_PORTFOLIO_VIS_GROUPS'			=> 'Can restrict an album to chosen groups',
]);
