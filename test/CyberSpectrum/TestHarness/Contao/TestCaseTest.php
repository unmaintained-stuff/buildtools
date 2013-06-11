<?php

namespace CyberSpectrum\TestHarness\Contao;

use \CyberSpectrum\TestHarness\Reflector;

class TestCaseTest extends \PHPUnit_Framework_TestCase
{
	public function testNoticeErrorHandler()
	{
		$test = new TestCase();

		$exception=false;
		try
		{
			Reflector::invoke($test, 'handleError', E_NOTICE, 'trigger notice', 'some.file', '23');
		}
		catch (\PHPUnit_Framework_Error_Notice $e)
		{
			$exception = true;
		}

		$this->assertTrue($exception, 'The notice was not being passed to the error handler.');

		$test = new TestCase();

		$exception=false;
		try
		{
			Reflector::invoke($test, 'handleError', E_NOTICE, 'trigger notice', '/vendor/contao/core/some.file', '23');
		}
		catch (\PHPUnit_Framework_Error_Notice $e)
		{
			$exception = true;
		}

		$this->assertFalse($exception, 'The notice was being passed to the error handler.');

		$test = new TestCase();

		$exception=false;
		try
		{
			Reflector::invoke($test, 'handleError', E_WARNING, 'trigger warning', 'some.file', '23');
		}
		catch (\PHPUnit_Framework_Error_Warning $e)
		{
			$exception = true;
		}

		$this->assertTrue($exception, 'The warning was not being passed to the error handler.');

		$test = new TestCase();

		$exception=false;
		try
		{
			Reflector::invoke($test, 'handleError', E_WARNING, 'trigger warning', '/vendor/contao/core/some.file', '23');
		}
		catch (\PHPUnit_Framework_Error_Warning $e)
		{
			$exception = true;
		}

		$this->assertTrue($exception, 'The warning was not being passed to the error handler.');
	}


	public function testTranslatePath()
	{
		$test = new TestCase();

		Reflector::setPropertyValue($test, 'isContao3', false);

		$this->assertEquals(
			'/contao/root/foo/bar',
			Reflector::invoke($test, 'translatePath', 'TL_ROOT/foo/bar', '/contao/root')
		);

		$this->assertEquals(
			'/contao/root/tl_files/foo/bar',
			Reflector::invoke($test, 'translatePath', 'TL_FILES/foo/bar', '/contao/root')
		);

		Reflector::setPropertyValue($test, 'isContao3', true);


		$this->assertEquals(
			'/contao/root/foo/bar',
			Reflector::invoke($test, 'translatePath', 'TL_ROOT/foo/bar', '/contao/root')
		);

		$this->assertEquals(
			'/contao/root/files/foo/bar',
			Reflector::invoke($test, 'translatePath', 'TL_FILES/foo/bar', '/contao/root')
		);

		try
		{
			Reflector::invoke($test, 'translatePath', 'INVALID/foo/bar', '/contao/root');
		}
		catch (\Exception $e)
		{
			if ($e->getMessage() == 'Invalid path encountered. it must start with TL_ROOT or TL_FILES.')
			{
				$exception = true;
			}
			else{
				throw $e;
			}
		}

		$this->assertEquals(true, $exception);
	}

	/**
	 * @covers \CyberSpectrum\TestHarness\Contao\TestCase::isContaoAt
	 */
	public function testContaoAt()
	{
		$test = new TestCase();

		$this->assertFalse(
			Reflector::invoke($test, 'isContaoAt', '/'),
			'Contao found at / - this should not be.'
		);

		$this->assertFalse(
			Reflector::invoke($test, 'isContaoAt', '/tmp'),
			'Contao found at /tmp - this should not be.'
		);

		$should = CONTAO_DIRECTORY_TEST;
		$this->assertTrue(
			Reflector::invoke($test, 'isContaoAt', $should),
			sprintf('Contao not found at "%s" (%s) - this should not be.',
				$should,
				getcwd() . '/' . $should
			)
		);
	}

	public function testgetContaoRoot()
	{
		$should = realpath(__DIR__ . '/../../../../vendor/contao/core');

		$test = new TestCase();

		$this->assertEquals(
			$should,
			Reflector::invoke($test, 'getContaoRoot', '/'),
			'Contao not found at expected location.'
		);
	}

