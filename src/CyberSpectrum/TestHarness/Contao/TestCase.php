<?php

namespace CyberSpectrum\TestHarness\Contao;

use CyberSpectrum\TestHarness\Reflector;

class TestCase extends \PHPUnit_Framework_TestCase
{
	/**
	 * Path to contao root (TL_ROOT).
	 *
	 * @var string
	 */
	private static $contao;

	/**
	 * @var boolean
	 */
	private static $isContao3;

	/**
	 * Test local database worker.
	 *
	 * @var DbWorker
	 */
	private $dbWorker;

	/**
	 * Keeps track of all files we have installed via installIntoContao() calls.
	 *
	 * @var array
	 */
	private static $installedFiles = array();

	/**
	 * Checks whether the given path is an contao root.
	 *
	 * @param string $path Pathname to check.
	 *
	 * @return boolean
	 */
	protected static function isContaoAt($path)
	{
		if (file_exists($path . '/system/initialize.php'))
		{
			self::$contao    = realpath($path);
			self::$isContao3 = (is_dir($path . '/system/modules/core'));

			return true;
		}
		return false;
	}

	/**
	 * Initizalizes Contao by including the initialize file.
	 *
	 * @param string $mode Either FE for frontend or BE for backend.
	 *
	 * @return void
	 */
	private function loadInitialize($mode)
	{
		if (!file_exists(self::$contao . '/system/initialize.php'))
		{
			throw new \Exception('initialize.php not found in contao core directory.');
		}

		define('TL_MODE', $mode);

		// Not quite elegant, I know, but we really have to silent that error about TL_ROOT being already defined.
		// Therefore we patch the specific part of initialize.php.
		if ($this->isContao3())
		{
			file_put_contents(self::$contao . '/system/initialize.test.php',
				str_replace(
					'define(\'TL_ROOT\', dirname(__DIR__));' . "\n",
					'if(!defined(\'TL_ROOT\')) { define(\'TL_ROOT\', dirname(__DIR__)); }' . "\n",
					file_get_contents(self::$contao . '/system/initialize.php')
				)
			);
			// Touch the localconfig.php so Contao thinks the installation is complete.
			touch(self::$contao . '/system/config/localconfig.php');
		}
		else
		{
			file_put_contents(self::$contao . '/system/initialize.test.php',
				str_replace(
					'define(\'TL_ROOT\', dirname(dirname(__FILE__)));' . "\n",
					'if(!defined(\'TL_ROOT\')) { define(\'TL_ROOT\', dirname(dirname(__FILE__))); }' . "\n",
					file_get_contents(self::$contao . '/system/initialize.php')
				)
			);
		}

		require_once self::$contao . '/system/initialize.test.php';

		// Initialize the session to empty values.
		switch ($mode)
		{
			case 'FE':
				$_SESSION['FE_DATA'] = array();
				break;
			case
				'BE':
				$_SESSION['BE_DATA'] = array();
				break;
			default:
				$_SESSION = array();
		}
	}

	/**
	 * Retrieve the base path to Contao - should be the same as the TL_ROOT constant but it looks better to call
	 * a function than to use a constant.
	 *
	 * @return string
	 */
	public static function getContaoRoot()
	{
		if (!self::$contao)
		{
			self::determineContaoRoot();
		}

		return self::$contao;
	}

	/**
	 * Checks whether this is running in Contao 3 environment.
	 *
	 * @return bool
	 */
	public function isContao3()
	{
		return self::$isContao3;
	}

	/**
	 * Checks whether this is running in Contao 2 environment.
	 *
	 * @return bool
	 */
	public function isContao2()
	{
		return !self::$isContao3;
	}

	/**
	 * Reset the exception handler to phpunit and the error handler to ourselves
	 *
	 * @return void
	 */
	private function resetPHPErrorHandling()
	{
		restore_exception_handler();

		set_error_handler(array('CyberSpectrum\\TestHarness\\Contao\\TestCase', 'handleError'));
		error_reporting(E_ALL|E_STRICT);
	}

