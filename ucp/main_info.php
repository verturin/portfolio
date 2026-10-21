<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\ucp;

class main_info
{
	public function module()
	{
		return [
			'filename'	=> '\verturin\portfolio\ucp\main_module',
			'title'		=> 'UCP_PORTFOLIO_TITLE',
			'modes'		=> [
				'watermark'	=> [
					'title'	=> 'UCP_PORTFOLIO_WATERMARK',
					'auth'	=> 'ext_verturin/portfolio',
					'cat'	=> ['UCP_PORTFOLIO_TITLE'],
				],
			],
		];
	}
}
