<?php
declare(strict_types=1);

namespace App\FrontModule\Presenters;

use Alnux\NetteBreadCrumb\BreadCrumb;
use App\Components\LanguageChanger;
use App\Components\Menu;
use App\Components\Shortcodes;
use App\FrontModule\Components;
use App\Model;
use App\Modules\CommentsModule\Components\Comments\Comments;
use App\Plugins\Statistics\Statistics;
use App\Service\LanguageService;
use App\Service\ProjectFolders;
use Nette\Application\Attributes\Persistent;
use Nette\Application\BadRequestException;
use Nette\Application\Helpers;


/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends \App\Presenters\BasePresenter
{
	/**
	 * Path to theme
	 * @var string
	 */
	private $themePath;

	/**
	 * Language from url
	 */
	#[Persistent]
	public ?string $locale = null;

	/**
	 * Actual selected language
	 */
	public string $language;

	/** @inject */
	public LanguageService $languages;

	/** @inject */
	public Model\Options $options;

	/** @inject */
	public Menu\Menu $menu;

	/** @inject */
	public LanguageChanger\LanguageChanger $languageChanger;

	/** @inject */
	public Components\Articles\Articles $articles;

	/** @inject */
	public Components\Categories\Categories $categories;

	/** @inject */
	public Comments $comments;

	/** @inject */
	public Components\SearchControl\SearchControl $search;

	/** @inject */
	public Shortcodes $shortcodes;

	/** @inject */
	public ProjectFolders $projectFolders;

	/** @inject */
	public Components\LoginLinkControl\LoginLinkControl $loginLink;

	/**
	 * Theme path getter
	 */
	protected function getWwwThemePath(): string
	{
		if(!isset($this->themePath))
		{
			$themePathOption = $this->options->getByKey('themePath');
			$this->themePath = $this->projectFolders->getWwwThemeDir().'/'.$themePathOption."/templates";
		}
		return $this->themePath;
	}


	/**
	 * Formats view template file names.
	 */
	public function formatTemplateFiles(): array
	{
		list(, $presenter) = Helpers::splitName($this->getName());
		$list = [
			$this->projectFolders->getThemeDir()."/FrontModule/templates/$presenter/".$this->getView().".latte",
			$this->getWwwThemePath()."/$presenter/".$this->getView().".latte",
			$this->getWwwThemePath()."/$presenter.".$this->getView().".latte",
		];

		//parent templates
		foreach(parent::formatTemplateFiles() as $file) {
			$list[] = $file;
		}

		return $list;
	}


	/**
	 * Formats view template file names.
	 */
	public function formatLayoutTemplateFiles(): array
	{
		list($module, $presenter) = Helpers::splitName($this->getName());
		$layout = $this->getLayout() ?: 'layout';
		$dir = dirname($this->getReflection()->getFileName());
		$dir = is_dir($this->getWwwThemePath()) ? $dir : dirname($dir);
		$list = [
			$this->projectFolders->getThemeDir()."/FrontModule/templates/$presenter/@$layout.latte",
			$this->projectFolders->getThemeDir()."/FrontModule/templates/@$layout.latte",
			$this->getWwwThemePath()."/$presenter/@$layout.latte",
			$this->getWwwThemePath()."/$presenter.@$layout.latte",
		];
		do {
			//todo: dava 2x stejny layout
			$list[] = $this->getWwwThemePath()."/@$layout.latte";
			$dir = dirname($dir);
		} while ($dir && $module && (list($module) = Helpers::splitName($module)));

		//parent templates
		foreach(parent::formatLayoutTemplateFiles() as $file) {
			$list[] = $file;
		}

		return $list;
	}


	protected function startup(): void
	{
		parent::startup();

		//languages
		if ($this->languages->existLanguage($this->locale)) {
			$this->language = $this->locale == null ? $this->languages->getDefaultLanguage() : $this->locale;
		} else {
			$this->language = $this->languages->getDefaultLanguage();
			throw new BadRequestException("Language '".$this->locale."' doesn't exist.");
		}
		$this->template->language = $this->languages->getLanguage($this->language);
		$this->template->languages = $this->languages;
		
		//setup translator
		$activeLanguages = array_keys($this->languages->getActiveLanguages());
		//add default "en" language for templates
		if(!in_array("en", $activeLanguages))
		{
			$activeLanguages[] = "en";
		}
		$this->translator->setAvailableLanguages($activeLanguages);
		$this->translator->setCurrentLang($this->language);
		$this->translator->setNamespace("front");

		//user
		$this->getUser()->getStorage()->setNamespace("front");

		//base breadcrumb
		$this->addBreadCrumbLink('Home', '/'/*$this->link('//:Front:Homepage:', array("id"=>null))*/, 'fa fa-home');

		//options
		$options = $this->options->findAll()->fetchPairs("key", "value");
		$this->template->options = $options;

		//register shortcode to template
		//$this->shortcodes->register($this->template);

		//set SEO to template
		$this->setSEO($options["seo_title"], $options["seo_description"], $options["seo_keywords"]);

		//trace URL in statistics
		/** @var Statistics $statisticsPlugin */
		$statisticsPlugin = $this->getPlugin('statistics');
		$statisticsPlugin->addHit();

		//themePath
		$this->template->themePath = $this->getWwwThemePath();
	}


	/**
	 * SEO setter
	 */
	protected function setSEO(?string $seoTitle = null, ?string $seoDescription = null, ?string $seoKeywords = null): void
	{
		if (!isset($this->template->seo)) {
			$this->template->seo = array(
				"title" => null,
				"description" => null,
				"keywords" => null
			);
		}
		if(!empty($seoTitle)) {
			$this->template->seo["title"] = $seoTitle;
		}
		if(!empty($seoDescription)) {
			$this->template->seo["description"] = $seoDescription;
		}
		if(!empty($seoKeywords)) {
			$this->template->seo["keywords"] = $seoKeywords;
		}
	}


	/**
	 * Components as service in plugins
	 */
	protected function createComponent(string $name): ?\Nette\ComponentModel\IComponent
	{
		if(in_array($name, ['breadCrumb', 'languageChanger', 'searchForm']))
		{
			return parent::createComponent($name);
		}

		//todo muze způsobovatchyby s jazykem
		//todo: smazal jsem protoze se neda pouzit getContext(), componenta uz musi byt vytvorena
		/*if ($this->getContext()->hasService($name)) {
			$component = $this->getContext()->createService($name);

			if(method_exists($component, 'setLanguage')){
				$component->setLanguage($this->language);
			}

			if (method_exists($component, 'create')) {
				// vytvarim komponentu pomoci Factory nebo IFactory
				return $component->create();
			} else {
				// vracim komponentu puvodne definovanou v konfigu v sekci factories:
				//return clone $component;
				return $component;
			}
		}*/
		// hledam tovarnicku createComponentName v presenteru
		// plugin components (presenter.component tag) then createComponentName in presenter
		return parent::createComponent($name);
	}


	/**
	 * BreadCrumb control
	 */
	protected function createComponentBreadCrumb(): BreadCrumb
	{
		$breadCrumb = parent::createComponentBreadCrumb();
		$path = $this->getWwwThemePath()."/BreadCrumb.latte";
		if(file_exists($path)) {
			$breadCrumb->customTemplate($path);
		}

		return $breadCrumb;
	}


	/**
	 * Categories menu
	 */
	protected function createComponentMenu(): Menu\Menu
	{
		$control = $this->menu;
		$control->setLanguage($this->language);
		$path = $this->getWwwThemePath()."/Menu.latte";
		if(file_exists($path)) {
			$control->customTemplate($path);
		}

		return $control;
	}


	/**
	 * Language Changer
	 */
	protected function createComponentLanguageChanger(): LanguageChanger\LanguageChanger
	{
		$control = $this->languageChanger;
		$control->setLanguage($this->language);
		$path = $this->getWwwThemePath()."/LanguageChanger.latte";
		if(file_exists($path)) {
			$control->customTemplate($path);
		}

		return $control;
	}


	/**
	 * List of Articles
	 */
	protected function createComponentArticles(): Components\Articles\Articles
	{
		$control = $this->articles;
		$control->setLanguage($this->language);

		return $control;
	}


	/**
	 * List of Categories
	 */
	protected function createComponentCategories(): Components\Categories\Categories
	{
		$control = $this->categories;
		$control->setLanguage($this->language);

		return $control;
	}


	/**
	 * List of Comments
	 */
	protected function createComponentComments(): Comments
	{
		$control = $this->comments;
		$control->setLanguage($this->language);

		return $control;
	}


	/**
	 * Search menu
	 */
	protected function createComponentSearch(): Components\SearchControl\SearchControl
	{
		$control = $this->search;
		$control->setLanguage($this->language);

		return $control;
	}


	/**
	 * LoginLink
	 */
	protected function createComponentLoginLink(): Components\LoginLinkControl\LoginLinkControl
	{
		$control = $this->loginLink;

		return $control;
	}

}