	public function testInstallIntoContao()
	{
		$test = new TestCase();

		$tmp = realpath(__DIR__ . '/../../../../') . '/tmp' . time();

		mkdir($tmp, 0777);

		Reflector::setPropertyValue($test, 'contao', $tmp);

		$this->assertEquals(
			$tmp,
			Reflector::invoke($test, 'getContaoRoot'),
			'Contao fakeroot not installed.'
		);

		Reflector::invoke($test, 'installIntoContao', __FILE__, 'TL_ROOT/' . basename(__FILE__));
		$this->assertFileExists($tmp . '/'. basename(__FILE__), 'Copied file does not exist.');
		$this->assertFileEquals(__FILE__, $tmp . '/'. basename(__FILE__), 'Copied file is not equal.');

		Reflector::invoke($test, 'installIntoContao', __DIR__, 'TL_ROOT/testcopyfolder');

		Reflector::invoke($test, 'tearDown');

		foreach (scandir($tmp) as $file)
		{
			if (!in_array($file, array('.', '..')))
			{
				$this->fail('tearDown did not remove all files/folders.');
				break;
			}
		}

		// Cleanup the mess we made.
		rmdir($tmp);
	}

	/**
	 * @depends testContaoAt
	 */
	public function testPatchContao3()
	{
		if (!(file_exists(CONTAO_DIRECTORY_TEST) && is_dir(CONTAO_DIRECTORY_TEST)))
		{
			$this->markTestSkipped('constant defining core location undefined.');
			return;
		}

		$tmp = realpath(__DIR__ . '/../../../../') . '/tmp' . time();
		mkdir($tmp, 0777);
		mkdir($tmp . '/system', 0777);
		mkdir($tmp . '/system/config', 0777);

		$test = new TestCase();

		Reflector::setPropertyValue($test, 'contao', $tmp);
		Reflector::setPropertyValue($test, 'isContao3', true);

		$this->assertEquals(
			$tmp,
			Reflector::invoke($test, 'getContaoRoot'),
			'Contao fakeroot not installed.'
		);

		copy(CONTAO_DIRECTORY_TEST . '/system/initialize.php', $tmp . '/system/initialize.php');

		Reflector::invoke($test, 'loadInitialize', 'FE');

		$this->assertContains('if(!defined(\'TL_ROOT\'))', file_get_contents($tmp . '/system/initialize.test.php'));

		Reflector::invoke($test, 'tearDown');

		// Cleanup the mess we made.
		$this->assertTrue(unlink($tmp . '/system/initialize.php'));
		$this->assertTrue(rmdir($tmp . '/system/config'));
		$this->assertTrue(rmdir($tmp . '/system'));
		$this->assertTrue(rmdir($tmp));
	}

	/**
	 * @depends testContaoAt
	 */
	public function testBootFromConstant()
	{
		$test = new TestCase();

		// Load class before we unwind the autoloaders.
		spl_autoload_call('CyberSpectrum\\TestHarness\\Reflector');

		if (!(file_exists(CONTAO_DIRECTORY_TEST) && is_dir(CONTAO_DIRECTORY_TEST)))
		{
			$this->markTestSkipped('constant defining core location undefined.');
			return;
		}

		define('CONTAO_DIRECTORY', CONTAO_DIRECTORY_TEST);

		try
		{
			$booted = Reflector::invoke($test, 'bootContao');
		} catch(\Exception $e)
		{
			throw $e;
		}

		$this->assertTrue($booted, 'Contao booted.');

		if (!defined('VERSION'))
		{
			$this->fail('Contao not booted.');
		}

		$this->assertNotEmpty(Reflector::invoke($test, 'getContaoRoot'), 'Contao root not found.');
		$this->assertTrue(class_exists('Config'), 'Contao Config class not found');
		$this->assertTrue(defined('TL_MODE'), 'TL_MODE is undefined.');
		$this->assertTrue(defined('VERSION'), 'VERSION is undefined.');
		$this->assertTrue(function_exists('deserialize'), 'deserialize() is undefined.');
	}

	/**
	 * @depends testContaoAt
	 */
	public function testBootAutomatic()
	{
		$test = new TestCase();

		$this->assertTrue(Reflector::invoke($test, 'bootContao'));

		if (!defined('VERSION'))
		{
			$this->fail('Contao not booted.');
		}

		$this->assertNotEmpty(Reflector::invoke($test, 'getContaoRoot'), 'Contao root not found.');

		$this->assertTrue(class_exists('Config'), 'Contao Config class not found');
		$this->assertTrue(defined('TL_MODE'), 'TL_MODE is undefined.');
		$this->assertTrue(defined('VERSION'), 'VERSION is undefined.');
		$this->assertTrue(function_exists('deserialize'), 'deserialize() is undefined.');
	}

