<?php
namespace DataDiff;

use Exception;
use PDO;

class DiffStorage {
	/** @var PDO */
	private $pdo = null;
	/** @var null */
	private $missingColumnValue;
	/** @var DiffStorageStore */
	private $storeA = null;
	/** @var DiffStorageStore */
	private $storeB = null;
	/** @var array */
	private $keys;

	/**
	 * Predefined types:
	 *     - integer
	 *     - string
	 *     - bool
	 *     - float
	 *     - double
	 *     - money
	 *
	 * @param array $keySchema
	 * @param array $valueSchema
	 * @param mixed $missingColumnValue
	 * @param callable|null $duplicateKeyHandler
	 * @throws Exception
	 */
	public function __construct(array $keySchema, array $valueSchema, $missingColumnValue = null, $duplicateKeyHandler = null) {
		$this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
		$this->pdo->exec('CREATE TABLE data_store (s_ab TEXT, s_key TEXT, s_data_hash TEXT, s_data TEXT, s_sort INT, PRIMARY KEY(s_ab, s_key))');
		$this->missingColumnValue = $missingColumnValue;
		$keySchema = $this->buildSchema($keySchema);
		$valueSchema = $this->buildSchema($valueSchema);

		if($duplicateKeyHandler === null) {
			$duplicateKeyHandler = function ($newData = null, $oldData = null) { return array_merge($oldData, $newData); };
		}

		$this->storeA = new DiffStorageStore($this->pdo, $keySchema, $valueSchema, $missingColumnValue, $duplicateKeyHandler, 'a', 'b');
		$this->storeB = new DiffStorageStore($this->pdo, $keySchema, $valueSchema, $missingColumnValue, $duplicateKeyHandler, 'b', 'a');
	}

	/**
	 * @return array
	 */
	public function getKeys() {
		return $this->keys;
	}

	/**
	 * @return DiffStorageStore
	 */
	public function storeA() {
		return $this->storeA;
	}

	/**
	 * @return DiffStorageStore
	 */
	public function storeB() {
		return $this->storeB;
	}

	/**
	 * @param array $schema
	 * @return array
	 * @throws Exception
	 */
	private function buildSchema($schema) {
		ksort($schema);
		foreach($schema as $columnName => $converter) {
			if(is_string($converter)) {
				if($converter === 'integer') {
					$schema[$columnName] = 'intval';
				} elseif($converter === 'string') {
					$schema[$columnName] = function ($val) { return (string) $val; };
				} elseif($converter === 'bool') {
					$schema[$columnName] = function ($val) { return ((bool) $val) ? 1 : 0; };
				} elseif($converter === 'float') {
					$schema[$columnName] = 'floatval';
				} elseif($converter === 'double') {
					$schema[$columnName] = 'doubleval';
				} elseif($converter === 'money') {
					$schema[$columnName] = function ($val) { return number_format((float) $val, 2, '.', ''); };
				} elseif(is_callable($converter)) {
				} else {
					throw new Exception('Unknown converter: ' . $converter);
				}
			}
		}
		return $schema;
	}
}
