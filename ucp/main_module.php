<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\ucp;

class main_module
{
	public $u_action;
	public $tpl_name;
	public $page_title;

	public function main($id, $mode)
	{
		global $phpbb_container;

		/** @var \verturin\portfolio\controller\ucp_controller $controller */
		$controller = $phpbb_container->get('verturin.portfolio.ucp_controller');
		$phpbb_container->get('language')->add_lang('common', 'verturin/portfolio');

		$this->tpl_name = '@verturin_portfolio/ucp_portfolio_watermark';
		$this->page_title = 'UCP_PORTFOLIO_WATERMARK';
		$controller->set_page_url($this->u_action);
		$controller->watermark();
	}
}
