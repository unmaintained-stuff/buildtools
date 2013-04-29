<?php


namespace CyberSpectrum\TestHarness\Contao;


class DbWorker
{
	/**
	 * @var TestCase
	 */
	protected $testcase;

	/**
	 * @var \Contao\Database
	 */
	protected $database;

	public function __construct($testcase, $database)
	{
		$this->testcase = $testcase;
		$this->database = $database;
	}

	protected function listTables()
	{
		return @$this->database->listTables(null, true);
	}

	protected function execute($query)
	{
		@$this->database->executeUncached($query);
	}

	public function createSchema()
	{
		foreach ($this->getSchemaStatements() as $group)
		{
			foreach ($group as $command)
			{
				$this->execute(
					str_replace(
						'DEFAULT CHARSET=utf8;',
						'DEFAULT CHARSET=utf8 COLLATE ' . $GLOBALS['TL_CONFIG']['dbCollation'] . ';',
						$command
					)
				);
			}
		}

		foreach ($this->listTables() as $table)
		{
			$this->execute('TRUNCATE ' . $table);
		}
	}

	public function importData($filename)
	{
		// Import data
		$file = file_get_contents($filename);

		preg_match_all('/CREATE TABLE.*;\s*\n/sU', $file, $sql);

		foreach ($sql[0] as $query)
		{
			$this->execute($query);
		}

		preg_match_all('/INSERT.*;\s*\n/sU', $file, $sql);

		foreach ($sql[0] as $query)
		{
			$this->execute($query);
		}
	}

	protected function getSchemaStatements()
	{
		$drop = array();
		$create = array();
		$return = array();

		$sql_current = $this->getFromDb();
		if ($this->testcase->isContao3())
		{
			$sql_target = $this->getFromDca();
		}
		else {
			$sql_target = array();
		}
		$sql_legacy = $this->getFromFile();

		// Manually merge the legacy definitions (see #4766)
		if (!empty($sql_legacy))
		{
			foreach ($sql_legacy as $table=>$categories)
			{
				foreach ($categories as $category=>$fields)
				{
					if (is_array($fields))
					{
						foreach ($fields as $name=>$sql)
						{
							$sql_target[$table][$category][$name] = $sql;
						}
					}
					else
					{
						$sql_target[$table][$category] = $fields;
					}
				}
			}
		}

		// Create tables
		foreach (array_diff(array_keys($sql_target), array_keys($sql_current)) as $table)
		{
			$return['CREATE'][] = "CREATE TABLE `" . $table . "` (\n  " . implode(",\n  ", $sql_target[$table]['TABLE_FIELDS']) . (!empty($sql_target[$table]['TABLE_CREATE_DEFINITIONS']) ? ',' . "\n  " . implode(",\n  ", $sql_target[$table]['TABLE_CREATE_DEFINITIONS']) : '') . "\n)" . $sql_target[$table]['TABLE_OPTIONS'] . ';';
			$create[] = $table;
		}

		// Add or change fields
		foreach ($sql_target as $k=>$v)
		{
			if (in_array($k, $create))
			{
				continue;
			}

			// Fields
			if (is_array($v['TABLE_FIELDS']))
			{
				foreach ($v['TABLE_FIELDS'] as $kk=>$vv)
				{
					if (!isset($sql_current[$k]['TABLE_FIELDS'][$kk]))
					{
						$return['ALTER_ADD'][] = 'ALTER TABLE `'.$k.'` ADD '.$vv.';';
					}
					elseif ($sql_current[$k]['TABLE_FIELDS'][$kk] != $vv)
					{
						$return['ALTER_CHANGE'][] = 'ALTER TABLE `'.$k.'` CHANGE `'.$kk.'` '.$vv.';';
					}
				}
			}

			// Create definitions
			if (is_array($v['TABLE_CREATE_DEFINITIONS']))
			{
				foreach ($v['TABLE_CREATE_DEFINITIONS'] as $kk=>$vv)
				{
					if (!isset($sql_current[$k]['TABLE_CREATE_DEFINITIONS'][$kk]))
					{
						$return['ALTER_ADD'][] = 'ALTER TABLE `'.$k.'` ADD '.$vv.';';
					}
					elseif ($sql_current[$k]['TABLE_CREATE_DEFINITIONS'][$kk] != str_replace('FULLTEXT ', '', $vv))
					{
						$return['ALTER_CHANGE'][] = 'ALTER TABLE `'.$k.'` DROP INDEX `'.$kk.'`, ADD '.$vv.';';
					}
				}
			}

			// Move auto_increment fields to the end of the array
			if (isset($return['ALTER_ADD']) && is_array($return['ALTER_ADD']))
			{
				foreach (preg_grep('/auto_increment/i', $return['ALTER_ADD']) as $kk=>$vv)
				{
					unset($return['ALTER_ADD'][$kk]);
					$return['ALTER_ADD'][$kk] = $vv;
				}
			}

			if (isset($return['ALTER_CHANGE']) && is_array($return['ALTER_CHANGE']))
			{
				foreach (preg_grep('/auto_increment/i', $return['ALTER_CHANGE']) as $kk=>$vv)
				{
					unset($return['ALTER_CHANGE'][$kk]);
					$return['ALTER_CHANGE'][$kk] = $vv;
				}
			}
		}

		// Drop tables
		foreach (array_diff(array_keys($sql_current), array_keys($sql_target)) as $table)
		{
			$return['DROP'][] = 'DROP TABLE `'.$table.'`;';
			$drop[] = $table;
		}

		// Drop fields
		foreach ($sql_current as $k=>$v)
		{
			if (!in_array($k, $drop))
			{
				// Create definitions
				if (is_array($v['TABLE_CREATE_DEFINITIONS']))
				{
					foreach ($v['TABLE_CREATE_DEFINITIONS'] as $kk=>$vv)
					{
						if (!isset($sql_target[$k]['TABLE_CREATE_DEFINITIONS'][$kk]))
						{
							$return['ALTER_DROP'][] = 'ALTER TABLE `'.$k.'` DROP INDEX `'.$kk.'`;';
						}
					}
				}

				// Fields
				if (is_array($v['TABLE_FIELDS']))
				{
					foreach ($v['TABLE_FIELDS'] as $kk=>$vv)
					{
						if (!isset($sql_target[$k]['TABLE_FIELDS'][$kk]))
						{
							$return['ALTER_DROP'][] = 'ALTER TABLE `'.$k.'` DROP `'.$kk.'`;';
						}
					}
				}
			}
		}

		return $return;
	}

