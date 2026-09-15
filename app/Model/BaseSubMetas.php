<?php
declare(strict_types=1);

namespace App\Model;

use App\Service\LanguageService;
use Nette\Database\Explorer;
use Nette\Database\SqlLiteral;
use Nette\Security\User;
use Nette\Utils\ArrayHash;

/**
 * BaseSubMetas Model
 */
abstract class BaseSubMetas extends BaseModel
{

	protected LanguageService $languages;

	protected User $user;

	/**
	 * Reference column
	 */
	private string $referenceColumn;


	/**
	 * Constructor
	 */
	public function __construct(Explorer $database, LanguageService $languages, User $user)
	{
		parent::__construct($database);
		$this->languages = $languages;
		$this->user = $user;
	}


	/**
	 * Reference column setter
	 */
	public function setReferenceColumn(string $referenceColumn): self
	{
		$this->referenceColumn = $referenceColumn;
		return $this;
	}


	/**
	 * Reference column getter
	 */
	public function getReferenceColumn(): string
	{
		if(!isset($this->referenceColumn)){
			throw new \InvalidArgumentException("Reference column id is not setted");
		}
		return $this->referenceColumn;
	}


	/**
	 * Find by article id
	 */
	public function findByColumnId(int $id): \Nette\Database\Table\Selection
	{

		return $this->findAll()->where($this->getReferenceColumn(), $id);
	}


	/**
	 * Inserts new
	 */
	public function insert(ArrayHash $data): int
	{
		$data->create_date = new SqlLiteral("NOW()");
		$data->created_by = $this->user->getId();
		return parent::insert($data);
	}


	/**
	 * Use metas - update or insert (if not exist)
	 * @param int $columnId Id of row in "Column" column
	 * @param array $data Data to update
	 */
	public function useMetas(int $columnId, array $data): void
	{
		if(is_null($columnId)){
			throw new \InvalidArgumentException("Reference column ('".$this->getReferenceColumn()."') cannot be null");
		}
		foreach ($data as $key => $value) {
			$query = $this->findByColumnId($columnId)
				->where("meta_id", $key);
			$exist = $query->fetch();
			if($exist){
				$query->update(array("value" => $value));
			} else {
				$this->insert(ArrayHash::from(array(
					$this->getReferenceColumn() => $columnId,
					"meta_id" => $key,
					"value" => $value
					)));
			}
		}
	}


	/**
	 * Update
	 */
	public function update(int $id, array $data): ?bool
	{
		throw new \BadMethodCallException("You cannot use this method");
	}

}
