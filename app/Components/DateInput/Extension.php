<?php
declare(strict_types=1);

namespace Vodacek\Forms\Controls;

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
		$init->addBody('Vodacek\Forms\Controls\DateInput::register();');
	}

}
