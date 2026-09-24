<?php
declare(strict_types=1);

namespace App\FrontModule\Presenters;

use App\Model\Files;
use App\Model\Pages;
use Nette\Application\BadRequestException;
use Nette\Database\Table\ActiveRow;

/**
 * Stránka na webu (`/<slug>` přes CustomRouter, typ URL `page`). Šablonu může přepsat téma v
 * theme/FrontModule/templates/Pages/detail.latte.
 */
class PagesPresenter extends BasePresenter
{
	private ?ActiveRow $page = null;


	public function __construct(
		private readonly Pages $pagesModel,
		private readonly Files $filesModel,
	) {
		parent::__construct();
	}


	public function actionDetail(int $id): void
	{
		$page = $this->pagesModel->findPublished($this->language)->where('pageId', $id)->fetch();
		if (!$page instanceof ActiveRow) {
			throw new BadRequestException("Page '$id' doesn't exist.");
		}

		// neveřejná stránka jen pro přihlášené (stejně jako u kategorií článků)
		if (!$page->public && !$this->getUser()->isLoggedIn()) {
			$this->forward(':Front:Sign:inFast', ['id' => null, 'backlink' => $this->storeRequest()]);
		}

		$this->page = $page;
	}


	public function renderDetail(int $id): void
	{
		if ($this->page === null) {
			throw new BadRequestException("Page '$id' doesn't exist.");
		}

		// drobečková navigace: publikované nadřazené stránky, pak stránka sama
		foreach ($this->pagesModel->getPublishedParents($id, $this->language) as $parent) {
			$this->addBreadCrumbLink($parent['title'], $this->link('this', ['id' => $parent['id']]), null, false);
		}
		$title = is_string($this->page->title) ? $this->page->title : '';
		$this->addBreadCrumbLink($title, $this->link('this'), null, false);
		$this->setSEO(
			is_string($this->page->seoTitle) ? $this->page->seoTitle : null,
			is_string($this->page->seoDescription) ? $this->page->seoDescription : null,
			is_string($this->page->seoKeywords) ? $this->page->seoKeywords : null,
		);

		foreach ($this->pagesModel->getPublishedLanguages($id) as $language) {
			$this->languageChanger->setLinkForLanguage($language, $this->link('this', ['id' => $id, 'locale' => $language]));
		}

		// filtr |shortcodes v obsahu stránky (BasePresenter ho šablonám presenterů neregistruje)
		$this->shortcodes->register($this->template);
		$this->template->page = $this->page;

		// obrázky (první = hlavní) a publikované podstránky v pořadí
		$files = [];
		foreach ($this->pagesModel->getFiles($id) as $file) {
			$files[] = $this->filesModel->toFileEntity($file);
		}
		$this->template->files = $files;
		$this->template->mainFile = $files[0] ?? null;
		$this->template->children = $this->pagesModel->findPublishedChildren($id, $this->language)->fetchAll();
	}
}