	/**
	 * Get the DCA table settings from the DCA cache
	 *
	 * @return array An array of DCA table settings
	 */
	protected function getFromDca()
	{
		$arrReturn = array();
		$arrTables = @\Contao\DcaExtractor::createAllExtracts();

		foreach ($arrTables as $strTable => $objTable)
		{
			$arrReturn[$strTable] = $objTable->getDbInstallerArray();
		}

		return $arrReturn;
	}


	/**
	 * Get the DCA table settings from the database.sql files
	 *
	 * @return array An array of DCA table settings
	 */
	protected function getFromFile()
	{
		$table = '';
		$return = array();

		// Get all SQL files
		foreach (scan(TL_ROOT . '/system/modules') as $strModule)
		{
			if (strncmp($strModule, '.', 1) === 0 || strncmp($strModule, '__', 2) === 0)
			{
				continue;
			}

			$strFile = TL_ROOT . '/system/modules/' . $strModule . '/config/database.sql';

			if (!file_exists($strFile))
			{
				continue;
			}

			$data = file($strFile);

			foreach ($data as $k=>$v)
			{
				$key_name = array();
				$subpatterns = array();

				// Unset comments and empty lines
				if (preg_match('/^[#-]+/', $v) || !strlen(trim($v)))
				{
					unset($data[$k]);
					continue;
				}

				// Store the table names
				if (preg_match('/^CREATE TABLE `([^`]+)`/i', $v, $subpatterns))
				{
					$table = $subpatterns[1];
				}
				// Get the table options
				elseif ($table != '' && preg_match('/^\)([^;]+);/', $v, $subpatterns))
				{
					$return[$table]['TABLE_OPTIONS'] = $subpatterns[1];
					$table = '';
				}
				// Add the fields
				elseif ($table != '')
				{
					preg_match('/^[^`]*`([^`]+)`/', trim($v), $key_name);
					$first = preg_replace('/\s[^\n\r]+/', '', $key_name[0]);
					$key = $key_name[1];

					// Create definitions
					if (in_array($first, array('KEY', 'PRIMARY', 'PRIMARY KEY', 'FOREIGN', 'FOREIGN KEY', 'INDEX', 'UNIQUE', 'FULLTEXT', 'CHECK')))
					{
						if (strncmp($first, 'PRIMARY', 7) === 0)
						{
							$key = 'PRIMARY';
						}

						$return[$table]['TABLE_CREATE_DEFINITIONS'][$key] = preg_replace('/,$/', '', trim($v));
					}
					else
					{
						$return[$table]['TABLE_FIELDS'][$key] = preg_replace('/,$/', '', trim($v));
					}
				}
			}
		}

		// HOOK: allow third-party developers to modify the array (see #3281)
		if (isset($GLOBALS['TL_HOOKS']['sqlGetFromFile']) && is_array($GLOBALS['TL_HOOKS']['sqlGetFromFile']))
		{
			foreach ($GLOBALS['TL_HOOKS']['sqlGetFromFile'] as $callback)
			{
				$this->import($callback[0]);
				$return = $this->$callback[0]->$callback[1]($return);
			}
		}

		return $return;
	}


