<?php
declare(strict_types=1);

namespace App\Model;

use App\Components\IViewCounter;
use App\Modules\CommentsModule\Model\ISubComments;
use App\Service\LanguageService;
use Nette\Database\SqlLiteral;
use Nette\Utils\ArrayHash;

/**
 * Categories Model
 */
class Categories extends BaseModel implements IViewCounter, ISubTags, ISubComments
{

	const string
		TRANSLATION_TABLE_NAME = 'category_descriptions';
	const string
		RELATION_ARTICLE_TABLE_NAME = 'category_article',
		RELATION_FILE_TABLE_NAME = 'category_files',
		RELATION_TAG_TABLE_NAME = 'category_tags',
		RELATION_COMMENT_TABLE_NAME = 'article_comments';

	private LanguageService $languages;

	public function __construct(\Nette\Database\Explorer $database, LanguageService $languages)
	{
		parent::__construct($database);
		$this->languages = $languages;

		$this->setTableName('categories');
		$this->setColumnId('category_id');
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
		$data->create_date = new SqlLiteral("NOW()");
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
	 * Update grid name
	 * @param int $categoryId
	 * @param string $language
	 * @param string $name
	 */
	public function updateGridName($categoryId, $language, $name)
	{
		if($language == $this->languages->getDefaultLanguage() ||
			($language != $this->languages->getDefaultLanguage() && $this->findById($categoryId)->select("grid_name")->fetchField() == null)){
			$this->update($categoryId, array("grid_name"=>$name));
		}
	}


	/**
	 * Update grid name
	 * @param int $categoryId
	 * @param string $language
	 * @param string $name
	 */
	private function recalculateLeftRightPositions($categoryId)
	{
		if($language == $this->languages->getDefaultLanguage() ||
			($language != $this->languages->getDefaultLanguage() && $this->findById($categoryId)->select("grid_name")->fetchField() == null)){
			$this->update($categoryId, array("grid_name"=>$name));
		}
	}


	/**
	 * Get table
	 * @return \Nette\Database\Table\Selection
	 */
	public function getTranslationTable()
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
			->where(self::TRANSLATION_TABLE_NAME.".language_id", array_keys($this->languages->getActiveLanguages())) //only active languages
			->where("status NOT IN ?", array("auto-draft", "trash"));
		if($language){
			$query->where(self::TRANSLATION_TABLE_NAME.".language_id", $language);
		}

		return $query;
	}


	/**
	 * Get all for menu
	 * @return \Nette\Database\Table\Selection Selection
	 */
	public function getAllForMenu()
	{
		$query = $this->getAll()
			->where("history_id", null)
			->where("status NOT IN ?", array("auto-draft", "trash"))
			->order("category_left");

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
		$data->{$this->getColumnId()} = $categoryId;
		$data->language_id = $language;
		$this->updateGridName($categoryId, $language, $data->title);
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
		$this->updateGridName($categoryId, $language, $data->title);
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
			->where($this->getColumnId(), $categoryId)
			->where("language_id", $language);
	}


	/**
	 * Add view count to counter
	 */
	public function addViewCount(int $categoryId, string $language): void
	{
		$data = array(
			"view_count" => new SqlLiteral("view_count+1")
		);
		$this->getTranslationTable()
			->where($this->getColumnId(), $categoryId)
			->where("language_id", $language)
			->update($data);
	}


	/**
	 * Get next position
	 * @return int
	 */
	protected function getNextPosition()
	{
		return $this->getAll()->select("IFNULL(MAX(position),0)+1 AS position")->fetchField();
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
			$treePosition["create_date"] =  new SqlLiteral("NOW()");
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
			->where("category_id", $categoryId)
			//->order("is_main DESC")
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
		$data["category_id"] = $categoryId;
		$data["file_id"] = $fileId;
		if(!isset($data["is_main"])){
			$is_main = $this->database->table(self::RELATION_FILE_TABLE_NAME)
				->select("IF(COUNT(is_main)=0, 1, 0)")
				->where("is_main", true)
				->where("category_id", $categoryId)
				->fetchField();
			$data["is_main"] = $is_main;
		}
		if(!isset($data["position"])){
			$position = $this->database->table(self::RELATION_FILE_TABLE_NAME)
				->select("IFNULL(MAX(position),0)+1")
				->where("category_id",$categoryId)
				->fetchField();
			$data["position"] = $position;
		}

		$this->database->query('INSERT IGNORE INTO '.self::RELATION_FILE_TABLE_NAME.' ?', $data);
	}


	/**
	 * Delete relation with file
	 * @param int $categoryId
	 * @param int $fileId
	 * @return int Row id
	 */
	public function deleteRelationFile($categoryId, $fileId)
	{
		$this->database->table(self::RELATION_FILE_TABLE_NAME)
			->where("category_id", $categoryId)
			->where("file_id", $fileId)
			->delete();
	}


