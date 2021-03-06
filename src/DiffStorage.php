<?php
namespace DataDiff;

use DataDiff\Exceptions\EmptySchemaException;
use DataDiff\Exceptions\InvalidSchemaException;
use Exception;
use PDO;
use PDOException;
use RuntimeException;

/**
 * @package DataDiff
 */
abstract class DiffStorage implements DiffStorageInterface, DiffStorageFieldTypeConstants {
	/** @var PDO */
	private $pdo;
	/** @var DiffStorageStore */
	private $storeA;
	/** @var DiffStorageStore */
	private $storeB;
	/** @var array */
	private $keys;
	/** @var array */
	private $valueKeys;

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
	 * @param array $options
	 *
	 * @throws EmptySchemaException
	 * @throws InvalidSchemaException
	 */
	public function __construct(array $keySchema, array $valueSchema, array $options) {
		$options = $this->defineOptionDefaults($options);
		$this->pdo = new PDO($options['dsn'], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
		$this->initSqlite();
		$this->compatibility();
		$this->buildTables();

		$this->keys = array_keys($keySchema);
		$this->valueKeys = array_keys($valueSchema);

		$sqlKeySchema = $this->buildSchema($keySchema);
		$sqlValueSchema = $this->buildSchema($valueSchema);

		$keyConverter = $this->buildConverter($keySchema);
		$valueConverter = $this->buildConverter($valueSchema);
		$converter = array_merge($keyConverter, $valueConverter);

		$this->storeA = new DiffStorageStore($this->pdo, $sqlKeySchema, $sqlValueSchema, $this->keys, $this->valueKeys, $converter, 'a', 'b', $options['duplicate_key_handler']);
		$this->storeB = new DiffStorageStore($this->pdo, $sqlKeySchema, $sqlValueSchema, $this->keys, $this->valueKeys, $converter, 'b', 'a', $options['duplicate_key_handler']);
	}

	/**
	 * @return array
	 */
	public function getKeys(): array {
		return $this->keys;
	}

	/**
	 * @return DiffStorageStore
	 */
	public function storeA(): DiffStorageStore {
		return $this->storeA;
	}

	/**
	 * @return DiffStorageStore
	 */
	public function storeB(): DiffStorageStore {
		return $this->storeB;
	}

	/**
	 * @param array $schema
	 * @return string
	 * @throws EmptySchemaException
	 * @throws InvalidSchemaException
	 */
	private function buildSchema(array $schema): string {
		$def = [];
		foreach($schema as $name => $type) {
			switch ($type) {
				case 'BOOL':
				case 'BOOLEAN':
					$def[] = sprintf('CASE WHEN CAST(:'.$name.' AS INT) = 0 THEN \'false\' ELSE \'true\' END');
					break;
				case 'INT':
				case 'INTEGER':
					$def[] = 'printf("%d", :'.$name.')';
					break;
				case 'FLOAT':
					$def[] = 'printf("%0.6f", :'.$name.')';
					break;
				case 'DOUBLE':
					$def[] = 'printf("%0.12f", :'.$name.')';
					break;
				case 'MONEY':
					$def[] = 'printf("%0.2f", :'.$name.')';
					break;
				case 'STR':
				case 'STRING':
					$def[] = '\'"\'||HEX(TRIM(:'.$name.'))||\'"\'';
					break;
				case 'MD5':
					$def[] = '\'"\'||md5(:'.$name.')||\'"\'';
					break;
				default:
					throw new InvalidSchemaException("Invalid type: {$type}");
			}
		}
		if(!count($def)) {
			throw new EmptySchemaException('Can\'t operate with empty schema');
		}
		return join('||"|"||', $def);
	}

	/**
	 * @param array $schema
	 * @return array
	 * @throws InvalidSchemaException
	 */
	private function buildConverter(array $schema): array {
		$def = [];
		foreach($schema as $name => $type) {
			switch ($type) {
				case 'BOOL':
				case 'BOOLEAN':
					$def[$name] = 'boolval';
					break;
				case 'INT':
				case 'INTEGER':
					$def[$name] = 'intval';
					break;
				case 'FLOAT':
					$def[$name] = function ($value) { return $value !== null ? (float) number_format((float) $value, 6, '.', '') : null; };
					break;
				case 'DOUBLE':
					$def[$name] = function ($value) { return $value !== null ? (double) number_format((double) $value, 12, '.', '') : null; };
					break;
				case 'MONEY':
					$def[$name] = function ($value) { return $value !== null ? number_format((float) $value, 2, '.', '') : null; };
					break;
				case 'STR':
				case 'STRING':
					$def[$name] = function ($value) { return $value !== null ? (string) $value : null; };
					break;
				case 'MD5':
					$def[$name] = function ($value) { return md5((string) $value); };
					break;
				default:
					throw new InvalidSchemaException("Invalid type: {$type}");
			}
		}
		return $def;
	}

	/**
	 * @throws RuntimeException
	 */
	private function compatibility() {
		try {
			if(!$this->testStatement('SELECT printf("%0.2f", 19.99999) AS res')) {
				$this->registerUDFunction('printf', 'sprintf');
			}

			if(!$this->testStatement('SELECT md5("aaa") AS md5res')) {
				$this->registerUDFunction('md5', 'md5');
			}
		} catch (Exception $e) {
			throw new RuntimeException($e->getMessage(), (int) $e->getCode(), $e);
		}
	}

	/**
	 * @param string $query
	 * @return bool
	 */
	private function testStatement(string $query): bool {
		try {
			return $this->pdo->query($query)->execute() !== false;
		} catch (PDOException $e) {
			return false;
		}
	}

	/**
	 * @param string $name
	 * @param mixed $callback
	 * @throws Exception
	 */
	private function registerUDFunction(string $name, $callback) {
		if(!method_exists($this->pdo, 'sqliteCreateFunction')) {
			throw new Exception('It is not possible to create user defined functions for rkr/data-diff\'s sqlite instance');
		}
		call_user_func([$this->pdo, 'sqliteCreateFunction'], $name, $callback);
	}

	/**
	 */
	private function initSqlite() {
		$tryThis = function ($query) {
			try {
				$this->pdo->exec($query);
			} catch (Exception $e) {
				// If the execution failed, go on anyways
			}
		};
		$tryThis("PRAGMA synchronous=OFF");
		$tryThis("PRAGMA count_changes=OFF");
		$tryThis("PRAGMA journal_mode=MEMORY");
		$tryThis("PRAGMA temp_store=MEMORY");
	}

	/**
	 */
	private function buildTables() {
		$this->pdo->exec('CREATE TABLE data_store (s_ab TEXT, s_key TEXT, s_value TEXT, s_data TEXT, s_sort INT, PRIMARY KEY(s_ab, s_key))');
		$this->pdo->exec('CREATE INDEX data_store_ab_index ON data_store (s_ab, s_key)');
		$this->pdo->exec('CREATE INDEX data_store_key_index ON data_store (s_key)');
	}

	/**
	 * @param array $options
	 * @return array
	 */
	private function defineOptionDefaults(array $options): array {
		if(!array_key_exists('dsn', $options)) {
			$options['dsn'] = 'sqlite::memory:';
		}
		if(!array_key_exists('duplicate_key_handler', $options)) {
			$options['duplicate_key_handler'] = null;
		}
		return $options;
	}
}
