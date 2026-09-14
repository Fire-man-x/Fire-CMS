<?php
declare(strict_types=1);

namespace App\Components;

use Nette\InvalidArgumentException;
use Nette\SmartObject;

/**
 * Class Themepath
 */
class ThemePath
{
	use SmartObject;

	private ?string $themePath = null;

	/**
	 * ThemePath constructor.
	 */
	public function __construct(string $themePath)
	{
		$this->themePath = $themePath;
	}

	public function getThemePath(): string
	{
		if(!$this->themePath) {
			throw new InvalidArgumentException('Theme path is not setted.');
		}
		return $this->themePath;
	}
}
