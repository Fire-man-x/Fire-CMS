<?php
declare(strict_types=1);

namespace LiveTranslator;

use Nette;

/**
 * Translator implementation.
 *
 * @author Vladislav Hejda
 *
 * @property string $namespace
 * @property string $currentLang
 * @property string $defaultLang
 * @property array $availableLanguages
 * @property string $presenterLanguageParam
 * @property-read bool $currentLangDefault
 */
class Translator implements Nette\Localization\Translator
{
	use Nette\SmartObject;

	/** plural-form meta */
	public static string $defaultPluralForms = 'nplurals=1; plural=0;';

	/* @var string */
	private $namespace;

	private ?string $defaultLang = null;

	private ?string $lang = null;

	private array $availableLanguages = array();

	/** @var string */
	private $presenterLanguageParam = array();

	private ITranslatorStorage $translatorStorage;

	private Nette\Http\Session $session;

	private Nette\Application\Application $application;


	/**
	 * @param string  $defaultLang
	 */
	public function __construct($defaultLang, ITranslatorStorage $translatorStorage, Nette\Http\Session $session, Nette\Application\Application $application)
	{
		$this->setDefaultLang($defaultLang);
		$this->translatorStorage = $translatorStorage;
		$session->start();
		$this->session = $session;
		$this->application = $application;
	}


	public function getNamespace(): string
	{
		return $this->namespace;
	}


	public function getCurrentLang(): string
	{
		if ($this->lang) {
			return $this->lang;
		}
		if ($this->presenterLanguageParam) {
			$presenter = $this->application->presenter;
			if (isset($presenter->{$this->presenterLanguageParam})) {
				$this->setCurrentLang($presenter->{$this->presenterLanguageParam});
				return $this->lang;
			}
		}
		return $this->lang = $this->defaultLang;
	}


	public function getDefaultLang(): string
	{
		return $this->defaultLang;
	}


	public function isCurrentLangDefault(): bool
	{
		return $this->getCurrentLang() === $this->defaultLang;
	}


	public function getAvailableLanguages(): array
	{
		return $this->availableLanguages ? array_keys($this->availableLanguages) : null;
	}


	public function getVariantsCount(?string $lang = null): array
	{
		list($nplurals) = $this->evalPluralForms(1, $lang);
		return $nplurals;
	}


	public function getVariant(int $count, ?string $lang = null): int
	{
		list(, $plural) = $this->evalPluralForms($count, $lang);
		return $plural;
	}


	public function getPresenterLanguageParam(): string
	{
		return $this->presenterLanguageParam;
	}


	public function getPresenterLink(string $switchLang): string
	{
		if (!$this->presenterLanguageParam) {
			return null;
		}
		return $this->application->presenter->link('this', array($this->presenterLanguageParam => $switchLang));
	}


	/**
	 * @throws TranslatorException on invalid namespace
	 */
	public function setNamespace(string $namespace): self
	{
		if (!is_string($namespace) || empty($namespace)) {
			throw new TranslatorException('Namespace must be nonempty string.');
		}

		$this->namespace = $namespace;
		return $this;
	}



	/**
	 * Set current language.
	 * @throws TranslatorException
	 */
	public function setCurrentLang(string $lang): self
	{
		if (!is_string($lang) || empty($lang)) {
			throw new TranslatorException('Language must be nonempty string.');
		}
		if ($this->lang === $lang) {
			return $this;
		}
		if ($this->availableLanguages && !isset($this->availableLanguages[$lang])) {
			throw new TranslatorException("Language $lang is not available.");
		}

		$this->lang = $lang;
		return $this;
	}



	/**
	 * Set default language.
	 * @throws TranslatorException
	 */
	public function setDefaultLang(string $lang): self
	{
		if (!is_string($lang) || empty($lang)) {
			throw new TranslatorException('Language must be nonempty string.');
		}
		if ($this->defaultLang === $lang) {
			return $this;
		}
		if ($this->availableLanguages && !isset($this->availableLanguages[$lang])) {
			throw new TranslatorException("Language $lang is not available.");
		}

		$this->defaultLang = $lang;
		return $this;
	}



	/**
	 * Give array with language name associated with plural forms meta such as:
	 * nplurals=3; plural=((n==1) ? 0 : (n>=2 && n<=4 ? 1 : 2));
	 * @param array
	 * @throws TranslatorException
	 */
	public function setAvailableLanguages(array $languages): self
	{
		if (!is_array($languages) || empty($languages)) {
			throw new TranslatorException("Available languages must be nonempty array.");
		}

		foreach ($languages as $lang => $pluralForms) {
			if (!is_string($lang)) {
				$lang = $pluralForms;
				$pluralForms = self::$defaultPluralForms;
			}
			$this->availableLanguages[$lang] = $pluralForms;
		}

		if ($this->lang && !isset($this->availableLanguages[$this->lang])) {
			throw new TranslatorException("Set language $this->lang is not available.");
		}
		if (!isset($this->availableLanguages[$this->defaultLang])) {
			throw new TranslatorException("Default language $this->defaultLang is not available.");
		}

		return $this;
	}


	public function setPresenterLanguageParam(string $paramName)
	{
		$this->presenterLanguageParam = $paramName;
	}



