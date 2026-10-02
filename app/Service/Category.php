<?php
declare(strict_types=1);

namespace App\Service;

use App\Model;
use Nette\Application\ForbiddenRequestException;
use Nette\Utils\ArrayHash;

/**
 * Category Service
 */
class Category
{

	private Model\Database\Categories $categoriesModel;


	/**
	 * Constructor
	 */
	public function __construct(Model\Database\Categories $categoriesModel)
	{
		$this->categoriesModel = $categoriesModel;
	}


	/**
	 * Insert category
	 */
	public function insert(ArrayHash $values, string $language, ArrayHash $translationValues): int
	{
		// transaction(): při výjimce rollback (dřív zůstala transakce otevřená), vnořené volání nevadí
		return $this->categoriesModel->getDatabase()->transaction(function () use ($values, $language, $translationValues): int {
			$hasParent = isset($values["parentId"]);

			//last right
			$rightQuery = $this->categoriesModel->findForMenu();
			if($hasParent){
				$rightQuery->select($this->categoriesModel->getTableName().".categoryRight AS max_right");
				$rightQuery->where("id", $values["parentId"]);
			}else{
				$rightQuery->select("COALESCE(MAX(".$this->categoriesModel->getTableName().".categoryRight), 0) AS max_right");
			}
			$right = $rightQuery->fetchField();
			if ($hasParent) {
				$values["categoryLeft"] = $right;
				$values["categoryRight"] = $right + 1;

				$this->categoriesModel->findForMenu()
					->where("categoryRight >= ?", $right)
					->update(array("categoryRight" => new \Nette\Database\SqlLiteral($this->categoriesModel->delimite("categoryRight") . " + 2")));

				$this->categoriesModel->findForMenu()
					->where("categoryLeft >= ?", $right)
					->update(array("categoryLeft" => new \Nette\Database\SqlLiteral($this->categoriesModel->delimite("categoryLeft") . " + 2")));
			} else {
				$values["categoryLeft"] = $right + 1;
				$values["categoryRight"] = $right + 2;
			}

			$id = $this->categoriesModel->insert($values);
			$this->categoriesModel->insertTranslation($id, $language, $translationValues);

			return $id;
		});
	}


	/**
	 * Update category by id
	 */
	public function update(int $categoryId, array $values, string $language, ArrayHash $translationValues): void
	{
		//make backup
		$this->makeBackup($categoryId);

		$this->categoriesModel->update($categoryId, $values);
		$this->categoriesModel->updateTranslation($categoryId, $language, $translationValues);
	}


	/**
	 * Smaže kategorii (do koše) - podkategorie se přesunou o úroveň výš k jejímu rodiči a strom
	 * (categoryLeft/categoryRight) se přepočítá z parentId/position.
	 * @return int|null rodič smazané kategorie, null = byla na nejvyšší úrovni
	 * @throws ForbiddenRequestException
	 */
	public function delete(int $categoryId): ?int
	{
		$categoryInfo = $this->categoriesModel->getById($categoryId);
		if (!$categoryInfo instanceof \Nette\Database\Table\ActiveRow) {
			throw new ForbiddenRequestException("Category '$categoryId' doesn't exist.");
		}
		$parentId = $categoryInfo->parentId === null ? null : (int) $categoryInfo->parentId;

		// koš, přesun podkategorií i přepočet stromu najednou - při chybě uprostřed se nic nezmění
		$this->categoriesModel->getDatabase()->transaction(function () use ($categoryId, $parentId): void {
			//delete
			$this->categoriesModel->update($categoryId, array("status"=>"trash"));

			//repair parent
			$this->categoriesModel->findForMenu()
				->where("parentId", $categoryId)
				->update(array("parentId" => $parentId));

			// přepočet celého stromu - dřívější posun hranic o šířku smazané kategorie počítal s tím, že zmizí
			// i podkategorie, ty se ale jen přesunou výš (překrývaly by se se sousedy)
			$this->recalculateTree();
		});

		return $parentId;
	}


	/**
	 * Update tree positions
	 */
	public function updateTreePositions(array $treePositions): void
	{
		// pozice i přepočet stromu najednou - při chybě uprostřed nezůstane strom napůl přepočítaný
		$this->categoriesModel->getDatabase()->transaction(function () use ($treePositions): void {
			$this->categoriesModel->updateTreePositions($treePositions);
			$this->recalculateTree();
		});
	}


	/**
	 * Find by category id
	 */
	public function getRevisionsCount(int $id): int
	{
		if ($id == null) {
			return 0;
		}
		return $this->categoriesModel->findAll()->where("historyId", $id)->count();
	}


