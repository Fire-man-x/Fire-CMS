<?php
declare(strict_types=1);

namespace App\FrontModule\Components\SearchControl;

use AlesWita\Components\VisualPaginator;
use Nette\Application\UI\Control;
use Nette\Application\UI\Form;

/**
 * Class SearchControl
 *
 * SearchControl Component
 */

class SearchControl extends Control
{

	/** articles */
	private array $articles = array();

	/** Query */
	private string $query = "";

	private string $language;

	private string $templateFile;

	private \Nette\Localization\Translator $translator;

	private SearchFormFactory $searchFormFactory;


	/**
	 * SearchControl Component
	 */
	public function __construct(\Nette\Localization\Translator $translator, SearchFormFactory $searchFactory)
	{
		$this->translator = $translator;
		$this->searchFormFactory = $searchFactory;
	}


	/**
	 * Query setter
	 */
	public function setQuery(string $query): self
	{
		$this->query = $query;
		return $this;
	}



	/**
	 * Custom template setter
	 */
	public function customTemplate(string $template = null): void
	{
		$this->templateFile = $template ?: __DIR__ . '/SearchControl.latte';
	}


	/**
	 * Render function
	 */
	public function render(): void
	{
		$this->customTemplate();

		$this->template->setFile($this->templateFile);
		$this->template->setTranslator($this->translator);
		$this->template->render();
	}


	/**
	 * Render function
	 */
	public function renderNavbar(): void
	{
		$this->customTemplate(__DIR__ . '/SearchControlNavbar.latte');

		$this->template->setFile($this->templateFile);
		$this->template->setTranslator($this->translator);
		$this->template->render();
	}


	/**
	 * Language setter
	 */
	public function setLanguage(string $language): self
	{
		$this->language = $language;

		return $this;
	}


	/**
	 * Files paginator factory.
	 */
	protected function createComponentPaginator(): VisualPaginator
	{
		$vp = new VisualPaginator();
		//$vp->setCanSetItemsPerPage(true);
		//$vp->setItemsPerPageList(array(1,2,3,10=>10));
		$vp->setItemsPerPage(10);
		$vp->setTranslator($this->translator);

		return $vp;
	}


	/**
	 * Sign-up form factory.
	 */
	protected function createComponentSearchForm(): Form
	{
		$form = $this->searchFormFactory->create($this->query);
		$form->setTranslator($this->translator);

		return $form;
	}

}