	/**
	 * Filters all notices originating from contao core and silents them.
	 *
	 * Trigger error handler if the error is NOT in Contao Core or it is NOT a notice.
	 * This is necessary, as the core is riddled with notices all over the place.
	 * Another way would be to restrict this to a list of known and expected notices, but this would be excessive
	 * to maintain.
	 *
	 * @param $errno
	 *
	 * @param $errstr
	 *
	 * @param $errfile
	 *
	 * @param $errline
	 *
	 * @return void
	 *
	 * @throws \Exception
	 */
	public static function handleError($errno, $errstr, $errfile, $errline)
	{
		// FIXME: this really counts as hack but we currently have no better way.
		if (strpos($errfile, 'vendor/contao/core') === false)
		{ \PHPUnit_Util_ErrorHandler::handleError($errno, $errstr, $errfile, $errline); }

		if(!($errno == E_NOTICE || $errno == E_USER_NOTICE || $errno == E_STRICT))
		{ \PHPUnit_Util_ErrorHandler::handleError($errno, $errstr, $errfile, $errline); }
	}

	/**
	 * Prepare database configuration as Contao expects it to be from the configuration provided via phpunit.xml or
	 * command line.
	 *
	 * @return bool
	 */
	protected function prepareDatabaseConfig()
	{
		if (defined('TL_MODE')
			&& array_key_exists('DB_TYPE', $GLOBALS)
			&& array_key_exists('DB_HOST', $GLOBALS)
			&& array_key_exists('DB_PORT', $GLOBALS)
			&& array_key_exists('DB_USER', $GLOBALS)
			&& array_key_exists('DB_PASS', $GLOBALS)
			&& array_key_exists('DB_NAME', $GLOBALS)
		)
		{
			foreach (array(
				'DB_TYPE' => 'dbDriver',
				'DB_HOST' => 'dbHost',
				'DB_USER' => 'dbUser',
				'DB_PASS' => 'dbPass',
				'DB_NAME' => 'dbDatabase',
				'DB_PORT' => 'dbPort',
			) as $from => $to)
			{
				$GLOBALS['TL_CONFIG'][$to] = $GLOBALS[$from];
			}

			return true;
		}

		return false;
	}

	/**
	 * Tells if
	 *
	 * @return bool
	 */
	protected function connectDatabase()
	{
		return $this->prepareDatabaseConfig() && \Database::getInstance();
	}

	/**
	 * Retrieve a database worker instance.
	 *
	 * @return DbWorker
	 */
	protected function getDbWorker()
	{
		if ($this->dbWorker)
		{
			return $this->dbWorker;
		}

		$this->dbWorker = new DbWorker($this, \Database::getInstance());

		return $this->dbWorker;
	}

	/**
	 * Translate a path to a valid path within Contao root.
	 *
	 * @param $destination
	 *
	 * @param $root
	 *
	 * @return string
	 *
	 * @throws \Exception
	 */
	protected function translatePath($destination, $root)
	{
		if (substr($destination, 0, 7) == 'TL_ROOT')
		{
			return $root . substr($destination, 7);
		}
		elseif(substr($destination, 0, 8) == 'TL_FILES')
		{
			// TODO: implement better files directory detection here.
			if ($this->isContao3())
			{
				return $root .'/files' . substr($destination, 8);
			}
			else
			{
				return $root .'/tl_files' . substr($destination, 8);
			}
		}
		else
		{
			throw new \Exception('Invalid path encountered. it must start with TL_ROOT or TL_FILES.');
		}
	}

