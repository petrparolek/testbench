<?php

namespace Testbench\Mocks;

use Nextras\Migrations\Printers\Console;

class ConsoleMock extends Console
{

	/**
	 * Prints text to a console, optionally in a specific color.
	 * @param  string      $s
	 * @param  string|NULL $color self::COLOR_*
	 */
	protected function output($s, $color = NULL)
	{
		if (headers_sent()) {
			if ($color === NULL || !$this->useColors) {
				echo "$s\n";
			} else {
				echo $this->color($s, $color) . "\n";
			}
		}
	}
}
