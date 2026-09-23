<?php
declare(strict_types=1);

namespace App\Model;

use App\Model\TranslatedTitleTrait\TranslatedTitleTrait;
use App\Components\IViewCounter;
use App\Modules\CommentsModule\Model\ISubComments;
use App\Service\LanguageService;
use Nette\Database\SqlLiteral;
use Nette\Database\Table\Selection;
use Nette\Utils\ArrayHash;

/**
 * Categories Model
 */
class Categories extends BaseModel implements Translatable, IViewCounter, ISubTags, ISubComments
{
	use TranslatedTitleTrait;

	const string
		TRANSLATION_TABLE_NAME = 'firecms_categoryDescriptions';
	const string
		RELATION_ARTICLE_TABLE_NAME = 'firecms_categoryArticle',
		RELATION_FILE_TABLE_NAME = 'firecms_categoryFiles',
		RELATION_TAG_TABLE_NAME = 'firecms_categoryTags',
		RELATION_COMMENT_ARTICLE_TABLE_NAME = 'firecms_articleComments',
		RELATION_COMMENT_CATEGORY_TABLE_NAME = 'firecms_categoryComments';

	private LanguageService $languages;

	public function __construct(\Nette\Database\Explorer $database, LanguageService $languages)
	{
		parent::__construct($database);
		$this->languages = $languages;

		$this->setTableName('firecms_categories');
		$this->setForeignKeyColumn('categoryId');
	}


	/**
	 * Get table
	 */
	public function getDatabase(): \Nette\Database\Explorer
	{
		return $this->database;
	}

	/**
	 * Inserts new
	 */
	public function insert(ArrayHash $data): int
	{
		$data->createDate = new SqlLiteral("NOW()");
		$data->position = $this->getNextPosition();
		return parent::insert($data);
	}



	/**
	 * Delete
	 * @param int $id
	 * /
	public function delete($id)
	{
		//update all childs
		$info = $this->findById($id)->fetch();
		

		//finally delete
		parent::delete($id);
		/*$this->findById($id)
			->update(array("deleted" => 1));* /
	}


	/**
	 * Sloupec překladové tabulky s názvem položky (viz TranslatedTitleTrait)
	 */
	protected function getTitleColumn(): string
	{
		return 'title';
	}


	/**
	 * Get table
	 * @return \Nette\Database\Table\Selection
	 */
	public function getTranslationTable(): \Nette\Database\Table\Selection
	{
		return $this->database->table(self::TRANSLATION_TABLE_NAME);
	}


	/**
	 * Get all with translation
	 * @param string $language If null then show in all languages
	 * @return \Nette\Database\Table\Selection Selection
	 */
	public function getAllWithTranslation($language = null)
	{
		$query = $this->getTranslationTable()
			->select(self::TRANSLATION_TABLE_NAME.".*")
			->select("category.*")
			->where(self::TRANSLATION_TABLE_NAME.".languageId", array_keys($this->languages->getActiveLanguages())) //only active languages
			->where("status NOT IN ?", array("auto-draft", "trash"));
		if($language){
			$query->where(self::TRANSLATION_TABLE_NAME.".languageId", $language);
		}

		return $query;
	}


	/**
	 * Get all for menu
	 * @return \Nette\Database\Table\Selection Selection
	 */
	public function getAllForMenu()
	{
		$query = $this->findAll()
			->where("historyId", null)
			->where("status NOT IN ?", array("auto-draft", "trash"))
			->order("categoryLeft");

		return $query;
	}


	/**
	 * Inserts new
	 * @param int $categoryId
	 * @param string $language
	 * @param ArrayHash $data
	 * @return int Row id
	 */
	public function insertTranslation($categoryId, $language, $data)
	{
		$data->{$this->getForeignKeyColumn()} = $categoryId;
		$data->languageId = $language;
		return $this->getTranslationTable()->insert($data);
	}


	/**
	 * Update translation
	 * @param int $categoryId
	 * @param string $language
	 * @param ArrayHash $data
	 * @return int Row id
	 */
	public function updateTranslation($categoryId, $language, $data)
	{
		$finded = $this->findTranslationBy($categoryId, $language);
		if($finded->fetch()){
			$finded->update($data);
		}else{
			$this->insertTranslation($categoryId, $language, $data);
		}
	}


