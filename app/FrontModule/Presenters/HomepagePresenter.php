<?php
declare(strict_types=1);

namespace App\FrontModule\Presenters;

use App\Components\FileManager\Files\HashImageEntity;
use App\Model\Database\Articles;
use App\Model\Database\Categories;
use App\Model\Database\Files;
use App\Model\Database\Pages;
use App\Model\Database\Sections;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Strings;

/**
 * Úvodní stránka webu (`Front:Homepage:default`): nejnovější publikované články (první zvýrazněný)
 * a přehled aktivních sekcí s kategoriemi. Šablonu může přepsat téma v
 * theme/FrontModule/templates/Homepage/default.latte.
 */
class HomepagePresenter extends BasePresenter
{
	/** Počet nejnovějších článků - první je zvýrazněný, zbytek v mřížce po třech */
	private const ArticleLimit = 7;

	/** Délka perexu na kartě článku/sekce (perex je HTML z editoru, na kartě jen prostý text) */
	private const ExcerptLength = 160;


	public function __construct(
		private readonly Articles $articlesModel,
		private readonly Categories $categoriesModel,
		private readonly Sections $sectionsModel,
		private readonly Files $filesModel,
		private readonly Pages $pagesModel,
	) {
		parent::__construct();
	}


	public function renderDefault(): void
	{
		foreach (array_keys($this->languages->getActiveLanguages()) as $languageId) {
			$this->languageChanger->setLinkForLanguage((string) $languageId, $this->link('this', ['locale' => $languageId]));
		}

		$this->template->articles = $this->getLatestArticles();
		$this->template->sections = $this->getSections();
		$this->template->contactPageId = $this->getContactPageId();
	}


	/**
	 * @return list<array{id: int, title: string, excerpt: string, date: \DateTimeInterface|null, image: HashImageEntity|null, categoryTitle: string|null}>
	 */
	private function getLatestArticles(): array
	{
		$rows = $this->articlesModel->findPublished($this->language)
			->order('article.publishingDate DESC')
			->limit(self::ArticleLimit)
			->fetchAll();

		// hlavní kategorie článků (název v aktuálním jazyce) jedním dotazem
		$mainCategoryIds = [];
		foreach ($rows as $row) {
			$relation = $this->articlesModel->getRelationCategory(self::toInt($row->articleId))->where('isMain', true)->fetch();
			if ($relation instanceof ActiveRow) {
				$mainCategoryIds[self::toInt($row->articleId)] = self::toInt($relation->categoryId);
			}
		}
		$categoryTitles = $mainCategoryIds === [] ? [] : $this->categoriesModel->getAllWithTranslation($this->language)
			->where('category.id', array_values($mainCategoryIds))
			->fetchPairs('categoryId', 'title');

		$articles = [];
		foreach ($rows as $row) {
			$articleId = self::toInt($row->articleId);
			$categoryId = $mainCategoryIds[$articleId] ?? null;
			$categoryTitle = $categoryId !== null ? ($categoryTitles[$categoryId] ?? null) : null;
			$articles[] = [
				'id' => $articleId,
				'title' => self::toText($row->title),
				'excerpt' => self::toPlainText($row->excerpt),
				'date' => $row->publishingDate instanceof \DateTimeInterface ? $row->publishingDate : null,
				'image' => $this->getMainImage($articleId),
				'categoryTitle' => is_string($categoryTitle) ? $categoryTitle : null,
			];
		}

		return $articles;
	}


	/**
	 * Aktivní sekce v pořadí z administrace s kategoriemi nejvyšší úrovně a počtem publikovaných článků
	 * @return list<array{id: int, title: string, description: string, categories: list<array{id: int, title: string}>, articleCount: int}>
	 */
	private function getSections(): array
	{
		$sections = [];
		foreach ($this->sectionsModel->findActive($this->language)->order('section.position')->fetchAll() as $row) {
			$sectionId = self::toInt($row->sectionId);

			$categories = [];
			$categoryRows = $this->categoriesModel->getAllWithTranslation($this->language)
				->where('category.sectionId', $sectionId)
				->where('category.parentId', null)
				->where('category.active', true)
				->where('category.status', 'publish')
				->where('category.historyId', null)
				->order('category.position');
			foreach ($categoryRows as $category) {
				$categories[] = ['id' => self::toInt($category->categoryId), 'title' => self::toText($category->title)];
			}

			$sections[] = [
				'id' => $sectionId,
				'title' => self::toText($row->title),
				'description' => self::toPlainText($row->content),
				'categories' => $categories,
				'articleCount' => $this->articlesModel->findPublished($this->language)->where('article.sectionId', $sectionId)->count('*'),
			];
		}

		return $sections;
	}


	/** Publikovaná stránka se šablonou "contact" (odkaz z výzvy dole na úvodní stránce), null = žádná */
	private function getContactPageId(): ?int
	{
		$page = $this->pagesModel->findPublished($this->language)
			->where('page.template', 'contact')
			->order('page.position')
			->fetch();

		return $page instanceof ActiveRow ? self::toInt($page->pageId) : null;
	}


	private function getMainImage(int $articleId): ?HashImageEntity
	{
		$file = $this->articlesModel->getRelationFile($articleId)->where('isMain', true)->fetch();
		if (!$file instanceof ActiveRow) {
			return null;
		}

		$entity = $this->filesModel->toFileEntity($file);

		return $entity instanceof HashImageEntity ? $entity : null;
	}


	private static function toInt(mixed $value): int
	{
		return is_numeric($value) ? (int) $value : 0;
	}


	private static function toText(mixed $value): string
	{
		return is_string($value) ? $value : '';
	}


	/** HTML z editoru -> zkrácený prostý text pro kartu */
	private static function toPlainText(mixed $html): string
	{
		$text = html_entity_decode(strip_tags(self::toText($html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

		return Strings::truncate(trim((string) preg_replace('~\s+~u', ' ', $text)), self::ExcerptLength);
	}
}