	/**
	 * This copies the various config files etc. into the contao root so we have it available there.
	 *
	 * The copy process is performed recursively and all needed parent folders will get created.
	 *
	 * If a file should appear to exist along the way, it will get overwritten allowing for defined test environments.
	 *
	 * NOTE: there is no cleanup process afterwards.
	 *
	 * This is necessary, as there exists simply no way to inject ourselves into Contao aside of having those available
	 * within /system/modules/{whatever} etc.
	 *
	 * You need to do this at least for /config{config.php,database.sql,autoload.php} and /dca/*.
	 *
	 * In the future we should incorporate the mechanism of the Contao composer installer somehow here.
	 *
	 * @param string $source The path name of the file/directory based from cwd.
	 *
	 * @param string $root       The root where the /config folder is to be found.
	 *
	 * @return void
	 */
	protected function installIntoContao($source, $destination='TL_ROOT')
	{
		$root = $this->getContaoRoot();

		$realdestination = $this->translatePath($destination, $root);

		$basename = basename($source);

		if (is_dir($source))
		{
			if (!file_exists($realdestination . '/' . $basename))
			{
				$parts = explode('/', $realdestination . '/' . $basename);

				$tmp = '';
				foreach ($parts as $part)
				{
					$tmp .= '/' . $part;
					if (!is_dir($tmp))
					{
						mkdir($tmp, 0775);
						self::$installedFiles[] = $tmp;
					}
				}
			}

			// @codeCoverageIgnoreStart
			if (!is_dir($realdestination . '/' . $basename))
			{
				throw new \Exception(sprintf('Failed to create directory %s.', $destination . '/' . $basename), 1);
			}
			// @codeCoverageIgnoreEnd

			foreach (scandir($source) as $file)
			{
				if (!in_array($file, array('.', '..')))
				{
					$this->installIntoContao($source . '/' . $file, $destination . '/' . $basename . '/' . $file);
				}
			}
		}
		elseif (is_file($source))
		{
			copy($source, $realdestination);
			self::$installedFiles[] = $realdestination;
		}
	}

	protected static function determineContaoRoot()
	{
		if (defined('CONTAO_DIRECTORY'))
		{
			if (self::isContaoAt(CONTAO_DIRECTORY))
			{
				return true;
			}
			throw new \Exception('Invalid root passed via constant CONTAO_DIRECTORY ' . CONTAO_DIRECTORY);
		}

		// search contao root...
		if (self::isContaoAt(__DIR__ . str_repeat(DIRECTORY_SEPARATOR . '..', 4) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'contao' . DIRECTORY_SEPARATOR . 'core')
			|| self::isContaoAt(__DIR__ . str_repeat(DIRECTORY_SEPARATOR . '..', 6) . DIRECTORY_SEPARATOR . 'contao' . DIRECTORY_SEPARATOR . 'core')
		)
		{
			// Contao core found.
			return true;
		}

		// Contao core not found, give up.
		throw new \Exception('Contao root could not be determined.');
	}

	/**
	 * Load base language files.
	 *
	 * @return void
	 */
	protected function loadLanguageFiles()
	{
		// Initialize the session to empty values and load the lanugage files needed.
		switch (TL_MODE)
		{
			case 'FE':
				$tmp = new SystemImplementor();
				Reflector::invoke($tmp, 'loadLanguageFile', 'default');
				break;
			case
			'BE':
				$tmp = new SystemImplementor();
				Reflector::invoke($tmp, 'loadLanguageFile', 'default');
				Reflector::invoke($tmp, 'loadLanguageFile', 'modules');
				break;
			default:
		}
		unset($tmp);
	}

	/**
	 * Tries to determine the Contao root and boot the system up.
	 *
	 * @param string $mode Either FE for frontend or BE for backend.
	 *
	 * @return bool true
	 *
	 * @throws \Exception
	 */
	protected function bootContao($mode = 'FE')
	{
		// search contao root...
		$this->determineContaoRoot();

		$errorlog = ini_get('error_log');

		// load the initialize.php of Contao.
		$this->loadInitialize($mode);

		// We do not want the Contao error handler.
		// The registered __exception() handler from contao confuses PHPUnit code coverage generating.
		$this->resetPHPErrorHandling();

		// Redirect error log back to where it was before.
		ini_set('error_log', $errorlog);

		$this->loadLanguageFiles();

		return true;
	}

	protected function tearDown()
	{
		if (is_array(self::$installedFiles))
		{
			foreach (array_reverse(self::$installedFiles) as $file)
			{
				if (is_file($file))
				{
					unlink($file);
				}
				else
				{
					rmdir($file);
				}
			}
			self::$installedFiles = array();
		}

		// if we were initialized, remove our patched inizialize.php
		if (file_exists(self::$contao . '/system/initialize.test.php'))
		{
			unlink(self::$contao . '/system/initialize.test.php');
			unlink(self::$contao . '/system/config/localconfig.php');
		}
	}
}
