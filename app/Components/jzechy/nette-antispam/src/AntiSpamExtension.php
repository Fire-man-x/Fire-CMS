<?php
declare(strict_types=1);

namespace Zet\AntiSpam;

use Nette;
use Nette\DI\CompilerExtension;
use Nette\Schema\Helpers;

/**
 * Class AntiSpamExtension
 *
 * @author  Zechy <email@zechy.cz>
 * @package Zet\AntiSpam
 */
final class AntiSpamExtension extends CompilerExtension {
	
	private array $defaults = [
		"lockTime" => 5,
		"resendTime" => 60,
		"numbers" => [
			"nula", "jedna", "dva", "tři", "čtyři", "pět", "šest", "sedm", "osm", "devět"
		],
		"question" => "Kolik je",
		"translate" => false
	];
	
	private array $configuration = [];
	
	/**
	 *
	 */
	public function loadConfiguration() {
		$this->configuration = Helpers::merge($this->getConfig(), $this->defaults);
	}
	
	public function afterCompile(Nette\PhpGenerator\ClassType $class) {
		$init = $class->getMethod("initialize");
		
		$init->addBody('\Zet\AntiSpam\AntiSpamControl::register(?, $this->getService(?), $this->getService(?));', [
			$this->configuration,
			$this->getContainerBuilder()->getByType(Nette\Http\Session::class),
			$this->getContainerBuilder()->getByType(Nette\Http\Request::class)
		]);
	}
}