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

	const string TABLE_NAME_ROLE_MODULE = 'role_module';

	protected Modules $modulesModel;


	public function __construct(Explorer $database, Modules $modulesModel)
	{
		parent::__construct($database);

		$this->setTableName('roles');
		$this->setColumnId('role_id');

		$this->modulesModel = $modulesModel;
	}


	/**
	 * Inserts new
	 */
	public function insert(ArrayHash $data): int
	{
		$maxPosition = $this->getAll()->select("MAX(position) AS position")->fetch();
		$data->position = $maxPosition->position + 1;

		return parent::insert($data);
	}


	/**
	 * Delete
	 */
	public function delete(int $id): void
	{
		$this->findById($id)
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
				->select(self::TABLE_NAME_ROLE_MODULE.".*, role.name AS role_name, module.name AS module_name");
	}


	/**
	 * Get role modules
	 */
	public function getRoleModules(int $role_id): array
	{
		$role_modules = $this->getRolesModules()->where(self::TABLE_NAME_ROLE_MODULE.".role_id", $role_id);
		$rules = array();
		foreach ($role_modules as $role_module) {
			//create if not exist
			if (!isset($rules[$role_module->module_id])) {
				$rules[$role_module->module_id] = $role_module;
			}

			//store higher priviledge
			$storedKey = array_search($rules[$role_module->module_id]->privilege, Acl::$privilegesPriority);
			$searchedKey = array_search($role_module->privilege, Acl::$privilegesPriority);
			if ($searchedKey > $storedKey) {
				$rules[$role_module->module_id] = $role_module;
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
	public function updateRoleModule(int $role_id, int $module_id, string $privilege)
	{
		/*if (!is_null($privilege) && !in_array($privilege, Acl::$privileges)) {
			throw new \InvalidArgumentException("Permission '$privilege' is not allowed.");
		} else {*/
			//delete all privileges
			$this->getRolesModuleTable()
				->where("role_id", $role_id)
				->where("module_id", $module_id)
				->delete();

			//insert again
			if (!is_null($privilege) && $role_id != 1) { //$role_id=1 => "admin"
				$this->getRolesModuleTable()
					->insert(array(
						"role_id" => $role_id,
						"module_id" => $module_id,
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
		return $this->getAll()->order("position")->fetchPairs($this->getColumnId(), "title");
	}


	/**
	 * Get items represented as list
	 */
	public function getListWithName(): array|\Nette\Database\Table\Selection
	{
		return $this->getAll()->order("position")->fetchPairs($this->getColumnId(), "name");
	}
}
