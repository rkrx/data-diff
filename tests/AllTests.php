<?php
namespace DataDiff;

class AllTests extends \PHPUnit_Framework_TestCase {
	/** @var DiffStorage */
	private $ds = null;

	/**
	 */
	public function setUp() {
		parent::setUp();

		$this->ds = new DiffStorage([
			'client_id' => 'integer',
		], [
			'client_id' => 'integer',
			'description' => 'string',
			'total' => 'money',
		]);

		for($i=2; $i <= 501; $i++) {
			$row = ['client_id' => $i, 'description' => 'Dies ist ein Test', 'total' => $i === 50 ? 60 : 59.98999, 'test' => $i % 2];
			$this->ds->storeA()->addRow($row);
		}
		for($i=1; $i <= 500; $i++) {
			$row = ['client_id' => $i, 'description' => 'Dies ist ein Test', 'total' => 59.98999, 'test' => $i % 3];
			$this->ds->storeB()->addRow($row);
		}
	}

	/**
	 */
	public function testNew() {
		$res = $this->ds->storeA()->getNew();
		foreach($res as $key => $value) {
			$this->assertEquals(501, $value['client_id']);
		}
	}

	/**
	 */
	public function testChanges() {
		$res = $this->ds->storeA()->getChanged();
		foreach($res as $key => $value) {
			$this->assertEquals(50, $value['client_id']);
		}
	}

	/**
	 */
	public function testMissing() {
		$res = $this->ds->storeA()->getMissing();
		foreach($res as $key => $value) {
			$this->assertEquals(1, $value['client_id']);
		}
	}
}
