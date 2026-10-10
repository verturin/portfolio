<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\acp;

class main_info
{
	public function module()
	{
		return [
			'filename'	=> '\verturin\portfolio\acp\main_module',
			'title'		=> 'ACP_PORTFOLIO_TITLE',
			'modes'		=> [
				'settings'	=> [
					'title'	=> 'ACP_PORTFOLIO_SETTINGS',
					'auth'	=> 'ext_verturin/portfolio && acl_a_portfolio_manage',
					'cat'	=> ['ACP_PORTFOLIO_TITLE'],
				],
				'albums'	=> [
					'title'	=> 'ACP_PORTFOLIO_ALBUMS',
					'auth'	=> 'ext_verturin/portfolio && acl_a_portfolio_manage',
					'cat'	=> ['ACP_PORTFOLIO_TITLE'],
				],
				'watermark'	=> [
					'title'	=> 'ACP_PORTFOLIO_WATERMARK',
					'auth'	=> 'ext_verturin/portfolio && acl_a_portfolio_manage',
					'cat'	=> ['ACP_PORTFOLIO_TITLE'],
				],
				'pages'		=> [
					'title'	=> 'ACP_PORTFOLIO_PAGES',
					'auth'	=> 'ext_verturin/portfolio && acl_a_portfolio_manage',
					'cat'	=> ['ACP_PORTFOLIO_TITLE'],
				],
				'departed'	=> [
					'title'	=> 'ACP_PORTFOLIO_DEPARTED',
					'auth'	=> 'ext_verturin/portfolio && acl_a_portfolio_manage',
					'cat'	=> ['ACP_PORTFOLIO_TITLE'],
				],
				'migrate'	=> [
					'title'	=> 'ACP_PORTFOLIO_MIGRATE',
					'auth'	=> 'ext_verturin/portfolio && acl_a_portfolio_manage',
					'cat'	=> ['ACP_PORTFOLIO_TITLE'],
				],
				'backup'	=> [
					'title'	=> 'ACP_PORTFOLIO_BACKUP',
					'auth'	=> 'ext_verturin/portfolio && acl_a_portfolio_manage',
					'cat'	=> ['ACP_PORTFOLIO_TITLE'],
				],
				'trash'		=> [
					'title'	=> 'ACP_PORTFOLIO_TRASH',
					'auth'	=> 'ext_verturin/portfolio && acl_a_portfolio_trash',
					'cat'	=> ['ACP_PORTFOLIO_TITLE'],
				],
			],
		];
	}
}
