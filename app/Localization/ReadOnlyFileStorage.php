<?php
declare(strict_types=1);

namespace App\Localization;

use LiveTranslator\Storage\File;

/**
 * Překladový soubor pluginu (`<plugin>/data/localization/<lang>.<namespace>`) jen pro čtení.
 *
 * Na rozdíl od LiveTranslator\Storage\File nezakládá chybějící soubory (File je otevírá `w+`, takže
 * by v adresáři pluginu vznikaly prázdné `en.front`, `jn.admin`, ...) a neotevírá je pro zápis -
 * adresář pluginu nemusí být zapisovatelný pro webserver. Zápis jde přes ChainTranslatorStorage
 * do hlavního úložiště.
 */
class ReadOnlyFileStorage extends File
{
	public function setTranslation(string $original, string $translated, string $lang, int $variant = 0, ?string $namespace = null)
	{
		throw new \LogicException('Plugin translation storage is read-only, write to the main storage instead.');
	}


	public function removeTranslation(string $original, string $lang, ?string $namespace = null)
	{
		throw new \LogicException('Plugin translation storage is read-only, write to the main storage instead.');
	}


	/**
	 * @return resource
	 */
	protected function getFileHandler(string $lang, ?string $namespace = null)
	{
		$file = $this->getFilename($lang, $namespace);

		if (isset($this->handlers[$file])) {
			return $this->handlers[$file];
		}

		$filePath = $this->storageDir . DIRECTORY_SEPARATOR . $file;
		$handler = is_file($filePath) ? fopen($filePath, 'r') : fopen('php://memory', 'r');
		if ($handler === false) {
			throw new \RuntimeException("Unable to open translation file '$filePath'.");
		}

		return $this->handlers[$file] = $handler;
	}
}
