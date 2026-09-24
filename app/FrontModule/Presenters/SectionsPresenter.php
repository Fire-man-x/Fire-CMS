<?php
declare(strict_types=1);

namespace App\FrontModule\Presenters;

use App\Model\Articles;
use App\Model\Categories;
use App\Model\Sections;
use Nette\Application\BadRequestException;
use Nette\Database\Table\ActiveRow;

/**
 * Výpis sekce na webu (`/<slug>` přes CustomRouter, typ URL `section`): úvodní text, kategorie článků
 * nejvyšší úrovně a nejnovější publikované články sekce. Šablonu může přepsat téma v
 * theme/FrontModule/templates/Sections/detail.latte.
 */
class SectionsPresenter extends BasePresenter
{
	/** Počet nejnovějších článků ve výpisu sekce */
	private const ArticleLimit = 20;

	private ?ActiveRow $sectionRow = null;


	public function __construct(
		private readonly Sections $sectionsModel,
		private readonly Categories $categoriesModel,
		private readonly Articles $articlesModel,
	) {
		parent::__construct();
	}


	public function actionDetail(int $id): void
	{
		$section = $this->sectionsModel->findActive($this->language)->where('sectionId', $id)->fetch();
		if (!$section instanceof ActiveRow) {
			throw new BadRequestException("Section '$id' doesn't exist.");
		}
		$this->sectionRow = $section;
	}


	public function renderDetail(int $id): void
	{
		if ($this->sectionRow === null) {
			throw new BadRequestException("Section '$id' doesn't exist.");
		}

		$title = is_string($this->sectionRow->title) ? $this->sectionRow->title : '';
		$this->addBreadCrumbLink($title, $this->link('this'), null, false);
		$this->setSEO(
			is_string($this->sectionRow->seoTitle) ? $this->sectionRow->seoTitle : null,
			is_string($this->sectionRow->seoDescription) ? $this->sectionRow->seoDescription : null,
			is_string($this->sectionRow->seoKeywords) ? $this->sectionRow->seoKeywords : null,
		);
		foreach ($this->sectionsModel->getActiveLanguages($id) as $language) {
			$this->languageChanger->setLinkForLanguage($language, $this->link('this', ['id' => $id, 'locale' => $language]));
		}

		$this->shortcodes->register($this->template);
		$this->template->sectionInfo = $this->sectionRow;
		$this->template->categories = $this->categoriesModel->getAllWithTranslation($this->language)
			->where('category.sectionId', $id)
			->where('category.parentId', null)
			->where('category.active', true)
			->where('category.status', 'publish')
			->where('category.historyId', null)
			->order('category.position')
			->fetchAll();
		$this->template->articles = $this->articlesModel->findPublished($this->language)
			->where('article.sectionId', $id)
			->order('article.publishingDate DESC')
			->limit(self::ArticleLimit)
			->fetchAll();
	}
}
