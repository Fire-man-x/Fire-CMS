<?php
declare(strict_types=1);

namespace App\Localization;

use LiveTranslator\ITranslatorStorage;

/**
 * Úložiště překladů pro LiveTranslator složené z hlavního úložiště projektu (`data/localization/`),
 * překladů tématu (`theme/data/localization/`) a překladů pluginů (`<plugin>/data/localization/`).
 *
 * - Čtení: hlavní úložiště → téma → pluginy (v pořadí registrace). Projekt i téma tak můžou text pluginu
 *   upravit bez zásahu do pluginu.
 * - Zápis (LiveTranslator panel): jen do hlavního úložiště, soubory pluginů zůstávají beze změny.
 *
 * Plugin přidá svoje překlady službou ve svém `config.plugin.neon` (viz docs/Architecture/plugins.md),
 * téma stejně v `theme/config/theme.neon`, jen s tagem `translator.themeStorage`:
 *
 *     -
 *         factory: App\Localization\ReadOnlyFileStorage(%rootDir%/app/Plugins/<Plugin>/data/localization)
 *         autowired: false
 *         tags: [translator.pluginStorage]
 */
class ChainTranslatorStorage implements ITranslatorStorage
{
	/**
	 * @param ITranslatorStorage[] $themeStorages
	 * @param ITranslatorStorage[] $pluginStorages
	 */
	public function __construct(
		private readonly ITranslatorStorage $mainStorage,
		private readonly array $themeStorages = [],
		private readonly array $pluginStorages = [],
	) {
	}


	public function getTranslation(string $original, string $lang, int $variant = 0, ?string $namespace = null): ?string
	{
		foreach ($this->getStorages() as $storage) {
			$translation = $storage->getTranslation($original, $lang, $variant, $namespace);
			if ($translation !== null) {
				return $translation;
			}
		}

		return null;
	}


	/**
	 * @return array<mixed> původní text => překlad(y), stejně jako ITranslatorStorage::getAllTranslations()
	 */
	public function getAllTranslations(string $lang, ?string $namespace = null): array
	{
		$translations = [];
		foreach (array_reverse($this->getStorages()) as $storage) {
			// pozdější (s vyšší prioritou) přepíše dřívější
			$translations = $storage->getAllTranslations($lang, $namespace) + $translations;
		}

		return $translations;
	}


	public function setTranslation(string $original, string $translated, string $lang, int $variant = 0, ?string $namespace = null)
	{
		$this->mainStorage->setTranslation($original, $translated, $lang, $variant, $namespace);
	}


	public function removeTranslation(string $original, string $lang, ?string $namespace = null)
	{
		$this->mainStorage->removeTranslation($original, $lang, $namespace);
	}


	/**
	 * @return ITranslatorStorage[] od nejvyšší priority
	 */
	private function getStorages(): array
	{
		return [$this->mainStorage, ...array_values($this->themeStorages), ...array_values($this->pluginStorages)];
	}
}
