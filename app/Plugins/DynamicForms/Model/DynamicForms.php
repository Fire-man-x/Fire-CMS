<?php
declare(strict_types=1);

namespace App\Plugins\DynamicForms\Model;

use App\Model\BaseModel;
use App\Model\Exceptions\RecordNotFoundException;
use App\Model\Translatable;
use App\Service\LanguageService;
use Nette\Database\Explorer;
use Nette\InvalidArgumentException;
use Nette\Utils\ArrayHash;

/**
 * DynamicForms Model
 */
class DynamicForms extends BaseModel implements Translatable
{

	const
		TRANSLATION_TABLE_NAME = 'firecms_plugin_dynamicFormDescriptions';

	/**
	 * Languages model
	 */
	private LanguageService $languages;


	/**
	 * Constructor
	 */
	public function __construct(Explorer $database, LanguageService $languages)
	{
		parent::__construct($database);
		$this->languages = $languages;

		$this->setTableName('firecms_plugin_dynamicForms');
		$this->setForeignKeyColumn('dynamicFormId');
	}


	/**
	 * Inserts new
	 */
	public function insert(ArrayHash $data): int
	{
		return parent::insert($data);
	}


	/**
	 * Get all items
	 */
	public function getItems(int $dynamicFormId): array
	{
		$dynamicForm = $this->getById($dynamicFormId);
		if(!$dynamicForm){
			throw new RecordNotFoundException();
		}
		$items = @unserialize($dynamicForm->itemsSpecifications);
		return $items !== false ? $items : array();
	}


	/**
	 * Save all items
	 */
	private function saveItems(int $dynamicFormId, array $items): void
	{
		$this->update($dynamicFormId, array(
			"itemsSpecifications" => serialize($items),
		));
	}


	/**
	 * Test if item exist
	 */
	public function testIfItemNameExist(int $dynamicFormId, string $itemName, ?string $notIn = null): bool
	{
		$items = $this->getItems($dynamicFormId);

		//test duplicity
		foreach($items as $item)
		{
			if($item["name"] == $itemName && $itemName != $notIn)
			{
				return true;
			}
		}

		return false;
	}


	/**
	 * Inserts new item
	 * @param int $dynamicFormId
	 * @param array $itemData
	 */
	public function insertItem($dynamicFormId, array $itemData): void
	{
		$items = $this->getItems($dynamicFormId);

		//insert
		$items[] = $itemData;
		$this->saveItems($dynamicFormId, $items);
	}


	/**
	 * Item getter
	 */
	public function getItem(int $dynamicFormId, int|string $itemName): ?array
	{
		$items = $this->getItems($dynamicFormId);

		//remove item by name
		foreach($items as $itemKey => $item)
		{
			if($item["name"] == $itemName)
			{
				return $item;
			}
		}

		return null;
	}


	/**
	 * Update item
	 * @param int $dynamicFormId
	 * @param string $itemName
	 * @param array $itemData
	 */
	public function updateItem(int $dynamicFormId, string $itemName, array $itemData): void
	{
		$items = $this->getItems($dynamicFormId);

		//edit item by name
		foreach($items as $itemKey => $item)
		{
			if($item["name"] == $itemName)
			{
				//update
				$items[$itemKey] = $itemData;
			}
		}

		$this->saveItems($dynamicFormId, $items);
	}


	/**
	 * Remove item
	 * @param int $dynamicFormId
	 * @param string $itemName
	 * @throws InvalidArgumentException If duplicity in "name" occurs
	 */
	public function removeItem($dynamicFormId, $itemName): void
	{
		$items = $this->getItems($dynamicFormId);

		//remove item by name
		foreach($items as $itemKey => $item)
		{
			if(!isset($item['name']) || $item["name"] == $itemName)
			{
				unset($items[$itemKey]);
			}
		}

		//insert
		$this->saveItems($dynamicFormId, $items);
	}


