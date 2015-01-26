<?php
/**
 * @link http://buildwithcraft.com/
 * @copyright Copyright (c) 2013 Pixel & Tonic, Inc.
 * @license http://buildwithcraft.com/license
 */

namespace craft\app\db\mysql;

use Craft;
use craft\app\enums\ConfigCategory;

/**
 * Class MysqlSchema
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class Schema extends \yii\db\mysql\Schema
{
	// Properties
	// =========================================================================

	/**
	 * @var int The maximum length that objects' names can be.
	 */
	public $maxObjectNameLength = 64;

	// Public Methods
	// =========================================================================

	/**
	 * @inheritDoc \yii\base\Object::init()
	 */
	public function init()
	{
		parent::init();

		$this->typeMap['mediumtext'] = 'mediumtext';
	}

	/**
	 * Creates a query builder for the database.
	 * This method may be overridden by child classes to create a DBMS-specific query builder.
	 * @return QueryBuilder query builder instance
	 */
	public function createQueryBuilder()
	{
		return new QueryBuilder($this->db);
	}

	/**
	 * @param $table
	 * @param $column
	 * @param $type
	 *
	 * @return string
	 */
	public function addColumnFirst($table, $column, $type)
	{
		$type = $this->getColumnType($type);

		$sql = 'ALTER TABLE '.$this->quoteTableName($table)
		       .' ADD '.$this->quoteColumnName($column).' '
		       .$this->getColumnType($type).' '
		       .'FIRST';

		return $sql;
	}

	/**
	 * @param $table
	 * @param $column
	 * @param $type
	 * @param $after
	 *
	 * @return string
	 */
	public function addColumnAfter($table, $column, $type, $after)
	{
		$type = $this->getColumnType($type);

		$sql = 'ALTER TABLE '.$this->quoteTableName($table).' ADD '.$this->quoteColumnName($column).' '.$this->getColumnType($type);

		if ($after)
		{
			$sql .= ' AFTER '.$this->quoteTableName($after);
		}

		return $sql;
	}

	/**
	 * @param $table
	 * @param $column
	 * @param $type
	 * @param $before
	 *
	 * @return string
	 */
	public function addColumnBefore($table, $column, $type, $before)
	{
		$tableInfo = $this->getTable($table, true);
		$columns = array_keys($tableInfo->columns);
		$beforeIndex = array_search($before, $columns);

		if ($beforeIndex === false)
		{
			return $this->addColumn($table, $column, $type);
		}
		else if ($beforeIndex > 0)
		{
			$after = $columns[$beforeIndex-1];
			return $this->addColumnAfter($table, $column, $type, $after);
		}
		else
		{
			return $this->addColumnFirst($table, $column, $type);
		}
	}

	/**
	 * @param string $table
	 * @param string $column
	 * @param string $type
	 * @param mixed $newName
	 * @param mixed $after
	 *
	 * @return string
	 */
	public function alterColumn($table, $column, $type, $newName = null, $after = null)
	{
		if (!$newName)
		{
			$newName = $column;
		}

		return 'ALTER TABLE '.$this->quoteTableName($table).' CHANGE '
			. $this->quoteColumnName($column).' '
			. $this->quoteColumnName($newName).' '
			. $this->getColumnType($type)
			. ($after ? ' AFTER '.$this->quoteColumnName($after) : '');
	}

	/**
	 * @param $table
	 * @param $columns
	 * @param $rows
	 *
	 * @return mixed
	 */
	public function insertAll($table, $columns, $rows)
	{
		$params = [];

		// Quote the column names
		foreach ($columns as $colIndex => $column)
		{
			$columns[$colIndex] = $this->quoteColumnName($column);
		}

		$valuesSql = '';

		foreach ($rows as $rowIndex => $row)
		{
			if ($rowIndex != 0)
			{
				$valuesSql .= ', ';
			}

			$valuesSql .= '(';

			foreach ($columns as $colIndex => $column)
			{
				if ($colIndex != 0)
				{
					$valuesSql .= ', ';
				}

				if (isset($row[$colIndex]) && $row[$colIndex] !== null)
				{
					$key = ':row'.$rowIndex.'_col'.$colIndex;
					$params[$key] = $row[$colIndex];
					$valuesSql .= $key;
				}
				else
				{
					$valuesSql .= 'NULL';
				}
			}

			$valuesSql .= ')';
		}

		// Generate the SQL
		$sql = 'INSERT INTO '.$this->quoteTableName($table).' ('.implode(', ', $columns).') VALUES '.$valuesSql;

		return ['query' => $sql, 'params' => $params];
	}

	/**
	 * @param string $table   The name of the table (including prefix, or wrapped in "{{" and "}}").
	 * @param array  $columns An array of columns.
	 * @param string $options Any additional SQL to append to the end of the query.
	 * @param string $engine  The engine the table should use ("InnoDb" or "MyISAM"). Default is "InnoDb".
	 *
	 * @return string The full SQL for creating a table.
	 */
	public function createTable($table, $columns, $options = null, $engine = 'InnoDb')
	{
		$cols = [];
		$options = 'ENGINE='.$engine.' DEFAULT CHARSET='.Craft::$app->config->get('charset', ConfigCategory::Db).' COLLATE='.Craft::$app->config->get('collation', ConfigCategory::Db).($options ? ' '.$options : '');

		foreach ($columns as $name => $type)
		{
			if (is_string($name))
			{
				$cols[] = "\t".$this->quoteColumnName($name).' '.$this->getColumnType($type);
			}
			else
			{
				$cols[] = "\t".$type;
			}
		}

		$sql = "CREATE TABLE ".$this->quoteTableName($table)." (\n".implode(",\n", $cols)."\n)";

		return $options === null ? $sql : $sql.' '.$options;
	}

	/**
	 * Builds a SQL statement for dropping a DB table if it exists.
	 *
	 * @param string $table
	 *
	 * @return string
	 */
	public function dropTableIfExists($table)
	{
		return 'DROP TABLE IF EXISTS '.$this->quoteTableName($table);
	}

	/**
	 * Returns the SQL for finding/replacing text.
	 *
	 * @param string $table
	 * @param string $column
	 * @param string $find
	 * @param string $replace
	 *
	 * @return array
	 */
	public function replace($table, $column, $find, $replace)
	{
		$sql = 'UPDATE '.$this->quoteTableName($table).' SET '.$this->quoteColumnName($column).' = REPLACE('.$this->quoteColumnName($column).',  :find, :replace)';
		$params = [':find' => (string) $find, ':replace' => (string) $replace];

		return ['query' => $sql, 'params' => $params];
	}

	/**
	 * Quotes a database name for use in a query.
	 *
	 * @param $name
	 *
	 * @return string
	 */
	public function quoteDatabaseName($name)
	{
		return '`'.$name.'`';
	}

	// Protected Methods
	// =========================================================================

	/**
	 * Returns all table names in the database which start with the tablePrefix.
	 *
	 * @param string $schema
	 *
	 * @return string
	 */
	protected function findTableNames($schema = null)
	{
		if (!$schema)
		{
			$likeSql = ($this->db->tablePrefix ? ' LIKE \''.$this->db->tablePrefix.'%\'' : '');
			return $this->db->createCommand()->setText('SHOW TABLES'.$likeSql)->queryColumn();
		}
		else
		{
			return parent::findTableNames();
		}
	}
}
