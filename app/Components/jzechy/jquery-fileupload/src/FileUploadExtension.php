<?php declare(strict_types = 1);

namespace Zet\FileUpload;

use Nette\DI\CompilerExtension;
use Nette\PhpGenerator\ClassType;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

/**
 * Class FileUploadExtension
 *
 * @author Zechy <email@zechy.cz>
 */
final class FileUploadExtension extends CompilerExtension
{

	/**
	 * Výchozí konfigurační hodnoty.
	 *
	 * @var array<mixed>
	 */
	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'maxFiles' => Expect::int(25),
			'maxFileSize' => Expect::string(),
			'uploadModel' => Expect::string(),
			'fileFilter' => Expect::string(),
			'renderer' => Expect::string('\Zet\FileUpload\Template\Renderer\Html5Renderer'),
			'translator' => Expect::string(),
			'autoTranslate' => Expect::bool(false),
			'messages' => Expect::array([
				'maxFiles' => 'Maximální počet souborů je {maxFiles}.',
				'maxSize' => 'Maximální velikost souboru je {maxSize}.',
				'fileTypes' => 'Povolené typy souborů jsou {fileTypes}.',

				// PHP Errors
				'fileSize' => 'Soubor je příliš veliký.',
				'partialUpload' => 'Soubor byl nahrán pouze částěčně.',
				'noFile' => 'Nebyl nahrán žádný soubor.',
				'tmpFolder' => 'Chybí dočasná složka.',
				'cannotWrite' => 'Nepodařilo se zapsat soubor na disk.',
				'stopped' => 'Nahrávání souboru bylo přerušeno.',
			]),
			'uploadSettings' => Expect::array(),
		]);
	}

	public function afterCompile(ClassType $class): void
	{
		$options = $this->getConfig();
		//$init = $class->methods['initialize'];

		/** @see \Zet\FileUpload\FileUploadControl::register() */
		/*$init->addBody('\Zet\FileUpload\FileUploadControl::register($this->getService(?), ?);', [
			$this->getContainerBuilder()->getByType('\Nette\DI\Container'),
			(array) $options,
		]);*/
	}

}
