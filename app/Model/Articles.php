<?php
declare(strict_types=1);

namespace App\Model;

use App\Components\IViewCounter;
use App\Modules\CommentsModule\Model\ISubComments;
use App\Service\LanguageService;
use Nette\Database\SqlLiteral;
use Nette\Utils\ArrayHash;

/**
 * Articles Model
 */
class Articles extends BaseModel implements IViewCounter, ISubTags, ISubComments
{

	const string
		TRANSLATION_TABLE_NAME = 'article_descriptions';
	const string
		RELATION_FILE_TABLE_NAME = 'article_files',
		RELATION_TAG_TABLE_NAME = 'article_tags',
		RELATION_COMMENT_TABLE_NAME = 'article_comments';

	private static array $statuses = array(
		'publish',
		'inherit',
		'draft',
		'auto-draft',
		'trash'
	);

	private LanguageService $languages;

	public function __construct(\Nette\Database\Explorer $database, LanguageService $languages)
	{
		parent::__construct($database);
		$this->languages = $languages;

		$this->setTableName('articles');
		$this->setColumnId('article_id');
	}


	/**
	 * Inserts new
	 */
	public function insert(ArrayHash $data): int
	{
		$data->create_date = new SqlLiteral("NOW()");
		return parent::insert($data);
	}



	/**
	 * Delete
	 */
	public function delete(int $id): void
	{
		$this->changeStatus($id, 'trash');
	}


	/**
	 * Update grid name
	 */
	public function updateGridName(int $articleId, string $language, string $name): void
	{
		if($language == $this->languages->getDefaultLanguage() ||
			($language != $this->languages->getDefaultLanguage() && $this->findById($articleId)->select("grid_name")->fetchField() == null)){
			$this->update($articleId, array("grid_name"=>$name));
		}
	}


	/**
	 * Update grid name
	 */
	private function changeStatus(int $id, string $status): void
	{
		if(!in_array($status, self::$statuses)){
			throw new \InvalidArgumentException("Status '$status' is not permitted.");
		}
		$this->findById($id)
			->update(array("status" => $status));
	}


	/**
	 * Change status publish
	 */
	public function statusPublish(int $id): void
	{
		$this->changeStatus($id, "publish");
	}


	/**
	 * Get table
	 */
	public function getTranslationTable(): \Nette\Database\Table\Selection
	{
		return $this->database->table(self::TRANSLATION_TABLE_NAME);
	}


	/**
	 * Inserts new
	 */
	public function insertTranslation(int $articleId, string $language, ArrayHash $data): int
	{
		$data->{$this->getColumnId()} = $articleId;
		$data->language_id = $language;
		$this->updateGridName($articleId, $language, $data->title);
		return $this->getTranslationTable()->insert($data);
	}


	/**
	 * Update translation
	 */
	public function updateTranslation(int $articleId, string $language, ArrayHash $data): void
	{
		$this->updateGridName($articleId, $language, $data->title);
		$finded = $this->findTranslationBy($articleId, $language);
		if($finded->fetch()){
			$finded->update($data);
		}else{
			$this->insertTranslation($articleId, $language, $data);
		}
	}


	/**
	 * Find translation
	 */
	public function findTranslationBy(int $articleId, string $language): \Nette\Database\Table\Selection
	{
		return $this->getTranslationTable()
			->where($this->getColumnId(), $articleId)
			->where("language_id", $language);
	}


	/**
	 * Get all with translation
	 * @param string|null $language If null then show in all languages
	 */
	public function getAllWithTranslation(string $language = null): \Nette\Database\Table\Selection
	{
		$query = $this->getTranslationTable()
			->select(self::TRANSLATION_TABLE_NAME.".*")
			->select("article.*")
			->where(self::TRANSLATION_TABLE_NAME.".language_id", array_keys($this->languages->getActiveLanguages())) //only active languages
			->where("status != ?", "trash");
		if($language){
			$query->where(self::TRANSLATION_TABLE_NAME.".language_id", $language);
		}

		return $query;
	}


