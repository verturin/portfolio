<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\acp;

class main_module
{
	public $u_action;
	public $tpl_name;
	public $page_title;

	public function main($id, $mode)
	{
		global $phpbb_container;

		/** @var \phpbb\language\language $language */
		$language = $phpbb_container->get('language');
		$language->add_lang(['common', 'acp'], 'verturin/portfolio');

		/** @var \verturin\portfolio\controller\acp_controller $controller */
		$controller = $phpbb_container->get('verturin.portfolio.controller.acp');
		$controller->set_page_url($this->u_action);

		switch ($mode)
		{
			case 'albums':
				$this->tpl_name = 'acp_portfolio_albums';
				$this->page_title = $language->lang('ACP_PORTFOLIO_ALBUMS');
				$controller->albums();
			break;

			case 'watermark':
				$this->tpl_name = 'acp_portfolio_watermark';
				$this->page_title = $language->lang('ACP_PORTFOLIO_WATERMARK');
				$controller->watermark();
			break;

			case 'pages':
				$this->tpl_name = 'acp_portfolio_pages';
				$this->page_title = $language->lang('ACP_PORTFOLIO_PAGES');
				$controller->pages();
			break;

			case 'departed':
				$this->tpl_name = 'acp_portfolio_departed';
				$this->page_title = $language->lang('ACP_PORTFOLIO_DEPARTED');
				$controller->departed();
			break;

			case 'migrate':
				$this->tpl_name = 'acp_portfolio_migrate';
				$this->page_title = $language->lang('ACP_PORTFOLIO_MIGRATE');
				$controller->migrate();
			break;

			case 'backup':
				$this->tpl_name = 'acp_portfolio_backup';
				$this->page_title = $language->lang('ACP_PORTFOLIO_BACKUP');
				$controller->backup();
			break;

			case 'trash':
				$this->tpl_name = 'acp_portfolio_trash';
				$this->page_title = $language->lang('ACP_PORTFOLIO_TRASH');
				$controller->trash();
			break;

			case 'settings':
			default:
				$this->tpl_name = 'acp_portfolio_settings';
				$this->page_title = $language->lang('ACP_PORTFOLIO_SETTINGS');
				$controller->settings();
			break;
		}
	}
}
