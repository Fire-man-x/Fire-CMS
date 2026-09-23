<?php
declare(strict_types=1);

namespace App\Model;

use App\Security\Acl;
use Nette\Database\Explorer;
use Nette\Utils\ArrayHash;

/**
 * Roles Model
 */
class Roles extends BaseModel implements IList
{

	const string TABLE_NAME_ROLE_MODULE = 'firecms_roleModule';

	protected Modules $modulesModel;


	public function __construct(Explorer $database, Modules $modulesModel)
	{
		parent::__construct($database);

		$this->setTableName('firecms_roles');
		$this->setForeignKeyColumn('roleId');

		$this->modulesModel = $modulesModel;
	}


	/**
	 * Inserts new
	 */
	public function insert(ArrayHash $data): int
	{
		$maxPosition = $this->findAll()->select("MAX(position) AS position")->fetch();
		$data->position = $maxPosition->position + 1;

		return parent::insert($data);
	}


	/**
	 * Delete
	 */
	public function delete(int $id): ?int
	{
		return $this->findById($id)
			->where("default", 0)
			->delete();
	}


	/**
	 * Find by name
	 */
	public function findByName(string $name): \Nette\Database\Table\Selection
	{
		return $this->getTable()->where("name", $name);
	}


	/**
	 * Get roles modules table
	 */
	private function getRolesModuleTable(): \Nette\Database\Table\Selection
	{
		return $this->database->table(self::TABLE_NAME_ROLE_MODULE);
	}


	/**
	 * Get roles modules
	 */
	public function getRolesModules(): \Nette\Database\Table\Selection
	{
		return $this->getRolesModuleTable()
				->select(self::TABLE_NAME_ROLE_MODULE.".*, role.name AS roleName, module.name AS moduleName");
	}


	/**
	 * Get role modules
	 */
	public function getRoleModules(int $roleId): array
	{
		$role_modules = $this->getRolesModules()->where(self::TABLE_NAME_ROLE_MODULE.".roleId", $roleId);
		$rules = array();
		foreach ($role_modules as $role_module) {
			//create if not exist
			if (!isset($rules[$role_module->moduleId])) {
				$rules[$role_module->moduleId] = $role_module;
			}

			//store higher priviledge
			$storedKey = array_search($rules[$role_module->moduleId]->privilege, Acl::$privilegesPriority);
			$searchedKey = array_search($role_module->privilege, Acl::$privilegesPriority);
			if ($searchedKey > $storedKey) {
				$rules[$role_module->moduleId] = $role_module;
			}
		}

		return $rules;
	}


	/**
	 * Alias for Models::getAllOtherPrivileges
	 * Get all other privileges
	 */
	public function getAllOtherPrivileges(): \Nette\Database\Table\Selection
	{
		return $this->modulesModel->getAllOtherPrivileges();
	}


	/**
	 * Update all priviledges
	 * @throws \InvalidArgumentException
	 */
	public function updateRoleModule(int $roleId, int $moduleId, ?string $privilege)
	{
		/*if (!is_null($privilege) && !in_array($privilege, Acl::$privileges)) {
			throw new \InvalidArgumentException("Permission '$privilege' is not allowed.");
		} else {*/
			//delete all privileges
			$this->getRolesModuleTable()
				->where("roleId", $roleId)
				->where("moduleId", $moduleId)
				->delete();

			//insert again
			if (!is_null($privilege) && $roleId != 1) { //$roleId=1 => "admin"
				$this->getRolesModuleTable()
					->insert(array(
						"roleId" => $roleId,
						"moduleId" => $moduleId,
						"privilege" => $privilege
				));
			}
		//}
	}


	/**
	 * Get items represented as list
	 * @return array|\Nette\Database\Table\Selection
	 */
	public function getList()
	{
		return $this->findAll()->order("position")->fetchPairs($this->getColumnId(), "title");
	}


	/**
	 * Get items represented as list
	 */
	public function getListWithName(): array|\Nette\Database\Table\Selection
	{
		return $this->findAll()->order("position")->fetchPairs($this->getColumnId(), "name");
	}
}
