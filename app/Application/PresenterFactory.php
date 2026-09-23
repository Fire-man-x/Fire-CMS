<?php
declare(strict_types=1);

namespace App\Application;

use Nette\Application\PresenterFactory as NettePresenterFactory;
use Nette\Utils\Finder;

/**
 * Presenter factory with a fallback resolver for presenters that live inside
 * plugin / feature-module trees under their own PSR-4 namespace.
 *
 * Nette's presenter mapping is keyed only by the first name segment (module),
 * so a single "Admin" mask cannot reach classes such as
 *   App\Plugins\Sliders\AdminModule\Presenters\SlidersPresenter
 *   App\Modules\CommentsModule\AdminModule\CommentsPresenter
 *   App\Modules\UrlModule\AdminModule\SettingsModule\UrlsPresenter
 *
 * This factory first asks Nette's standard mapping. When the resulting class
 * does not exist, it looks the presenter up in an index built by scanning the
 * configured plugin/module directories for *Presenter.php files.
 *
 * Ať se najde jakákoliv třída, přednost má stejnojmenná třída v namespace `Theme\` (adresář theme/) -
 * viz themeOverride().
 */
final class PresenterFactory extends NettePresenterFactory
{
	/** absolute directories scanned for *Presenter.php files */
	private array $scanDirs = [];

	/** explicit "Foo:Bar" presenter name => FQCN overrides */
	private array $map = [];

	/**
	 * Lazy index: presenter short class name => list of candidates.
	 *
	 * list<array{class: string, realm: string, stem: string}>>|null
	 */
	private ?array $index = null;

	public function __construct(?callable $factory = null)
	{
		parent::__construct($factory);
	}


		public function setScanDirs(array $dirs): static
	{
		$this->scanDirs = $dirs;
		$this->index = null;
		return $this;
	}


	/**
	 * @param  array<string, string>  $map
	 */
	public function setMap(array $map): static
	{
		$this->map = $map;
		return $this;
	}


	public function formatPresenterClass(string $presenter): string
	{
		$default = parent::formatPresenterClass($presenter);
		if (class_exists($default)) {
			return self::themeOverride($default);
		}

		if (isset($this->map[$presenter])) {
			return self::themeOverride($this->map[$presenter]);
		}

		$class = $this->resolveFallback($presenter);

		return $class !== null ? self::themeOverride($class) : $default;
	}


	/**
	 * Přepis presenteru z theme: k `App\X` se použije `Theme\X` (tedy `theme/X.php` místo `app/X.php`),
	 * pokud existuje - např. `Theme\FrontModule\Presenters\SignPresenter` místo
	 * `App\FrontModule\Presenters\SignPresenter`. Typicky z původní třídy dědí a mění jen část chování;
	 * šablony se pak dohledávají i u původní třídy (viz `App\Presenters\BasePresenter::formatTemplateFiles()`).
	 */
	private static function themeOverride(string $class): string
	{
		if (!str_starts_with($class, 'App\\')) {
			return $class;
		}

		$themeClass = 'Theme\\' . substr($class, 4);

		return class_exists($themeClass) ? $themeClass : $class;
	}


	/**
	 * Tries to find a matching presenter in the scanned plugin/module trees.
	 */
	private function resolveFallback(string $presenter): ?string
	{
		$parts = explode(':', $presenter);
		$leaf = end($parts) . 'Presenter';
		$realm = match ($parts[0]) {
			'Admin' => 'Admin',
			'Front' => 'Front',
			default => '',
		};
		$middle = array_slice($parts, 1, -1); // segments between the module and the presenter

		$candidates = $this->getIndex()[$leaf] ?? [];
		if (!$candidates) {
			return null;
		}

		// 1) keep candidates from the same realm (Admin/Front); '' matches any realm
		$pool = array_values(array_filter(
			$candidates,
			fn(array $c): bool => $c['realm'] === $realm || $c['realm'] === '',
		));
		$pool = $pool ?: $candidates;

		if (count($pool) === 1) {
			return $pool[0]['class'];
		}

		// 2) disambiguate by a middle segment matching the plugin/module folder stem,
		//    e.g. "Front:Calendar:Homepage" -> ...\Plugins\SDHCalendar\...
		foreach ($pool as $c) {
			if ($c['stem'] === '') {
				continue;
			}
			foreach ($middle as $seg) {
				if ($seg !== '' && (stripos($c['stem'], $seg) !== false || stripos($seg, $c['stem']) !== false)) {
					return $c['class'];
				}
			}
		}

		return null; // ambiguous or nothing matched - let Nette report the canonical miss
	}


		private function getIndex(): array
	{
		if ($this->index !== null) {
			return $this->index;
		}

		$this->index = [];
		foreach ($this->scanDirs as $dir) {
			if (!is_dir($dir)) {
				continue;
			}

			foreach (Finder::findFiles('*Presenter.php')->from($dir) as $path => $file) {
				$class = self::readClass((string) $path);
				if ($class === null) {
					continue;
				}

				$short = substr($class, strrpos($class, '\\') + 1);
				$realm = str_contains($class, '\\AdminModule\\')
					? 'Admin'
					: (str_contains($class, '\\FrontModule\\') ? 'Front' : '');
				$stem = preg_match('~\\\\(?:Plugins|Modules)\\\\([^\\\\]+)~', $class, $m) ? $m[1] : '';

				$this->index[$short][] = ['class' => $class, 'realm' => $realm, 'stem' => $stem];
			}
		}

		return $this->index;
	}


	/**
	 * Reads the fully qualified class name declared in a PHP file without loading it.
	 */
	private static function readClass(string $file): ?string
	{
		$src = @file_get_contents($file);
		if ($src === false) {
			return null;
		}

		if (
			!preg_match('~^[ \t]*namespace[ \t]+([^;\s]+)[ \t]*;~m', $src, $ns)
			|| !preg_match('~^[ \t]*(?:abstract[ \t]+|final[ \t]+)*class[ \t]+(\w+)~m', $src, $cl)
		) {
			return null;
		}

		return $ns[1] . '\\' . $cl[1];
	}
}
