<?php
declare(strict_types=1);

namespace App\Components;

use App\FileStorage\FileManager;
use App\FileStorage\TPresenter;
use Nette\Application\UI\Control;

/**
 * Abstract BaseControl
 */
abstract class BaseControl extends Control
{

	use TPresenter;


	/**
	 * BaseControl
	 */
	public function __construct(FileManager $fileManager)
	{
		$this->fileManager = $fileManager;
	}
}