	/**
	 * Change positions of items
	 * @param int $categoryId Id of slider
	 * @param array $positions Array with sorted files
	 */
	public function changeFilePositions($categoryId, $positions)
	{
		foreach ($positions as $position => $file_id){
			$this->database->table(self::RELATION_FILE_TABLE_NAME)
				->where("category_id", $categoryId)
				->where("file_id", $file_id)
				->update(array(
					"position"=>$position+1
				));
		}
	}


	/**
	 * Inserts relation with article
	 * @param int $categoryId
	 * @return \Nette\Database\Table\Selection All articles relation
	 */
	public function getRelationArticle($categoryId)
	{
		return $this->database->table(self::RELATION_ARTICLE_TABLE_NAME)
			->select(self::RELATION_ARTICLE_TABLE_NAME.".*")
			->select("article.*")
			->where("category_id", $categoryId)
			->order("is_main DESC")
			->order("position");
	}


	/**
	 * Inserts relation with article
	 * @param int $categoryId
	 * @param int $articleId
	 * @param ArrayHash $data
	 * @return int Row id
	 */
	public function insertRelationArticle($categoryId, $articleId, $data = array())
	{
		$data["category_id"] = $categoryId;
		$data["article_id"] = $articleId;
		if(!isset($data["is_main"])){
			$is_main = $this->database->table(self::RELATION_ARTICLE_TABLE_NAME)
				->select("IF(COUNT(is_main)=0, 1, 0)")
				->where("is_main", true)
				->where("article_id", $articleId)
				->fetchField();
			$data["is_main"] = $is_main;
		}

		$this->database->query('INSERT IGNORE INTO '.self::RELATION_ARTICLE_TABLE_NAME.' ?', $data);
	}


	/**
	 * Delete relation with article
	 * @param int $categoryId
	 * @param int $articleId
	 * @return int Row id
	 */
	public function deleteRelationArticle($categoryId, $articleId)
	{
		$this->database->table(self::RELATION_ARTICLE_TABLE_NAME)
			->where("category_id", $categoryId)
			->where("article_id", $articleId)
			->delete();
	}


	/**
	 * Delete relation with article
	 * @param int $categoryId
	 * @param int $articleId
	 * @return int Row id
	 */
	public function setRelationArticleAsMain($categoryId, $articleId)
	{
		$this->database->table(self::RELATION_ARTICLE_TABLE_NAME)
			->where("article_id", $articleId)
			->update(array("is_main" => false));

		$this->database->table(self::RELATION_ARTICLE_TABLE_NAME)
			->where("category_id", $categoryId)
			->where("article_id", $articleId)
			->update(array("is_main" => true));
	}


	/**
	 * Get all parent categories
	 * @param int $initialCategoryId
	 * @param string $language
	 * @return array With items of parent from the closest to the farest
	 */
	public function getAllParents($initialCategoryId, $language)
	{
		$parentTree = array();
		$parent = $initialCategoryId;
		while ($parent != null) {
			$parentCategory = $this->getAllWithTranslation($language)
					->where("category.category_id", $parent)->fetch();
			if ($parentCategory) {
				$parent = $parentCategory->parent_id;
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
	 * @return \Nette\Database\Table\Selection
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
		$data[$this->getColumnId()] = $categoryId;
		$data["tag_id"] = $tagId;

		$this->database->query('INSERT IGNORE INTO ' . self::RELATION_TAG_TABLE_NAME . ' ?', $data);
	}


	/**
	 * Delete all relation with tags
	 * @param int $categoryId
	 */
	public function deleteAllRelationTags($categoryId)
	{
		$this->getRelationTagsTable()
			->where($this->getColumnId(), $categoryId)
			->delete();
	}


	/**
	 * Delete relation with tag
	 * @param int $categoryId
	 * @param int $tagId
	 */
	public function deleteRelationTag($categoryId, $tagId)
	{
		$this->deleteAllRelationTags()
			->where($this->getColumnId(), $categoryId)
			->where("tag_id", $tagId)
			->delete();
	}


	/**
	 * Relation comments table
	 */
	public function getRelationCommentsTable(): \Nette\Database\Table\Selection
	{
		return $this->database->table(self::RELATION_COMMENT_TABLE_NAME);
	}



	/**
	 * Get relation with comment
	 */
	public function getRelationComments(int $id): \Nette\Database\Table\Selection
	{
		return $this->getRelationCommentsTable()
			->where($this->getColumnId(), $id);
	}


	/**
	 * Inserts relation with comment
	 */
	public function insertRelationComments(int $categoryId, int $commentId): void
	{
		$data = array();
		$data[$this->getColumnId()] = $categoryId;
		$data["comment_id"] = $commentId;

		$this->database->query('INSERT IGNORE INTO ' . self::RELATION_COMMENT_TABLE_NAME . ' ?', $data);
	}


	/**
	 * Delete relation with comment
	 * @param int $categoryId
	 * @param int $commentId
	 */
	public function deleteRelationComment($categoryId, $commentId)
	{
		$this->getRelationComments($categoryId)
			->where("comment_id", $commentId)
			->delete();
	}

}
