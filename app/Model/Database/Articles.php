<?php
declare(strict_types=1);

namespace App\Model\Database;

use App\Components\IViewCounter;
use App\Model\TranslatedTitleTrait\TranslatedTitleTrait;
use App\Modules\CommentsModule\Model\ISubComments;
use App\Service\LanguageService;
use Nette\Database\SqlLiteral;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\ArrayHash;

/**
 * Articles Model
 */
class Articles extends BaseModel implements Translatable, IViewCounter, ISubTags, ISubComments
{
	use TranslatedTitleTrait;

	const string
		TRANSLATION_TABLE_NAME = 'firecms_articleDescriptions';
	const string
		RELATION_FILE_TABLE_NAME = 'firecms_articleFiles',
		RELATION_TAG_TABLE_NAME = 'firecms_articleTags',
		RELATION_COMMENT_TABLE_NAME = 'firecms_articleComments';

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

		$this->setTableName('firecms_articles');
		$this->setForeignKeyColumn('articleId');
	}


	/**
	 * Inserts new
	 */
	public function insert(ArrayHash $data): int
	{
		$data->createDate = new SqlLiteral("NOW()");
		return parent::insert($data);
	}



	/**
	 * Delete
	 */
	public function delete(int $id): ?int
	{
		return (int) $this->changeStatus($id, 'trash');
	}


	/**
	 * Sloupec překladové tabulky s názvem položky (viz TranslatedTitleTrait)
	 */
	protected function getTitleColumn(): string
	{
		return 'title';
	}


	/**
	 * Update grid name
	 */
	private function changeStatus(int $id, string $status): bool
	{
		if(!in_array($status, self::$statuses)){
			throw new \InvalidArgumentException("Status '$status' is not permitted.");
		}
		return $this->getById($id)
			->update(array("status" => $status));
	}


	/**
	 * Change status publish
	 */
	public function statusPublish(int $id): bool
	{
		return $this->changeStatus($id, "publish");
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
		$data->{$this->getForeignKeyColumn()} = $articleId;
		$data->languageId = $language;
		$inserted = $this->getTranslationTable()->insert($data);
		if($inserted instanceof ActiveRow){
			return 1;
		}else{
			return (int) $inserted;
		}
	}


	/**
	 * Update translation
	 */
	public function updateTranslation(int $articleId, string $language, ArrayHash $data): void
	{
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
			->where($this->getForeignKeyColumn(), $articleId)
			->where("languageId", $language);
	}


	/**
	 * Články zobrazitelné na webu: publikované, aktivní, aktuální verze (ne revize), s datem publikace
	 * v minulosti a nevypršené
	 * @return \Nette\Database\Table\Selection<ActiveRow>
	 */
	public function findPublished(string $language): \Nette\Database\Table\Selection
	{
		return $this->getAllWithTranslation($language)
			->where("article.historyId", null)
			->where("article.status", "publish")
			->where("article.active", true)
			->where("article.publishingDate <= NOW()")
			->where("article.expiringDate IS NULL OR article.expiringDate > NOW()");
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
			->where(self::TRANSLATION_TABLE_NAME.".languageId", array_keys($this->languages->getActiveLanguages())) //only active languages
			->where("status != ?", "trash");
		if($language){
			$query->where(self::TRANSLATION_TABLE_NAME.".languageId", $language);
		}

		return $query;
	}


	/**
	 * Add view count to counter
	 */
	public function addViewCount(int $articleId, string $language): void
	{
		$data = array(
			"viewCount" => new SqlLiteral($this->delimite("viewCount") . " + 1")
		);
		$this->getTranslationTable()
			->where($this->getForeignKeyColumn(), $articleId)
			->where("languageId", $language)
			->update($data);
	}


	/**
	 * Update tree positions
	 * @param array $treePositions
	 */
	public function updateTreePositions(array $treePositions)
	{
		// řádky [id, parentId, position, level] existují, mění se jen pozice (dřív INSERT ... ON DUPLICATE KEY UPDATE)
		$this->updateRowsById($treePositions);
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
			->where("articleId", $articleId)
			->order("isMain DESC")
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
		$data["articleId"] = $articleId;
		$data["fileId"] = $fileId;
		if(!isset($data["isMain"])){
			// první soubor je hlavní (bool - isMain je v PostgreSQL boolean)
			$data["isMain"] = $this->database->table(self::RELATION_FILE_TABLE_NAME)
				->where("isMain", true)
				->where("articleId", $articleId)
				->count('*') === 0;
		}
		if(!isset($data["position"])){
			$data["position"] = (int) $this->database->table(self::RELATION_FILE_TABLE_NAME)
				->where("articleId", $articleId)
				->max("position") + 1;
		}

		$this->insertIfNotExists(self::RELATION_FILE_TABLE_NAME, $data, ["articleId", "fileId"]);
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
			->where("articleId", $articleId)
			->where("fileId", $fileId)
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
			->where("article.id", $articleId)
			->where("category.status IN ?", array('publish','pending'))
			->order("isMain DESC");
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
		$data[$this->getForeignKeyColumn()] = $articleId;
		$data["tagId"] = $tagId;

		$this->insertIfNotExists(self::RELATION_TAG_TABLE_NAME, $data, [$this->getForeignKeyColumn(), "tagId"]);
	}


	/**
	 * Delete all relation with tags
	 */
	public function deleteAllRelationTags(int $articleId): void
	{
		$this->getRelationTagsTable()
			->where($this->getForeignKeyColumn(), $articleId)
			->delete();
	}


	/**
	 * Delete relation with tag
	 */
	public function deleteRelationTag(int $articleId, int $tagId): void
	{
		$this->getRelationTagsTable()
			->where($this->getForeignKeyColumn(), $articleId)
			->where("tagId", $tagId)
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
			->where($this->getForeignKeyColumn(), $id);
	}


	/**
	 * Inserts relation with comment
	 */
	public function insertRelationComments(int $articleId, int $commentId): void
	{
		$data = array();
		$data[$this->getForeignKeyColumn()] = $articleId;
		$data["commentId"] = $commentId;

		$this->insertIfNotExists(self::RELATION_COMMENT_TABLE_NAME, $data, [$this->getForeignKeyColumn(), "commentId"]);
	}


	/**
	 * Delete relation with comment
	 */
	public function deleteRelationComment(int $articleId, int $commentId): void
	{
		$this->getRelationComments($articleId)
			->where("commentId", $commentId)
			->delete();
	}

}
