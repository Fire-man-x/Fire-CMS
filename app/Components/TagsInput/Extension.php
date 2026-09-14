<?php
declare(strict_types=1);

namespace Achse\TagInput;

use Nette;

class Extension extends Nette\DI\CompilerExtension
{


	/**
	 * @return void
	 */
	public function afterCompile(Nette\PhpGenerator\ClassType $class)
	{
		parent::afterCompile($class);
		$init = $class->getMethod('initialize');
		$init->addBody('\Achse\TagInput\TagInput::register();');
	}

}
