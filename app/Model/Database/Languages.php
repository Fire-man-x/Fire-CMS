<?php
declare(strict_types=1);

namespace App\Model\Database;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\ArrayHash;

/**
 * Languages Model
 */
class Languages extends BaseModel
{

	public function __construct(Explorer $database)
	{
		parent::__construct($database);

		$this->setTableName('firecms_languages');
		$this->setColumnId('languageId');
		$this->setForeignKeyColumn('languageId');
	}


	/**
	 * Inserts new
	 */
	public function insert(ArrayHash $data): int
	{
		$data->position = $this->getNextPosition();
		// klíč je languageId (kód jazyka), ne automatické ID - getInsertId() by na PostgreSQL spadl (lastval())
		return parent::insert($data);
	}

	/**
	 * Find row by id
	 */
	public function findById(int|string  $id): Selection
	{
		return $this->getTable()->where($this->getColumnId(), $id);
	}


	/**
	 * Find by id
	 */
	public function getById(int|string $id): ?ActiveRow
	{
		return $this->findById($id)->fetch();
	}


	/**
	 * Update
	 */
	public function update(int|string $id, array $data): ?bool
	{
		return $this->getById($id)?->update($data);
	}


	/**
	 * Nastaví výchozí jazyk. Výchozí je vždy právě jeden (ostatním se `default` zruší) a je aktivní -
	 * LanguageService::getDefaultLanguage() hledá výchozí jazyk jen mezi aktivními.
	 */
	public function setDefault(string $languageId): void
	{
		$this->database->transaction(function () use ($languageId): void {
			$this->findAll()
				->where($this->getColumnId() . ' != ?', $languageId)
				->update(['default' => false]);
			$this->findById($languageId)->update(['default' => true, 'active' => true]);
		});
	}


	/**
	 * Delete
	 */
	public function delete(int|string $id): ?int
	{
		return $this->findById($id)
			->where("default", false) // boolean sloupec - PostgreSQL 0 neporovná
			->delete();
	}


	/**
	 * Get next position
	 */
	protected function getNextPosition(): int
	{
		return (int) $this->findAll()->max("position") + 1;
	}

}