	/**
	 * Get the current database structure
	 *
	 * @return array An array of tables and fields
	 */
	protected function getFromDB()
	{
		// We do not want to restrict to tl_* table names here. We assume, in a test environment, we have complete
		// control over the defined database and therefore may savely destroy anything in there to have a defined
		// state.
		// $tables = preg_grep('/^tl_/', $this->listTables());
		$tables = $this->listTables();

		if (empty($tables))
		{
			return array();
		}

		$return = array();

		foreach ($tables as $table)
		{
			// The Contao Database classes spit out notices like there is no evil.
			$fields = @$this->database->listFields($table, true);

			foreach ($fields as $field)
			{
				$name = $field['name'];
				$field['name'] = '`'.$field['name'].'`';

				if ($field['type'] != 'index')
				{
					unset($field['index']);

					// Field type
					if (isset($field['length']))
					{
						$field['type'] .= '(' . $field['length'] . (isset($field['precision']) && ($field['precision'] != '') ? ',' . $field['precision'] : '') . ')';

						unset($field['length']);
						unset($field['precision']);
					}

					// Default values
					if (in_array(strtolower($field['type']), array('text', 'tinytext', 'mediumtext', 'longtext', 'blob', 'tinyblob', 'mediumblob', 'longblob')) || stristr($field['extra'], 'auto_increment') || $field['default'] === null || strtolower($field['default']) == 'null')
					{
						unset($field['default']);
					}
					// Date/time constants (see #5089)
					elseif (in_array(strtolower($field['default']), array('current_date', 'current_time', 'current_timestamp')))
					{
						$field['default'] = "default " . $field['default'];
					}
					// Everything else
					else
					{
						$field['default'] = "default '" . $field['default'] . "'";
					}

					$return[$table]['TABLE_FIELDS'][$name] = trim(implode(' ', $field));
				}

				// Indices
				if (isset($field['index']) && $field['index_fields'])
				{
					$index_fields = implode('`, `', $field['index_fields']);

					switch ($field['index'])
					{
						case 'UNIQUE':
							if ($name == 'PRIMARY')
							{
								$return[$table]['TABLE_CREATE_DEFINITIONS'][$name] = 'PRIMARY KEY  (`'.$index_fields.'`)';
							}
							else
							{
								$return[$table]['TABLE_CREATE_DEFINITIONS'][$name] = 'UNIQUE KEY `'.$name.'` (`'.$index_fields.'`)';
							}
							break;

						case 'FULLTEXT':
							$return[$table]['TABLE_CREATE_DEFINITIONS'][$name] = 'FULLTEXT KEY `'.$name.'` (`'.$index_fields.'`)';
							break;

						default:
							$return[$table]['TABLE_CREATE_DEFINITIONS'][$name] = 'KEY `'.$name.'` (`'.$index_fields.'`)';
							break;
					}

					unset($field['index_fields']);
					unset($field['index']);
				}
			}
		}

		return $return;
	}
}
