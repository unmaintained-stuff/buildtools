<?php

namespace CyberSpectrum\TestHarness;

class Reflector
{
	public static function getProperty($instance, $property)
	{
		$reflection = new \ReflectionProperty($instance, $property);
		$reflection->setAccessible(true);

		return $reflection;
	}

	public static function getPropertyValue($instance, $property)
	{
		$reflection = self::getProperty($instance, $property);

		return $reflection->getValue($instance);
	}

	public static function setPropertyValue($instance, $property, $value)
	{
		$reflection = self::getProperty($instance, $property);

		$reflection->setValue($instance, $value);
	}

	public static function getMethod($instance, $method)
	{
		$reflection = new \ReflectionMethod($instance, $method);
		$reflection->setAccessible(true);

		return $reflection;
	}

	public static function invokeArgs($instance, $method, array $args)
	{
		$reflection = self::getMethod($instance, $method);

		return $reflection->invokeArgs($instance, $args);
	}

	public static function invoke($instance, $method /*, ... */)
	{
		$args = func_get_args();
		array_shift($args);
		array_shift($args);

		return self::invokeArgs($instance, $method, $args);
	}
}