	/**
	 * Find translation
	 * @param int $categoryId
	 * @param string $language
	 * @return \Nette\Database\Table\Selection Selection
	 */
	public function findTranslationBy($categoryId, $language)
	{
		return $this->getTranslationTable()
			->where($this->getForeignKeyColumn(), $categoryId)
			->where("languageId", $language);
	}


	/**
	 * Add view count to counter
	 */
	public function addViewCount(int $categoryId, string $language): void
	{
		$data = array(
			"viewCount" => new SqlLiteral("viewCount+1")
		);
		$this->getTranslationTable()
			->where($this->getForeignKeyColumn(), $categoryId)
			->where("languageId", $language)
			->update($data);
	}


	/**
	 * Get next position
	 * @return int
	 */
	protected function getNextPosition()
	{
		return $this->findAll()->select("IFNULL(MAX(position),0)+1 AS position")->fetchField();
	}


	/**
	 * Update tree positions
	 * @param array $treePositions
	 */
	public function updateTreePositions(array $treePositions)
	{
		// search for columns to update
		$keys = array_keys(array_values($treePositions)[0]);

		// join keys for update statement
		$updateStatement = array();
		foreach ($keys as $key){
			$updateStatement[$key] = new SqlLiteral("VALUES($key)");
		}

		foreach ($treePositions as &$treePosition){
			$treePosition["createDate"] =  new SqlLiteral("NOW()");
		}

		$this->database->query("INSERT INTO `" . $this->getTableName() . "` ? ON DUPLICATE KEY UPDATE ? ", $treePositions, $updateStatement);
	}


	/**
	 * Inserts relation with file
	 * @param int $categoryId
	 * @return \Nette\Database\Table\Selection All files relation
	 */
	public function getRelationFile($categoryId)
	{
		return $this->database->table(self::RELATION_FILE_TABLE_NAME)
			->select(self::RELATION_FILE_TABLE_NAME.".*")
			->select("file.*")
			->where("categoryId", $categoryId)
			//->order("isMain DESC")
			->order("position");
	}


	/**
	 * Inserts relation with file
	 * @param int $categoryId
	 * @param int $fileId
	 * @param ArrayHash $data
	 * @return int Row id
	 */
	public function insertRelationFile($categoryId, $fileId, $data = array())
	{
		$data["categoryId"] = $categoryId;
		$data["fileId"] = $fileId;
		if(!isset($data["isMain"])){
			$isMain = $this->database->table(self::RELATION_FILE_TABLE_NAME)
				->select("IF(COUNT(isMain)=0, 1, 0)")
				->where("isMain", true)
				->where("categoryId", $categoryId)
				->fetchField();
			$data["isMain"] = $isMain;
		}
		if(!isset($data["position"])){
			$position = $this->database->table(self::RELATION_FILE_TABLE_NAME)
				->select("IFNULL(MAX(position),0)+1")
				->where("categoryId",$categoryId)
				->fetchField();
			$data["position"] = $position;
		}

		$this->database->query('INSERT IGNORE INTO '.self::RELATION_FILE_TABLE_NAME.' ?', $data);
	}


	/**
	 * Delete relation with file
	 */
	public function deleteRelationFile(int $categoryId, int $fileId): void
	{
		$this->database->table(self::RELATION_FILE_TABLE_NAME)
			->where("categoryId", $categoryId)
			->where("fileId", $fileId)
			->delete();
	}


	/**
	 * Change positions of items
	 */
	public function changeFilePositions(int $categoryId, array $positions): void
	{
		foreach ($positions as $position => $fileId){
			$this->database->table(self::RELATION_FILE_TABLE_NAME)
				->where("categoryId", $categoryId)
				->where("fileId", $fileId)
				->update(array(
					"position"=>$position+1
				));
		}
	}


	/**
	 * Inserts relation with article
	 */
	public function getRelationArticle(int $categoryId): Selection
	{
		return $this->database->table(self::RELATION_ARTICLE_TABLE_NAME)
			->select(self::RELATION_ARTICLE_TABLE_NAME.".*")
			->select("article.*")
			->where("categoryId", $categoryId)
			->order("isMain DESC")
			->order("position");
	}