	/**
	 * Find by template name
	 * @param $templateName
	 */
	public function findByTemplateName($templateName, $notInId = null): \Nette\Database\Table\Selection
	{
		$query = $this->findAll()->where("templateName", $templateName);
		if(isset($notInId))
		{
			$query->where($this->getColumnId()." != ?", $notInId);
		}
		return $query;
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
	 * @param int $dynamicFormId
	 * @param string $language
	 * @param ArrayHash $data
	 * Row id
	 */
	public function insertTranslation($dynamicFormId, $language, $data): int
	{
		$data->{$this->getForeignKeyColumn()} = $dynamicFormId;
		$data->languageId = $language;
		$this->getTranslationTable()->insert($data);
		return 1;
	}


	/**
	 * Update translation
	 */
	public function updateTranslation(int $dynamicFormId, string $language, ArrayHash $data): void
	{
		$finded = $this->findTranslationBy($dynamicFormId, $language);
		if($finded->fetch()){
			$finded->update($data);
		} else {
			$this->insertTranslation($dynamicFormId, $language, $data);
		}
	}


	/**
	 * Find translation
	 * @param int $dynamicFormId
	 * @param string $language
	 * Selection
	 */
	public function findTranslationBy($dynamicFormId, $language): \Nette\Database\Table\Selection
	{
		return $this->getTranslationTable()
			->where($this->getForeignKeyColumn(), $dynamicFormId)
			->where("languageId", $language);
	}


	/**
	 * Get all with translation
	 * @param string $language If null then show in all languages
	 * Selection
	 */
	public function getAllWithTranslation($language = null): \Nette\Database\Table\Selection
	{
		$query = $this->getTranslationTable()
			->select(self::TRANSLATION_TABLE_NAME.".*")
			->where(self::TRANSLATION_TABLE_NAME.".languageId", array_keys($this->languages->getActiveLanguages())); //only active languages
		if($language){
			$query->where(self::TRANSLATION_TABLE_NAME.".languageId", $language);
		}

		return $query;
	}



	/**
	 * Inserts new item translation
	 * @param int $dynamicFormId
	 * @param array $itemData
	 */
	public function insertItemTranslation($dynamicFormId, array $itemData): void
	{
		$items = $this->getItems($dynamicFormId);

		//insert
		$items[] = $itemData;
		$this->saveItems($dynamicFormId, $items);
	}


	/**
	 * Save all items translations
	 * @param int $dynamicFormId
	 * @param string $language
	 * @param array $items
	 */
	private function saveItemTranslations($dynamicFormId, $language, array $items): void
	{
		$this->updateTranslation($dynamicFormId, $language, ArrayHash::from(array(
			"items" => serialize($items),
		)));
	}


	/**
	 * Item translation getter
	 */
	public function getItemTranslation(int $dynamicFormId, int|string $itemName, string $languageId = null): ?array
	{
		$query = $this->getTranslationTable()->where($this->getForeignKeyColumn(), $dynamicFormId);
		if($languageId)
		{
			$query->where('languageId', $languageId);
		}
		$dynamicFormTranslations = $query->fetchAssoc('languageId');

		foreach($dynamicFormTranslations as &$dynamicFormTranslation)
		{
			$items = @unserialize($dynamicFormTranslation['items']);
			$dynamicFormTranslation['items'] = is_array($items) ? $items : array();
		}
		return $dynamicFormTranslations;
	}


	/**
	 * Update item translation
	 */
	public function updateItemTranslation(int $dynamicFormId, string $itemName, array $itemDatas): void
	{
		$itemTranslations = $this->getItemTranslation($dynamicFormId, $itemName);
		bdump($itemTranslations, '$itemTranslations');
		/**
		 * @var string $languageId
		 * @var ArrayHash $itemData
		 */
		foreach($itemDatas as $languageId => $itemData)
		{

			if(isset($itemTranslations[$languageId]['items']))
			{
				$forSave = $itemTranslations[$languageId]['items'];
			}
			else
			{
				$forSave = array();
			}

			//todo: po odeslani formulare blbne

			//edit item by name
			bdump($itemName);
			bdump($itemData);
			bdump((array)$itemData);
			$forSave[$itemName] = (array) $itemData; //for example: "label"=>"Xabel"
			bdump($forSave, '$forsave');

			$this->saveItemTranslations($dynamicFormId, $languageId, $forSave);
		}
	}


	/**
	 * Update item translation
	 * @param int $dynamicFormId
	 * @param string $itemName
	 */
	public function removeItemTranslation(int $dynamicFormId, string $itemName): void
	{
		$itemTranslations = $this->getItemTranslation($dynamicFormId, $itemName);

		foreach($itemTranslations as $languageId => $itemData)
		{
			//remove
			unset($itemData['items'][$itemName]);

			$this->saveItemTranslations($dynamicFormId, $languageId, $itemData['items']);
		}
	}
}
