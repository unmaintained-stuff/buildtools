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

		$this->assertTrue(Reflector::invoke($this->testCase, 'connectDatabase'));

		$this->worker = Reflector::invoke($this->testCase, 'getDbWorker');
	}

	public function testDropTables()
	{
		$this->bootStrap();

		$tables = Reflector::invoke($this->worker, 'listTables');

		foreach ($tables as $table)
		{
			Reflector::invoke($this->worker, 'execute', 'DROP TABLE ' . $table);
		}
	}

	public function testCreateTable()
	{
		$this->bootStrap();

		$tables = Reflector::invoke($this->worker, 'listTables');

		foreach ($tables as $table)
		{
			Reflector::invoke($this->worker, 'execute', 'DROP TABLE ' . $table);
		}
	}




	public function testListTables()
	{
		$this->bootStrap();

		//$this->worker
	}


}