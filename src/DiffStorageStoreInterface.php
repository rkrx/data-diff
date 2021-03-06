<?php
namespace DataDiff;

use Generator;
use Traversable;
use Countable;
use IteratorAggregate;

interface DiffStorageStoreInterface extends Countable, IteratorAggregate {
	/**
	 * @param array $data
	 * @param array|null $translation
	 * @param callable|null $duplicateKeyHandler
	 */
	public function addRow(array $data, ?array $translation = null, ?callable $duplicateKeyHandler = null);

	/**
	 * @param Traversable|array $rows
	 * @param array|null $translation
	 * @param callable|null $duplicateKeyHandler
	 * @return $this
	 */
	public function addRows($rows, ?array $translation = null, ?callable $duplicateKeyHandler = null);

	/**
	 * Returns true whenever there is any changed, added or removed data available
	 */
	public function hasAnyChanges(): bool;

	/**
	 * Get all rows, that are present in this store, but not in the other
	 *
	 * @param array $arguments
	 * @return DiffStorageStoreRow[]|Generator
	 */
	public function getNew(array $arguments = []);

	/**
	 * Get all rows, that have a different value hash in the other store
	 *
	 * @param array $arguments
	 * @return DiffStorageStoreRow[]|Generator
	 */
	public function getUnchanged(array $arguments = []);

	/**
	 * Get all rows, that have a different value hash in the other store
	 *
	 * @param array $arguments
	 * @return DiffStorageStoreRow[]|Generator
	 */
	public function getChanged(array $arguments = []);

	/**
	 * Get all rows, that are present in this store, but not in the other and
	 * get all rows, that have a different value hash in the other store
	 *
	 * @param array $arguments
	 * @return DiffStorageStoreRow[]|Generator
	 */
	public function getNewOrChanged(array $arguments = []);

	/**
	 * Get all rows, that are present in the other store, but not in this
	 *
	 * @param array $arguments
	 * @return DiffStorageStoreRow[]|Generator
	 */
	public function getMissing(array $arguments = []);

	/**
	 * Get all rows, that are present in this store, but not in the other and
	 * get all rows, that have a different value hash in the other store and
	 * get all rows, that are present in the other store, but not in this
	 *
	 * @param array $arguments
	 * @return DiffStorageStoreRow[]|Generator
	 */
	public function getNewOrChangedOrMissing(array $arguments = []);

	/**
	 * @return $this
	 */
	public function clearAll();

	/**
	 * @return Traversable|array[]
	 */
	public function getIterator();

	/**
	 * @return int
	 */
	public function count(): int;
}
