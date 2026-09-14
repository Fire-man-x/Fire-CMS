<?php
declare(strict_types=1);

namespace App\Presenters;

use Alnux\NetteBreadCrumb\BreadCrumb;
use App\Components\FileManager\TPresenter;
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

}