	/**
	 * Inserts relation with article
	 */
	public function insertRelationArticle(int $categoryId, int $articleId, ArrayHash $data): void
	{
		$data["categoryId"] = $categoryId;
		$data["articleId"] = $articleId;
		if(!isset($data["isMain"])){
			$isMain = $this->database->table(self::RELATION_ARTICLE_TABLE_NAME)
				->select("IF(COUNT(isMain)=0, 1, 0)")
				->where("isMain", true)
				->where("articleId", $articleId)
				->fetchField();
			$data["isMain"] = $isMain;
		}

		$this->database->query('INSERT IGNORE INTO '.self::RELATION_ARTICLE_TABLE_NAME.' ?', $data);
	}


	/**
	 * Delete relation with article
	 */
	public function deleteRelationArticle(int $categoryId, int $articleId): void
	{
		$this->database->table(self::RELATION_ARTICLE_TABLE_NAME)
			->where("categoryId", $categoryId)
			->where("articleId", $articleId)
			->delete();
	}


	/**
	 * Delete relation with article
	 */
	public function setRelationArticleAsMain(int $categoryId, int $articleId): void
	{
		$this->database->table(self::RELATION_ARTICLE_TABLE_NAME)
			->where("articleId", $articleId)
			->update(array("isMain" => false));

		$this->database->table(self::RELATION_ARTICLE_TABLE_NAME)
			->where("categoryId", $categoryId)
			->where("articleId", $articleId)
			->update(array("isMain" => true));
	}


	/**
	 * Get all parent categories
	 * @return array With items of parent from the closest to the farest
	 */
	public function getAllParents(int $initialCategoryId, string $language): array
	{
		$parentTree = array();
		$parent = $initialCategoryId;
		while ($parent != null) {
			$parentCategory = $this->getAllWithTranslation($language)
					->where("category.id", $parent)->fetch();
			if ($parentCategory) {
				$parent = $parentCategory->parentId;
				if ($parentCategory->active) {
					$parentTree[] = $parentCategory;
				}
			} else { //not active or in trash
				$parent = null;
			}
		}

		return $parentTree;
	}


	/**
	 * Relation tags table
	 */
	public function getRelationTagsTable(): \Nette\Database\Table\Selection
	{
		return $this->database->table(self::RELATION_TAG_TABLE_NAME);
	}


	/**
	 * Inserts relation with tag
	 */
	public function insertRelationTags(int $categoryId, int $tagId): void
	{
		$data = array();
		$data[$this->getForeignKeyColumn()] = $categoryId;
		$data["tagId"] = $tagId;

		$this->database->query('INSERT IGNORE INTO ' . self::RELATION_TAG_TABLE_NAME . ' ?', $data);
	}


	/**
	 * Delete all relation with tags
	 */
	public function deleteAllRelationTags(int $categoryId): void
	{
		$this->getRelationTagsTable()
			->where($this->getForeignKeyColumn(), $categoryId)
			->delete();
	}


	/**
	 * Delete relation with tag
	 */
	public function deleteRelationTag(int $categoryId, int $tagId): void
	{
		$this->getRelationTagsTable()
			->where($this->getForeignKeyColumn(), $categoryId)
			->where("tagId", $tagId)
			->delete();
	}


	/**
	 * Relation comments table
	 */
	public function getRelationCommentsTable(): \Nette\Database\Table\Selection
	{
		return $this->database->table(self::RELATION_COMMENT_ARTICLE_TABLE_NAME);
	}



	/**
	 * Get relation with comment
	 */
	public function getRelationComments(int $id): \Nette\Database\Table\Selection
	{
		return $this->getRelationCommentsTable()
			->where($this->getForeignKeyColumn(), $id);
	}


	/**
	 * Inserts relation with comment
	 */
	public function insertRelationComments(int $categoryId, int $commentId): void
	{
		$data = array();
		$data[$this->getForeignKeyColumn()] = $categoryId;
		$data["commentId"] = $commentId;

		$this->database->query('INSERT IGNORE INTO ' . self::RELATION_COMMENT_ARTICLE_TABLE_NAME . ' ?', $data);
	}


	/**
	 * Delete relation with comment
	 */
	public function deleteRelationComment(int $categoryId, int $commentId): void
	{
		$this->getRelationComments($categoryId)
			->where("commentId", $commentId)
			->delete();
	}

}
