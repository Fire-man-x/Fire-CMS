<?php
declare(strict_types=1);

namespace App\Components\TemplateLookupTrait;

use Nette\FileNotFoundException;
use Nette\InvalidArgumentException;

/**
 * Vyhledání šablony komponenty po vzoru Presenter::formatTemplateFiles(): seznam kandidátů v pořadí priority,
 * použije se první existující soubor. Projekt tak šablonu komponenty jádra přepíše nebo přidá další variantu
 * jen tím, že soubor položí do tématu, bez úpravy jádra.
 *
 * Kandidáti pro view (formatTemplateFiles()):
 * 1. theme/FrontModule/Components/<Komponenta>/<view>.latte - šablony projektu,
 * 2. <adresář komponenty>/<view>.latte - šablony jádra.
 * <Komponenta> = krátký název třídy, která trait používá (ne potomka), výchozí view = totéž (Menu → Menu.latte).
 * Jiné view je varianta šablony, typicky z `{control <komponenta>:<view> ...}` - Latte volá render<View>(), název
 * bez pomlčky s velkým prvním písmenem (`menu:uspInfo` → `renderUspInfo`), proto se zkouší i s malým.
 *
 * Třída, která trait používá, implementuje getThemeDir() (typicky App\Service\ProjectFolders::getThemeDir()).
 */
trait TemplateLookupTrait
{
	/**
	 * Adresář tématu projektu (theme/)
	 */
	abstract protected function getThemeDir(): string;


	/**
	 * Možné soubory šablony v pořadí priority
	 *
	 * @param string|null $view NULL = výchozí šablona komponenty
	 * @return list<string>
	 */
	public function formatTemplateFiles(?string $view = null): array
	{
		$component = self::templateComponentName();
		$view ??= $component;
		if (preg_match('~^[\w-]+$~D', $view) !== 1) {
			throw new InvalidArgumentException("Invalid template name '$view' of component " . self::class . '.');
		}

		$views = array_values(array_unique([$view, lcfirst($view)]));
		$files = [];
		foreach ([$this->getThemeDir() . "/FrontModule/Components/$component", self::templateComponentDir()] as $dir) {
			foreach ($views as $candidate) {
				$files[] = "$dir/$candidate.latte";
			}
		}

		return $files;
	}


	/**
	 * První existující šablona z formatTemplateFiles()
	 *
	 * @param string|null $view NULL = výchozí šablona komponenty
	 * @throws InvalidArgumentException neplatný název view (jen písmena, číslice, _ a -)
	 * @throws FileNotFoundException žádný z kandidátů neexistuje
	 */
	public function findTemplateFile(?string $view = null): string
	{
		$files = $this->formatTemplateFiles($view);
		foreach ($files as $file) {
			if (is_file($file)) {
				return $file;
			}
		}

		throw new FileNotFoundException('Template of component ' . self::class . ' not found, tried: ' . implode(', ', $files) . '.');
	}


	/**
	 * Krátký název třídy, která trait používá - adresář šablon v tématu a výchozí view
	 */
	private static function templateComponentName(): string
	{
		return (new \ReflectionClass(self::class))->getShortName();
	}


	/**
	 * Adresář třídy, která trait používá - i u jejího potomka (např. v tématu) se šablony jádra hledají u ní
	 */
	private static function templateComponentDir(): string
	{
		$file = (new \ReflectionClass(self::class))->getFileName();
		if ($file === false) {
			throw new \LogicException('Component ' . self::class . ' has no source file.');
		}

		return dirname($file);
	}
}
