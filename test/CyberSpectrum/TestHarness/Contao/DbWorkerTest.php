<?php

namespace CyberSpectrum\TestHarness\Contao;

use \CyberSpectrum\TestHarness\Reflector;

class DbWorkerTest extends \PHPUnit_Framework_TestCase
{
	protected $testCase;

	protected $worker;

	protected function bootStrap()
	{
		$this->testCase = new TestCase();

		if (!(Reflector::invoke($this->testCase, 'bootContao') && defined('VERSION')))
		{
			$this->fail('Contao not booted.');
		}

		$this->assertTrue(Reflector::invoke($this->testCase, 'connectDatabase'),
			'Database not connected: ' . var_export($GLOBALS['TL_CONFIG']['dbHost'], true));

		$this->worker = Reflector::invoke($this->testCase, 'getDbWorker');
	}

	public function testGetConfig()
	{
		$this->testCase = new TestCase();

		if (!(Reflector::invoke($this->testCase, 'bootContao') && defined('VERSION')))
		{
			$this->fail('Contao not booted.');
		}

		// Initialize empty.
		putenv('DB_USER=');
		$GLOBALS['DB_USER'] = null;

		$this->assertEmpty(
			$value = Reflector::invoke($this->testCase, 'getConfigOption', 'DB_USER'),
			sprintf('Config value DB_USER is not empty but %s', var_export($value, true))
		);

		putenv('DB_USER=testuser');
		$this->assertEquals(
			'testuser',
			$value = Reflector::invoke($this->testCase, 'getConfigOption', 'DB_USER'),
			sprintf('Config value DB_USER from environment is %s', var_export($value, true))
		);

		putenv('DB_USER=');
		$GLOBALS['DB_USER'] = 'testuser';
		$this->assertEquals(
			'testuser',
			$value = Reflector::invoke($this->testCase, 'getConfigOption', 'DB_USER'),
			sprintf('Config value DB_USER from globals is %s', var_export($value, true))
		);
	}

	/**
	 * @depends testGetConfig
	 */
	public function testDropTables()
	{
		$this->bootStrap();

		$tables = Reflector::invoke($this->worker, 'listTables');

		foreach ($tables as $table)
		{
			Reflector::invoke($this->worker, 'execute', 'DROP TABLE ' . $table);
		}
	}

	/**
	 * @depends testGetConfig
	 */
	public function testCreateTable()
	{
		$this->bootStrap();

		$tables = Reflector::invoke($this->worker, 'listTables');

		foreach ($tables as $table)
		{
			Reflector::invoke($this->worker, 'execute', 'DROP TABLE ' . $table);
		}
	}

	/**
	 * @depends testGetConfig
	 */
	public function testListTables()
	{
		$this->bootStrap();

		//$this->worker
	}


}
