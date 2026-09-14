<?php
declare(strict_types=1);

namespace App\Components\LanguageChanger;

use App\Service\LanguageService;
use Nette\Application\UI\Control;

/**
 * Class LanguageChanger
 *
 * LanguageChanger Component
 */
class LanguageChanger extends Control
{

	private LanguageService $languages;

	private \Nette\Localization\Translator $translator;

	private string $templateFile;

	/**
	 * Actual selected language
	 */
	private string $language;

	private array $languageLinks = array();


	/**
	 * LanguageChanger
	 */
	public function __construct(LanguageService $languages, \Nette\Localization\Translator $translator)
	{
		$this->languages = $languages;
		$this->translator = $translator;
	}


	/**
	 * Language
	 */
	public function setLanguage(string $language): static
	{
		$this->language = $language;
		return $this;
	}


	/**
	 * Link for languages
	 */
	public function setLinkForLanguage(string $language, string $link): static
	{
		$this->languageLinks[$language] = $link;
		return $this;
	}


	public function customTemplate(string $template = null): void
	{
		$this->templateFile = $template ?: __DIR__ . '/LanguageChanger.latte';
	}


	/**
	 * Render function
	 */
	public function render(string $menuClass = "", string $itemClass = ""): void
	{
		$this->customTemplate();

		$this->template->setFile($this->templateFile);

		$this->template->language = $this->language;
		$this->template->languages = $this->languages;
		$this->template->languageLinks = $this->languageLinks;
		$this->template->menuClass = $menuClass;
		$this->template->itemClass = $itemClass;

		$this->template->setTranslator($this->translator);
		$this->template->render();
	}


	/**
	 * Render function
	 */
	public function renderHead()
	{
		$this->customTemplate(__DIR__ . '/LanguageChangerHead.latte');

		$this->template->setFile($this->templateFile);

		$this->template->language = $this->language;
		$this->template->languages = $this->languages;
		$this->template->languageLinks = $this->languageLinks;

		$this->template->setTranslator($this->translator);
		$this->template->render();
	}
}