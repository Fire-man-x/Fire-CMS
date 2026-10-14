<?php
declare(strict_types=1);

namespace App\Components\DateInput;

use Nette;

class Extension extends Nette\DI\CompilerExtension
{


	/**
	 * @return void
	 */
	public function afterCompile(Nette\PhpGenerator\ClassType $class){
		parent::afterCompile($class);
		$init = $class->getMethod('initialize');
		/** @see DateInput::register() */
		$init->addBody('App\Components\DateInput\DateInput::register();');
	}

}
