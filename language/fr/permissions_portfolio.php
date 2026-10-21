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
	'ACL_CAT_PORTFOLIO'				=> 'Portfolio',

	'ACL_U_PORTFOLIO_VIEW'			=> 'Peut voir le portfolio',
	'ACL_U_PORTFOLIO_CREATE_ALBUM'	=> 'Peut créer ses propres albums et sous-albums',
	'ACL_U_PORTFOLIO_UPLOAD'		=> 'Peut envoyer des photos',
	'ACL_U_PORTFOLIO_NOAPPROVE'		=> 'Peut publier des photos sans validation',
	'ACL_U_PORTFOLIO_RATE'			=> 'Peut noter les photos',
	'ACL_U_PORTFOLIO_COMMENT'		=> 'Peut commenter les photos',
	'ACL_U_PORTFOLIO_SHARE'			=> 'Peut partager ses albums avec des co-gestionnaires',
	'ACL_U_PORTFOLIO_DELETE'		=> 'Peut supprimer ses propres photos et albums (mise à la corbeille)',
	'ACL_U_PORTFOLIO_RESTORE'		=> 'Peut restaurer ses photos depuis la corbeille',
	'ACL_U_PORTFOLIO_DOWNLOAD'		=> 'Peut télécharger les photos originales',
	'ACL_U_PORTFOLIO_EXPORT'		=> 'Peut exporter ses albums en ZIP',
	'ACL_U_PORTFOLIO_UNLIMITED'		=> 'N’est pas soumis aux quotas (photos et albums)',

	'ACL_M_PORTFOLIO_APPROVE'		=> 'Peut valider les photos en attente',
	'ACL_M_PORTFOLIO_EDIT'			=> 'Peut voir, modifier et déplacer toutes les photos',
	'ACL_M_PORTFOLIO_DELETE'		=> 'Peut mettre à la corbeille toutes les photos (sans limite de suppression)',
	'ACL_M_PORTFOLIO_COMMENTS'		=> 'Peut supprimer tous les commentaires',

	'ACL_A_PORTFOLIO_MANAGE'		=> 'Peut gérer le portfolio (réglages, albums publics, droits par groupe)',
	'ACL_A_PORTFOLIO_TRASH'			=> 'Peut valider la suppression définitive et restaurer les photos de la corbeille',
	// v1.5.0
	'ACL_U_PORTFOLIO_REACT'					=> 'Peut réagir aux photos (👍 ❤️ 😀…)',
	'ACL_U_PORTFOLIO_PROFILE_LINK'			=> 'Voit le lien vers la galerie dans les profils et les messages',
	// v1.6.0
	'ACL_U_PORTFOLIO_VIS_GROUPS'			=> 'Peut réserver un album à des groupes choisis',
]);
