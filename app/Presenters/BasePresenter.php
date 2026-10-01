<?php
declare(strict_types=1);

namespace App\Presenters;

use Alnux\NetteBreadCrumb\BreadCrumb;
use App\Components\FileManager\TPresenter;
use Nette\Application\Helpers;
use Nette\Application\UI\Presenter;
use Nette\HtmlStringable;


/**
 * Base presenter for all application presenters.
 * @property-read \App\Security\User $user
 */
abstract class BasePresenter extends Presenter
{
	/**
	 * Image storage Trait
	 */
	use TPresenter;

	/**
	 * Translator
	 * @inject
	 */
	public \LiveTranslator\Translator $translator;

	/**
	 * @inject
	 */
	public \App\DI\IPluginServiceLocator $pluginServices;

	/**
	 * @inject
	 */
	public \App\DI\IPluginComponentLocator $pluginComponents;


	/**
	 * Fetches a plugin service registered under the given "presenter.plugin" tag.
	 * @param string $name
	 * @return object
	 */
	protected function getPlugin(string $name): object
	{
		return $this->pluginServices->get($name);
	}


	/**
	 * Components provided by plugins via the "presenter.component" tag.
	 * @param string $name
	 * @return \Nette\ComponentModel\IComponent|null
	 */
	protected function createComponent(string $name): ?\Nette\ComponentModel\IComponent
	{
		$factory = $this->pluginComponents->get($name);

		return $factory !== null ? $factory->createComponent($this) : parent::createComponent($name);
	}


	protected function startup(): void
	{
		parent::startup();

		$this->template->setTranslator($this->translator);
	}


	/**
	 * Saves the message to template, that can be displayed after redirect.
	 * @param  string|\stdClass|HtmlStringable  $message
	 * @param  string
	 * @param  bool
	 * @return \stdClass
	 */
	public function flashMessage($message, string $type = 'info', bool $translate = true): \stdClass
	{
		if ($this->translator && $translate){
			$message = $this->translator->translate($message);
		}

		if($this->isAjax()){
			$this->redrawControl("flashes");
		}

		return parent::flashMessage($message, $type);
	}


	/**
	 * BreadCrumb control
	 */
	protected function createComponentBreadCrumb(): BreadCrumb
	{
		$breadCrumb = new BreadCrumb();

		return $breadCrumb;
	}


	/**
	 * Add link to BreadCrumb control
	 * @param \Nette\Application\UI\Link $link
	 * @param null $icon
	 */
	protected function addBreadCrumbLink(string $title, string $link = null, $icon = null, $translate = true): void
	{
		$this["breadCrumb"]->addLink($translate ? $this->translator->translate($title) : $title, $link, $icon);
	}


	/**
	 * Šablony view - navíc i u presenteru z app/, který tenhle presenter z theme/ přepisuje
	 * (viz App\Application\PresenterFactory::themeOverride()), aby přepis nemusel kopírovat šablony.
	 * @return list<string>
	 */
	public function formatTemplateFiles(): array
	{
		$list = parent::formatTemplateFiles();
		[, $presenter] = Helpers::splitName((string) $this->getName());
		$view = $this->getView();
		foreach ($this->getOverriddenTemplateDirs() as $dir) {
			$list[] = "$dir/templates/$presenter/$view.latte";
			$list[] = "$dir/templates/$presenter.$view.latte";
		}

		return $list;
	}


	/**
	 * Šablony layoutu - navíc i u presenteru z app/, který tenhle presenter z theme/ přepisuje.
	 * @return list<string>
	 */
	public function formatLayoutTemplateFiles(): array
	{
		$list = parent::formatLayoutTemplateFiles();
		$layout = $this->getLayout();
		if (is_string($layout) && preg_match('#/|\\\\#', $layout)) {
			return $list; //layout zadaný cestou k souboru
		}

		$layout = is_string($layout) && $layout !== '' ? $layout : 'layout';
		[, $presenter] = Helpers::splitName((string) $this->getName());
		$levels = substr_count((string) $this->getName(), ':');
		foreach ($this->getOverriddenTemplateDirs() as $dir) {
			$list[] = "$dir/templates/$presenter/@$layout.latte";
			$list[] = "$dir/templates/$presenter.@$layout.latte";
			$level = $levels;
			do {
				$list[] = "$dir/templates/@$layout.latte";
			} while ($level-- && ($dir = dirname($dir)));
		}

		return $list;
	}


	/**
	 * Adresáře (se složkou templates/) neabstraktních předků, které presenter z namespace Theme\ přepisuje -
	 * typicky jeden: Theme\FrontModule\Presenters\SignPresenter -> app/FrontModule.
	 * @return list<string>
	 */
	private function getOverriddenTemplateDirs(): array
	{
		$dirs = [];
		$class = new \ReflectionClass(static::class);
		while (str_starts_with($class->getName(), 'Theme\\')) {
			$parent = $class->getParentClass();
			if ($parent === false || $parent->isAbstract()) {
				break;
			}

			$dir = dirname((string) $parent->getFileName());
			$dir = is_dir("$dir/templates") ? $dir : dirname($dir);
			if (is_dir("$dir/templates")) {
				$dirs[] = $dir;
			}
			$class = $parent;
		}

		return $dirs;
	}

}
