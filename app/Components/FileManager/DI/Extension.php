<?php
declare(strict_types=1);

namespace App\Components\FileManager\DI;

use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nette\Utils\Validators;

/**
 * The Image Extension
 */
class Extension extends CompilerExtension
{

	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'storageClass' => Expect::string()->default('App\Components\FileManager\Storages\HashFileStorage'),
			'imageEntity' => Expect::string()->default('App\Components\FileManager\Files\HashImageEntity'),
			'basePath' => Expect::string()->default('/files/'),
			'storageDir' => Expect::string()->default('%wwwDir%/files'),
			'cacheDir' => Expect::string()->default('%wwwDir%/files/cache'),
			'macros' => Expect::array()->default([
				"App\Components\FileManager\Macro\ImageMacro"
			]),
		]);
	}


	public function loadConfiguration()
	{
		$options = $this->getConfig();
		$builder = $this->getContainerBuilder();

		// Image storage
		$builder->addDefinition($this->prefix('storage'))
			->setFactory($options->storageClass, [$options->storageDir, $options->cacheDir, $options->basePath, true, '@httpRequest']);

		//File manager
		$builder->addDefinition($this->prefix('fileManager'))
			->setFactory('App\Components\FileManager\FileManager', [$this->prefix('@storage')]);

		// Latte macros
		$this->addMacros($options);
	}


	/**
	 * Adds Latte extensions (found in $options) to the latte factory definition.
	 *
	 * @param \stdClass
	 */
	private function addMacros($options)
	{
		$builder = $this->getContainerBuilder();

		if (isset($options->macros) && is_array($options->macros)) {
			$factory = $builder->getDefinition('nette.latteFactory');
			foreach ($options->macros as $macro) {
				Validators::assert($macro, 'string');
				$factory->getResultDefinition()->addSetup('addExtension', [new Statement($macro)]);
			}
		}
	}
}