	/**
	 * @depends testBootAutomatic
	 */
	public function testBootWrongPath()
	{
		$test = new TestCase();

		define('CONTAO_DIRECTORY', '/tmp');

		// Move the initialize out of the way so we will correctly fail.
		rename(CONTAO_DIRECTORY_TEST . '/system/initialize.php', CONTAO_DIRECTORY_TEST . '/system/initialize.php.test');

		// Now booting should fail for sure.
		$bootSuccess = true;
		try
		{
			Reflector::invoke($test, 'bootContao');
		}
		catch (\Exception $e)
		{
			$bootSuccess = false;
		}

		// Rename it back.
		rename(CONTAO_DIRECTORY_TEST . '/system/initialize.php.test', CONTAO_DIRECTORY_TEST . '/system/initialize.php');

		if ($bootSuccess)
		{
			$this->fail('Contao booted.');
		}

		$this->assertFalse(class_exists('Config'), 'Contao Config class not found');
		$this->assertFalse(defined('TL_MODE'), 'TL_MODE is undefined.');
		$this->assertFalse(defined('VERSION'), 'VERSION is undefined.');
		$this->assertFalse(function_exists('deserialize'), 'deserialize() is undefined.');
	}

	/**
	 * @depends testBootAutomatic
	 */
	public function testBootFrontend()
	{
		$test = new TestCase();

		$boot = new \ReflectionMethod('\CyberSpectrum\TestHarness\Contao\TestCase', 'bootContao');
		$boot->setAccessible(true);

		if (!Reflector::invoke($test, 'bootContao', 'FE'))
		{
			$this->fail('Contao not booted.');
		}

		$this->assertEquals(TL_MODE, 'FE');

		$this->assertNotEmpty(Reflector::invoke($test, 'getContaoRoot'), 'Contao root not found.');
		$this->assertTrue(class_exists('Config'), 'Contao Config class not found');
		$this->assertTrue(defined('TL_MODE'), 'TL_MODE is undefined.');
		$this->assertTrue(defined('VERSION'), 'VERSION is undefined.');
		$this->assertTrue(function_exists('deserialize'), 'deserialize() is undefined.');
		$this->assertArrayHasKey('TL_LANG', $GLOBALS);
	}

	/**
	 * @depends testBootAutomatic
	 */
	public function testBootBackend()
	{
		$test = new TestCase();

		$boot = new \ReflectionMethod('\CyberSpectrum\TestHarness\Contao\TestCase', 'bootContao');
		$boot->setAccessible(true);

		if (!Reflector::invoke($test, 'bootContao', 'BE'))
		{
			$this->fail('Contao not booted.');
		}

		$this->assertEquals(TL_MODE, 'BE');

		$this->assertNotEmpty(Reflector::invoke($test, 'getContaoRoot'), 'Contao root not found.');
		$this->assertTrue(class_exists('Config'), 'Contao Config class not found');
		$this->assertTrue(defined('TL_MODE'), 'TL_MODE is undefined.');
		$this->assertTrue(defined('VERSION'), 'VERSION is undefined.');
		$this->assertTrue(function_exists('deserialize'), 'deserialize() is undefined.');
		$this->assertArrayHasKey('TL_LANG', $GLOBALS);
	}

	/**
	 * @depends testBootAutomatic
	 */
	public function testBootNoend()
	{
		$test = new TestCase();

		$boot = new \ReflectionMethod('\CyberSpectrum\TestHarness\Contao\TestCase', 'bootContao');
		$boot->setAccessible(true);

		if (!Reflector::invoke($test, 'bootContao', 'NO'))
		{
			$this->fail('Contao not booted.');
		}

		$this->assertEquals(TL_MODE, 'NO');

		$this->assertNotEmpty(Reflector::invoke($test, 'getContaoRoot'), 'Contao root not found.');
		$this->assertTrue(class_exists('Config'), 'Contao Config class not found');
		$this->assertTrue(defined('TL_MODE'), 'TL_MODE is undefined.');
		$this->assertTrue(defined('VERSION'), 'VERSION is undefined.');
		$this->assertTrue(function_exists('deserialize'), 'deserialize() is undefined.');
	}