	/**
	 * Add view count to counter
	 */
	public function addViewCount(int $articleId, string $language): void
	{
		$data = array(
			"view_count" => new SqlLiteral("view_count+1")
		);
		$this->getTranslationTable()
			->where($this->getColumnId(), $articleId)
			->where("language_id", $language)
			->update($data);
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
	 * @param int $articleId
	 * @return \Nette\Database\Table\Selection All files relation
	 */
	public function getRelationFile($articleId)
	{
		return $this->database->table(self::RELATION_FILE_TABLE_NAME)
			->select(self::RELATION_FILE_TABLE_NAME.".*")
			->select("file.*")
			->where("article_id", $articleId)
			->order("is_main DESC")
			->order("position");
	}


	/**
	 * Inserts relation with file
	 * @param int $articleId
	 * @param int $fileId
	 * @param ArrayHash $data
	 * @return int Row id
	 */
	public function insertRelationFile($articleId, $fileId, $data = array())
	{
		$data["article_id"] = $articleId;
		$data["file_id"] = $fileId;
		if(!isset($data["is_main"])){
			$is_main = $this->database->table(self::RELATION_FILE_TABLE_NAME)
				->select("IF(COUNT(is_main)=0, 1, 0)")
				->where("is_main", true)
				->where("article_id", $articleId)
				->fetchField();
			$data["is_main"] = $is_main;
		}
		if(!isset($data["position"])){
			$position = $this->database->table(self::RELATION_FILE_TABLE_NAME)
				->select("IFNULL(MAX(position),0)+1")
				->where("article_id",$articleId)
				->fetchField();
			$data["position"] = $position;
		}

		$this->database->query('INSERT IGNORE INTO '.self::RELATION_FILE_TABLE_NAME.' ?', $data);
	}


	/**
	 * Delete relation with file
	 * @param int $articleId
	 * @param int $fileId
	 * @return int Row id
	 */
	public function deleteRelationFile($articleId, $fileId)
	{
		$this->database->table(self::RELATION_FILE_TABLE_NAME)
			->where("article_id", $articleId)
			->where("file_id", $fileId)
			->delete();
	}


	/**
	 * Relation categories
	 * @param int $articleId
	 * @return \Nette\Database\Table\Selection All categories relation
	 */
	public function getRelationCategory($articleId)
	{
		return $this->database->table(Categories::RELATION_ARTICLE_TABLE_NAME)
			->select(Categories::RELATION_ARTICLE_TABLE_NAME.".*")
			->select("category.*")
			->where("article.article_id", $articleId)
			->where("category.status IN ?", array('publish','pending'))
			->order("is_main DESC")
			->order("category.grid_name");
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
	public function insertRelationTags(int $articleId, int $tagId): void
	{
		$data = array();
		$data[$this->getColumnId()] = $articleId;
		$data["tag_id"] = $tagId;

		$this->database->query('INSERT IGNORE INTO ' . self::RELATION_TAG_TABLE_NAME . ' ?', $data);
	}


	/**
	 * Delete all relation with tags
	 * @param int $articleId
	 */
	public function deleteAllRelationTags($articleId)
	{
		$this->getRelationTagsTable()
			->where($this->getColumnId(), $articleId)
			->delete();
	}


	/**
	 * Delete relation with tag
	 * @param int $articleId
	 * @param int $tagId
	 */
	public function deleteRelationTag($articleId, $tagId)
	{
		$this->deleteAllRelationTags()
			->where($this->getColumnId(), $articleId)
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
	public function insertRelationComments(int $articleId, int $commentId): void
	{
		$data = array();
		$data[$this->getColumnId()] = $articleId;
		$data["comment_id"] = $commentId;

		$this->database->query('INSERT IGNORE INTO ' . self::RELATION_COMMENT_TABLE_NAME . ' ?', $data);
	}


	/**
	 * Delete relation with comment
	 * @param int $articleId
	 * @param int $commentId
	 */
	public function deleteRelationComment($articleId, $commentId)
	{
		$this->getRelationComments($articleId)
			->where("comment_id", $commentId)
			->delete();
	}

}
