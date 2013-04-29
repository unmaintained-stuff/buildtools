<?php

namespace CyberSpectrum\TestHarness\Contao;

/**
 * Class SystemImplementor
 *
 * This class solely exists to provide access to loadLanguageFile in the test cases for booting Contao into a defined
 * state.
 *
 * @package CyberSpectrum\TestHarness\Contao
 */
class SystemImplementor extends \System
{
	/**
	 * Make the constructor public, nothing else.
	 */
	public function __construct()
	{
		parent::__construct();
	}
}
