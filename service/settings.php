<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\service;

/**
 * Réglages de l'extension, stockés dans SA PROPRE table (portfolio_config)
 * et non dans phpbb_config : aucune interférence avec la configuration du forum.
 *
 * S'utilise comme \phpbb\config\config ($settings['portfolio_xxx'], $settings->set()) mais N'EN HÉRITE PAS
 * (v1.8.6) : un second service de type \phpbb\config\config empêchait l'« autowiring » d'autres extensions
 * (« Cannot autowire service … references class phpbb\config\config but no such service exists »).
 */
class settings implements \ArrayAccess, \IteratorAggregate, \Countable
{
	/** @var array */
	protected $config = [];

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var string */
	protected $table;

	public function __construct(\phpbb\db\driver\driver_interface $db, $table)
	{
		$this->db = $db;
		$this->table = $table;

		// Pendant l'installation ou après une suppression des données, la table peut ne pas encore exister :
		// on démarre alors sans réglages au lieu de bloquer le forum.
		$values = [];
		$this->db->sql_return_on_error(true);
		$result = $this->db->sql_query('SELECT config_name, config_value FROM ' . $this->table);
		$this->db->sql_return_on_error(false);
		if ($result)
		{
			while ($row = $this->db->sql_fetchrow($result))
			{
				$values[$row['config_name']] = $row['config_value'];
			}
			$this->db->sql_freeresult($result);
		}

		$this->config = $values;
	}

	#[\ReturnTypeWillChange]
	public function getIterator()
	{
		return new \ArrayIterator($this->config);
	}

	#[\ReturnTypeWillChange]
	public function offsetExists($key)
	{
		return isset($this->config[$key]);
	}

	#[\ReturnTypeWillChange]
	public function offsetSet($key, $value)
	{
		$this->config[$key] = $value;
	}

	#[\ReturnTypeWillChange]
	public function offsetUnset($key)
	{
		unset($this->config[$key]);
	}

	#[\ReturnTypeWillChange]
	public function count()
	{
		return count($this->config);
	}

	#[\ReturnTypeWillChange]
	public function offsetGet($key)
	{
		return isset($this->config[$key]) ? $this->config[$key] : '';
	}

	public function delete($key, $use_cache = true)
	{
		$this->db->sql_query('DELETE FROM ' . $this->table . " WHERE config_name = '" . $this->db->sql_escape($key) . "'");
		unset($this->config[$key]);
	}

	public function set($key, $value, $use_cache = true)
	{
		$value = (string) $value;
		$sql = 'UPDATE ' . $this->table . "
			SET config_value = '" . $this->db->sql_escape($value) . "'
			WHERE config_name = '" . $this->db->sql_escape($key) . "'";
		$this->db->sql_query($sql);

		if (!$this->db->sql_affectedrows() && !isset($this->config[$key]))
		{
			$this->db->sql_query('INSERT INTO ' . $this->table . ' ' . $this->db->sql_build_array('INSERT', [
				'config_name'	=> $key,
				'config_value'	=> $value,
			]));
		}

		$this->config[$key] = $value;
	}

	public function set_atomic($key, $old_value, $new_value, $use_cache = true)
	{
		if (isset($this->config[$key]) && $this->config[$key] != $old_value)
		{
			return false;
		}
		$this->set($key, $new_value);

		return true;
	}

	public function increment($key, $increment, $use_cache = true)
	{
		$this->set($key, (int) $this->offsetGet($key) + (int) $increment);
	}
}