	/**
	 * Find by category id
	 */
	public function makeBackup(int $id): void
	{
		//$this->categorysModel->getDatabase()->query("INSERT INTO ".$this->categorysModel->getTableName()." SELECT *, null AS categoryId FROM ".$this->categorysModel->getTableName()." WHERE categoryId = 10");
		$categoryToDuplicate = ArrayHash::from($this->categoriesModel->getById($id)?->toArray());
		$categoryToDuplicate->historyId = $categoryToDuplicate->id;
		$categoryToDuplicate->id = null;

		//category
		$newId = $this->categoriesModel->insert($categoryToDuplicate);

		//description
		$allWithTranslations = $this->categoriesModel->getTranslationTable()
			->select(Model\Database\Categories::TRANSLATION_TABLE_NAME.".*")
			->where(Model\Database\Categories::TRANSLATION_TABLE_NAME.".categoryId", $id)
			->fetchAll();
		foreach ($allWithTranslations as $allWithTranslation){
			$translation = ArrayHash::from($allWithTranslation->toArray());
			unset($translation->categoryId);
			unset($translation->updateDate);
			$this->categoriesModel->insertTranslation($newId, $allWithTranslation->language, $translation);
		}

		//category_file
		$allFiles = $this->categoriesModel->getRelationFile($id)->fetchAll();
		foreach ($allFiles as $allFile){
			$file = ArrayHash::from(array(
				"fileId" => $allFile->fileId,
				"isMain" => $allFile->isMain,
				"position" => $allFile->position,
			));
			$this->categoriesModel->insertRelationFile($newId, $file->fileId, $file);
		}

		//category_article
		$allArticles = $this->categoriesModel->getDatabase()->table(Model\Database\Categories::RELATION_ARTICLE_TABLE_NAME)
			->select(Model\Database\Categories::RELATION_ARTICLE_TABLE_NAME.".*")
			->where("categoryId", $id)
			->fetchAll();
		/** @var \Nette\Database\Table\ActiveRow $allArticle */
		foreach ($allArticles as $allArticle){
			$article = ArrayHash::from($allArticle->toArray());
			unset($article->categoryId);
			$this->categoriesModel->insertRelationArticle($newId, $article->articleId, $article);
		}

		//category_meta
		/*$allMetas = $this->categorysModel->getRelationCategory($id)->fetchAll();
		foreach ($allMetas as $allMeta){
			$meta = ArrayHash::from($allMeta);
			unset($meta->categoryId);
			$this->categoriesModel->insertRelationCategory($meta->categoryId, $newId, $meta);
		}*/

		//category_tag
		$allTags = $this->categoriesModel->getRelationTagsTable()
			->select($this->categoriesModel->getRelationTagsTable()->getName().".*")
			->where("categoryId", $id)
			->fetchAll();
		/** @var \Nette\Database\Table\ActiveRow $allTag */
		foreach ($allTags as $allTag){
			$tag = ArrayHash::from($allTag->toArray());
			unset($tag->categoryId);
			$this->categoriesModel->insertRelationTags($newId, $tag->tagId);
		}

	}


	/**
	 * Find by category id
	 */
	public function getRevisions(int $id): ?array
	{
		if($id == null){
			return null;
		}
		return $this->categoriesModel->findAll()->where("historyId", $id)->order("createDate")->fetchAll();
	}


	/**
	 * Přepočítá vnořené množiny (categoryLeft/categoryRight) celého stromu kategorií z parentId a pořadí
	 * sourozenců (position). Strom je společný pro všechny sekce a position se čísluje v rámci sekce, proto
	 * nejdřív sectionId (kategorie sekce zůstanou pohromadě), pak position - ne podle starého categoryLeft
	 * (getAllForMenu()), jinak by se přetažení mezi sourozenci neprojevilo.
	 */
	public function recalculateTree(): void
	{
		/** @var array<int, list<int>> $childIds parentId (0 = nejvyšší úroveň) => id dětí v pořadí */
		$childIds = [];
		$rows = $this->categoriesModel->findForMenu()
			->select("id, parentId")
			->order("sectionId")
			->order("position")
			->order("id");
		foreach ($rows as $row) {
			$childIds[$row->parentId === null ? 0 : (int) $row->parentId][] = (int) $row->id;
		}

		$left = 1;
		foreach ($childIds[0] ?? [] as $categoryId) {
			$left = $this->setLeftRight($categoryId, $childIds, $left, 0);
		}
	}


	/**
	 * Uloží hranice a hloubku kategorie a jejích potomků, vrátí další volné číslo. Hloubka se ukládá taky -
	 * po smazání rodiče se podkategorie přesunou o úroveň výš.
	 * @param array<int, list<int>> $childIds
	 */
	private function setLeftRight(int $categoryId, array $childIds, int $left, int $level): int
	{
		$categoryLeft = $left++;
		foreach ($childIds[$categoryId] ?? [] as $childId) {
			$left = $this->setLeftRight($childId, $childIds, $left, $level + 1);
		}

		$this->categoriesModel->findAll()
			->where("id", $categoryId)
			->update(array(
				"categoryLeft" => $categoryLeft,
				"categoryRight" => $left,
				"level" => $level,
			));

		return $left + 1;
	}



	/**
	 * Get all comments with all childs
	 */
	public function getAllCommentsWithChilds($language, string $type, int $columnId, \Nette\Utils\Paginator $paginator): array
	{

		$comments = $this->categoriesModel->getAllInLanguage($language);

		$commentsChilds = clone $comments;

		$comments->where("parentId", null)
			->order("createDate DESC");
		//item count
		$itemsCount = $comments->count();
		$paginator->setItemCount($itemsCount);
		//normal list
		$commentsList = $comments->limit($paginator->getItemsPerPage(), $paginator->getOffset())->fetchAssoc("id");

		//child list
		$childList = $commentsChilds->where("parentId IS NOT NULL")->order("left ASC")->fetchAssoc("parentId|id");

		foreach ($commentsList as $commentId => $comment) {
			$commentsList[$commentId] = $this->createTree($comment, $childList);
		}

		return $commentsList;
	}


	/**
	 * Create tree from list
	 */
	protected function createTree(array $parent, array $childList)
	{
		$parentComment = \Nette\Utils\ArrayHash::from($parent);
		$parentCommentId = $parentComment->id;
		if (in_array($parentCommentId, array_keys($childList))) {
			$parentComment->childs = $childList[$parentCommentId];
			//walk over all childs
			foreach ($parentComment->childs as $childId => $child) {
				$parentComment->childs[$childId] = $this->createTree($child, $childList);
			}
		} else {
			$parentComment->childs = array();
		}

		return $parentComment;
	}

}
