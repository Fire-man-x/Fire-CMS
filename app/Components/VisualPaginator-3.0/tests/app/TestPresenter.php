<?php

/**
 * This file is part of the AlesWita\Components\VisualPaginator
 * Copyright (c) 2015 Ales Wita (aleswita+github@gmail.com)
 */

declare(strict_types=1);

namespace AlesWita\Components\VisualPaginator\Tests\App;

use AlesWita;
use Nette;


final class TestPresenter extends Nette\Application\UI\Presenter
{
	/** @inject */
	public AlesWita\Components\VisualPaginator $visualPaginator;

	/** @inject */
	public Nette\Http\Session $session;

	public function actionFormOne(): void {
		$this["paginator"]->setCanSetItemsPerPage(true)
			->setItemCount(50);

		$this->setView("template");
	}

	public function actionFormTwo(): void {
		$this["paginator"]->setCanSetItemsPerPage(true)
			->setItemCount(50);

		$this->setView("template");
	}

	public function actionSessionOne(): void {
		$this->setView("template");
	}

	public function actionSessionTwo(): void {
		$this["paginator"]->setSession($this->session, "my-reposity", "my-section")
			->setCanSetItemsPerPage(true)
			->setItemsPerPage(20);

		$this->setView("template");
	}

	public function actionPaginateOne(): void {
		$this["paginator"]->onPaginate[] = function() {
			$this->redirect('this');
		};

		$this->setView("template");
	}

	public function actionNormalTemplateOne(): void {
		$this["paginator"]->setItemCount(50);
		$this->setView("template");
	}

	public function actionNormalTemplateTwo(): void {
		$this["paginator"]->setItemCount(10);
		$this->setView("template");
	}

	public function actionNormalTemplateThree(): void {
		$this["paginator"]->setItemCount(10)
			->setCanSetItemsPerPage(true);

		$this->setView("template");
	}

	public function actionNormalTemplateFour(): void {
		$this["paginator"]->setItemCount(10)
			->setCanSetItemsPerPage(true);

		$this->setView("template");
	}

	public function actionNormalTemplateFive(): void {
		$this["paginator"]->setItemCount(50);
		$this->setView("templatePaginator");
	}

	public function actionNormalTemplateSix(): void {
		$this["paginator"]->setItemCount(10);
		$this->setView("templatePaginator");
	}

	public function actionNormalTemplateSeven(): void {
		$this["paginator"]->setItemCount(10)
			->setCanSetItemsPerPage(true);

		$this->setView("templateItemsPerPage");
	}

	public function actionNormalTemplateEight(): void {
		$this["paginator"]->setItemCount(50)
			->setAjax(true);

		$this->setView("template");
	}

	protected function createComponentPaginator(): AlesWita\Components\VisualPaginator {
		return $this->visualPaginator;
	}
}
