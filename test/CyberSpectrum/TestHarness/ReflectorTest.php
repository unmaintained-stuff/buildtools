<?php

namespace CyberSpectrum\TestHarness;

use \CyberSpectrum\TestHarness\Reflector;

class ReflectorTest extends \PHPUnit_Framework_TestCase
{
	public function testProperties()
	{
		$toTest = new \stdClass();

		$toTest->foo = 'bar';

		$reflection = new \ReflectionProperty($toTest, 'foo');

		$this->assertInstanceOf('ReflectionProperty', Reflector::getProperty($toTest, 'foo'));
		$this->assertSame('foo', Reflector::getProperty($toTest, 'foo')->name);
		$this->assertSame('stdClass', Reflector::getProperty($toTest, 'foo')->class);
		$this->assertSame('bar', Reflector::getPropertyValue($toTest, 'foo'));
	}
}