	/**
	 * Translates string.
	 * Give original string or array of its original variants.
	 * Rest of arguments are handed to sprintf() function.
	 * @throws TranslatorException
	 */
	public function translate(string|\Stringable $message, mixed ...$parameters): string|\Stringable //($string, $count = 1)
	{
		$count = isset($parameters[0]) ? $parameters[0] : 0;
		$hasVariants = false;
		if (is_array($message)) {
			$hasVariants = true;
			$stringVariants = array_map('trim', array_values($message));
			$message = trim((string) $message[0]);
		} else {
			$message = trim((string) $message);
			$plural = 0;
			if (is_array($count)) {
				$args =  $count;

			} elseif (func_num_args() > 2) {
				$args = func_get_args();
				unset($args[0]);
				$args = array_values($args);

			} else {
				$args = array($count);
			}
		}

		if ($hasVariants) {
			if (is_array($count)) {
				$args = $count;
			} elseif (($argc = func_num_args()) > 2) {
				$args = func_get_args();
				unset($args[0]);
				$args = array_values($args);
			} if (isset($args)) {
				unset($count);
				foreach ($args as $arg) {
					if (is_numeric($arg)) {
						$count = (int) $arg;
						break;
					}
				}
				if (!isset($count)) {
					$count = 1;
				}
			}
			else {
				if (is_numeric($count)) {
					$count = (int) $count;
				} else {
					$count = 1;
				}
				$args = array($count);
			}

			$plural = $this->getVariant($count);
		}

		$lang = $this->getCurrentLang();

		if ($lang === $this->defaultLang) {
			if ($hasVariants) {
				if (isset($stringVariants[$plural])) {
					$translated = $stringVariants[$plural];
				} else {
					$translated = end($stringVariants);
				}
			}
			else {
				$translated = $message;
			}
		}

		else {
			$translated = $this->translatorStorage->getTranslation($message, $lang, $plural, $this->namespace);
			if (!is_string($translated) && !is_null($translated)) {
				throw new TranslatorException('ITranslatorStorage::getTranslation() must return string, '.gettype($translated).' returned.');
			}

			if (!$translated) {
				$newStrings = &$this->getNewStrings();
				$newStrings[$message] = false;

				if ($hasVariants) {
					if (isset($stringVariants[$plural])) {
						$translated = $stringVariants[$plural];
					} else {
						$translated = end($stringVariants);
					}
				} else {
					$translated = $message;
				}
			}
		}

		if (false !== strpos($translated, '%')) {
			$tmp = str_replace(array('%label', '%name', '%value'), array('#label', '#name', '#value'), $translated);
			if (false !== strpos($tmp, '%')) {
				$translated = vsprintf($tmp, $args);
				$translated = str_replace(array('#label', '#name', '#value'), array('%label', '%name', '%value'), $translated);
			}
		}

		return $translated;
	}



	/**
	 * @throws TranslatorException
	 */
	public function getAllStrings(): array
	{
		$strings = $this->translatorStorage->getAllTranslations($this->getCurrentLang(), $this->namespace);
		if (!is_array($strings)) {
			throw new TranslatorException('ITranslatorStorage::getAllTranslations() must return array, '.gettype($strings).' returned.');
		}

		$newStrings = $this->getNewStrings();
		return $strings + (is_array($newStrings) ? $newStrings : array());
	}



	/**
	 * Set translation string(s).
	 */
	public function setTranslation(string $original, string|array|bool $translated)
	{
		$lang = $this->getCurrentLang();
		if ($lang === $this->defaultLang) {
			return;
		}
		$original = trim($original);
		if ($translated === false) {
			$newStrings = &$this->getNewStrings();
			$this->translatorStorage->removeTranslation($original, $lang, $this->namespace);
			unset($newStrings[$original]);
			return;
		}

		if (!is_array($translated)) {
			$translated = array($translated);
		}
		$translated = array_values($translated);
		foreach ($translated as $variant => $string) {
			$this->translatorStorage->setTranslation($original, $string, $lang, $variant, $this->namespace);
		}
	}



	protected function getSessionSection()
	{
		$ns = $this->namespace ?: 'default';
		return $this->session->getSection("LT-$ns");
	}



	protected function &getNewStrings()
	{
		// todo mohlo by to mít jednu section a ns by byly jednotlivý property zde
		$section = $this->getSessionSection();
		if (!isset($section->strings)) {
			$section->strings = array();
		}
		$strings = &$section->strings;
		return $strings;
	}



	private function evalPluralForms($count = 1, $lang = null)
	{
		$lang = $lang ?: $this->getCurrentLang();
		$pluralForms = isset($this->availableLanguages[$lang]) ? $this->availableLanguages[$lang] : self::$defaultPluralForms;
		if (!$pluralForms) {
			throw new TranslatorException("Empty plural-form meta for language $lang.");
		}

		$eval = preg_replace('/([a-z]+)/', '$$1', "n=$count;$pluralForms");
		eval($eval);

		if (!isset($nplurals)) {
			throw new TranslatorException("Cannot resolve nplurals form count for $lang. Check plural-form meta $pluralForms.");
		}
		if (!isset($plural)) {
			throw new TranslatorException("Cannot resolve plural form for $lang. Check plural-form meta $pluralForms.");
		}
		if (($plural +1) > $nplurals) {
			throw new TranslatorException(
				"Plural-form parse error for $lang. Plural form cannot exceed ".($nplurals-1)
			  . " regarding to nplural=$nplurals, but $plural returned. Check plural-form meta $pluralForms.");
		}

		return array($nplurals, $plural);
	}
}


class TranslatorException extends \Exception {}
