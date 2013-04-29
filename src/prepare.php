<?php

// detect and load the composer autoloader.

$loader = null;
foreach (array(
	__DIR__ . '/../vendor/autoload.php',
	__DIR__ . '/../../../autoload.php'
) as $file)
{
	if (file_exists($file))
	{
		$loader = $file;

		break;
	}
}

if (!defined('TL_ROOT'))
{
	define('TL_ROOT', CyberSpectrum\TestHarness\Contao\TestCase::getContaoRoot());
}

if (!$loader)
{
	die(
		'You need to set up the project dependencies using the following commands:' . PHP_EOL .
		'curl -s http://getcomposer.org/installer | php' . PHP_EOL .
		'php composer.phar install' . PHP_EOL
	);
}

// prepare the module installation if any is required.