	/**
	 * @depends testBootAutomatic
	 */
	public function testContaoVersion()
	{
		$test = new TestCase();

		Reflector::invoke($test, 'bootContao');

		if (!defined('VERSION'))
		{
			$this->fail('Contao not booted.');
		}

		$isContao2 = new \ReflectionMethod('\CyberSpectrum\TestHarness\Contao\TestCase', 'isContao2');
		$isContao2->setAccessible(true);
		$isContao3 = new \ReflectionMethod('\CyberSpectrum\TestHarness\Contao\TestCase', 'isContao3');
		$isContao3->setAccessible(true);

		if (version_compare('3.0', VERSION, '<='))
		{
			$this->assertFalse(Reflector::invoke($test, 'isContao2'), 'Contao2');
			$this->assertTrue (Reflector::invoke($test, 'isContao3'), 'Contao3');
		}
		else
		{
			$this->assertTrue (Reflector::invoke($test, 'isContao2'), 'Contao2');
			$this->assertFalse(Reflector::invoke($test, 'isContao3'), 'Contao3');
		}
	}

	/**
	 * @depends testBootAutomatic
	 */
	public function testDatabaseNotAvailable()
	{
		$configKeys = array
		(
			'DB_TYPE',
			'DB_HOST',
			'DB_USER',
			'DB_PASS',
			'DB_NAME',
			'DB_PORT',
		);

		$keep = array();
		foreach ($configKeys as $key)
		{
			$keep[$key] = $GLOBALS[$key];
			unset($GLOBALS[$key]);
			putenv($key.'=');
		}

		$test = new TestCase();

		$this->assertFalse(Reflector::invoke($test, 'prepareDatabaseConfig'));

		Reflector::invoke($test, 'bootContao');

		if (!defined('VERSION'))
		{
			$this->fail('Contao not booted.');
		}

		$this->assertFalse(Reflector::invoke($test, 'prepareDatabaseConfig'));

		$this->assertFalse(Reflector::invoke($test, 'connectDatabase'));
	}

	/**
	 * @depends testBootAutomatic
	 */
	public function testDatabaseAvailable()
	{
		$configKeys = array
		(
			'DB_TYPE',
			'DB_HOST',
			'DB_USER',
			'DB_PASS',
			'DB_NAME',
			'DB_PORT',
		);

		foreach ($configKeys as $key)
		{
			if (!isset($GLOBALS[$key]))
			{
				$this->markTestSkipped(sprintf('Database config from phpunit.xml not complete - missing key %s.', $key));
				return;
			}
		}

		$test = new TestCase();

		$needDb = Reflector::getMethod($test, 'prepareDatabaseConfig');

		$this->assertFalse($needDb->invoke($test));

		Reflector::invoke($test, 'bootContao');

		if (!defined('VERSION'))
		{
			$this->fail('Contao not booted.');
		}

		$this->assertTrue($needDb->invoke($test));

		$this->assertTrue(Reflector::invoke($test, 'connectDatabase'));
	}

	/**
	 * depends testDatabaseAvailable
	 */
	public function testDatabaseWorkerAvailable()
	{
		$test = new TestCase();

		Reflector::invoke($test, 'bootContao');

		if (!defined('VERSION'))
		{
			$this->fail('Contao not booted.');
		}

		$this->assertTrue(Reflector::invoke($test, 'connectDatabase'));

		$worker = Reflector::invoke($test, 'getDbWorker');

		$this->assertNotNull($worker, 'getDbWorker()');

		$worker2 = Reflector::invoke($test, 'getDbWorker');
		// Second check, the same object should get returned.
		$this->assertSame($worker, $worker2, 'Calling getDbWorker() again should return the same instance');
	}

	/**
	 * depends testDatabaseAvailable
	 */
	public function testDatabaseImportSchema()
	{
		$test = new TestCase();

		Reflector::invoke($test, 'bootContao');

		if (!defined('VERSION'))
		{
			$this->fail('Contao not booted.');
		}

		$this->assertTrue(Reflector::invoke($test, 'connectDatabase'));

		$worker = Reflector::invoke($test, 'getDbWorker');

		$this->assertNotNull($worker, 'getDbWorker()');
	}
}
