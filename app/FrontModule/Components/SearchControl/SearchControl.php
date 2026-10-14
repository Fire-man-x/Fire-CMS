<?php
declare(strict_types=1);

namespace App\FrontModule\Components\SearchControl;

use AlesWita\VisualPaginator\VisualPaginator;
use AlesWita\VisualPaginator\VisualPaginatorFactory;
use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;

/**
 * Class SearchControl
 *
 * SearchControl Component
 */

class SearchControl extends Control
{


	/** Query */
	private string $query = "";

	private string $templateFile;


	/**
	 * SearchControl Component
	 */
	public function __construct(
		private readonly Translator $translator,
		private readonly SearchFormFactory $searchFormFactory,
		private readonly VisualPaginatorFactory $visualPaginatorFactory,
	) {
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
	 * Files paginator factory.
	 */
	protected function createComponentPaginator(): VisualPaginator
	{
		return $this->visualPaginatorFactory->create()
			->setDefaultItemsPerPage(10);
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
