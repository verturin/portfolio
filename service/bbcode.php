<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\service;

/**
 * BBCode [portfolio] pour insérer une photo dans les messages du forum (v1.6.0).
 *
 *   [portfolio]12[/portfolio]          image moyenne (taille par défaut)
 *   [portfolio=mini]12[/portfolio]     vignette
 *   [portfolio=medium]12[/portfolio]   image moyenne
 *   [portfolio=large]12[/portfolio]    grande image (pleine largeur du message)
 *
 * L'image passe par le contrôleur du Portfolio : droits de l'album et filigrane respectés,
 * l'original n'est jamais exposé.
 */
class bbcode
{
	const SIZES = ['mini', 'medium', 'large'];

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var string */
	protected $table;

	public function __construct(\phpbb\db\driver\driver_interface $db, $bbcodes_table = '')
	{
		$this->db = $db;
		$this->table = $bbcodes_table ?: BBCODES_TABLE;
	}

	/**
	 * @param string $board_url Adresse du forum (generate_board_url())
	 * @return bool
	 */
	public function install($board_url)
	{
		$base = rtrim($board_url, '/') . '/app.php/portfolio/';

		// Une seule définition : la taille est facultative (moyenne par défaut). Deux BBCode « portfolio »
		// et « portfolio= » porteraient le même nom pour le moteur de rendu et l'un effacerait l'autre.
		$this->db->sql_query('DELETE FROM ' . $this->table . " WHERE bbcode_tag = 'portfolio='");

		$ok = $this->save('portfolio', [
			'bbcode_helpline'		=> 'PORTFOLIO_BBCODE_HELPLINE',
			'display_on_posting'	=> 1,
			'bbcode_match'			=> '[portfolio={IDENTIFIER;optional;defaultValue=medium}]{NUMBER}[/portfolio]',
			'bbcode_tpl'			=> $this->template($base, '{NUMBER}', '{IDENTIFIER}'),
			'first_pass_match'		=> '!\[portfolio(?:\=([a-zA-Z0-9-_]+))?\]([0-9]+)\[/portfolio\]!i',
			'first_pass_replace'	=> '[portfolio=${1}:$uid]${2}[/portfolio:$uid]',
			'second_pass_match'		=> '!\[portfolio\=([a-zA-Z0-9-_]*):$uid\]([0-9]+)\[/portfolio:$uid\]!s',
			'second_pass_replace'	=> $this->template($base, '${2}', '${1}'),
		]);

		return $ok;
	}

	public function uninstall()
	{
		$this->db->sql_query('DELETE FROM ' . $this->table . " WHERE bbcode_tag IN ('portfolio', 'portfolio=')");
	}

	public function is_installed()
	{
		$sql = 'SELECT bbcode_match FROM ' . $this->table . " WHERE bbcode_tag = 'portfolio'";
		$result = $this->db->sql_query($sql);
		$match = (string) $this->db->sql_fetchfield('bbcode_match');
		$this->db->sql_freeresult($result);

		return strpos($match, 'defaultValue=medium') !== false;
	}

	protected function template($base, $id, $size)
	{
		return '<a href="' . $base . 'photo/' . $id . '" class="pf-bb pf-bb-' . $size . '"><img src="' . $base . 'embed/' . $id . '/' . $size . '" alt="Photo ' . $id . '" class="pf-bb__img" style="max-width:100%;height:auto"></a>';
	}

	protected function save($tag, array $fields)
	{
		$fields['bbcode_tag'] = $tag;

		$sql = 'SELECT bbcode_id FROM ' . $this->table . " WHERE LOWER(bbcode_tag) = '" . $this->db->sql_escape($tag) . "'";
		$result = $this->db->sql_query($sql);
		$id = (int) $this->db->sql_fetchfield('bbcode_id');
		$this->db->sql_freeresult($result);

		if ($id)
		{
			$this->db->sql_query('UPDATE ' . $this->table . ' SET ' . $this->db->sql_build_array('UPDATE', $fields) . ' WHERE bbcode_id = ' . $id);

			return true;
		}

		$result = $this->db->sql_query('SELECT MAX(bbcode_id) AS n FROM ' . $this->table);
		$max = (int) $this->db->sql_fetchfield('n');
		$this->db->sql_freeresult($result);

		$fields['bbcode_id'] = max(NUM_CORE_BBCODES + 1, $max + 1);
		if ($fields['bbcode_id'] > BBCODE_LIMIT)
		{
			return false;
		}

		$this->db->sql_query('INSERT INTO ' . $this->table . ' ' . $this->db->sql_build_array('INSERT', $fields));

		return true;
	}
}
