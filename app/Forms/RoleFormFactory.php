<?php
declare(strict_types=1);

namespace App\Forms;

use App\Model;
use App\Security\Acl;
use Nette\Application\UI\Form;

class RoleFormFactory extends BaseFormFactory
{

	private Model\Roles $model;

	private Model\Modules $modelModules;


	public function __construct(Model\Roles $model, Model\Modules $modelModules)
	{
		parent::__construct();
		$this->model = $model;
		$this->modelModules = $modelModules;
	}


	public function create(int|string $editId = null): Form
	{
		$form = parent::create($editId);

		$form->addText('title', 'Title')
			->setRequired(VALIDATE_REQUIRED);

		$form->addText('name', 'Role')
			->setRequired(VALIDATE_REQUIRED);

		$form->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formSucceeded');
		$form->onValidate[] = array($this, 'formValidate');
		return $form;
	}


	public function formValidate($form, $values)
	{
		$values->name = str_replace("-", "_", \Nette\Utils\Strings::webalize($values->name));

		$query = $this->model->findAll()->where("name", $values->name);
		if ($this->isEditMode()) {
			$query->where($this->model->getColumnId() . " != ?", $this->getEditId());
		}
		$name = $query->fetch();
		if ($name) {
			$form['name']->addError("Name '%value' already exist.");
			$form->getPresenter()->flashMessage("Name already exist.", FLASH_FAILED);
		}
	}


	public function formSucceeded($form, $values)
	{
		unset($values->editId);

		$values->name = str_replace("-", "_", \Nette\Utils\Strings::webalize($values->name));

		if ($this->isEditMode()) {
			//if default - dont update name
			$isDefault = $this->model->findAll()
				->where($this->model->getColumnId(), $this->getEditId())
				->fetch()?->default;
			if($isDefault){
				unset($values->name);
			}

			$this->model->update($this->getEditId(), (array) $values);
		} else {
			$this->model->insert($values);
		}
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValues(Form $form, int $editId)
	{
		$defaults = $this->model->getById($editId);
		if ($defaults?->default) {
			$form["name"]->getControlPrototype()->readonly(true);
		}

		$form->setDefaults($defaults);
	}


	public function createModuleForm(int $editId = null): Form
	{
		$form = parent::create($editId);

		$moduleContainer = $form->addContainer("modules");
		$modules = $this->modelModules->getList();
		$otherPrivileges = $this->modelModules->getAllOtherPrivileges()->fetchAssoc("parentId|id");
		foreach ($modules as $moduleId => $title) {
			$radioControl = $moduleContainer->addRadioList((string) $moduleId, $title, array("any" => "any") + Acl::$privileges)
				->setDefaultValue("any");
			$radioControl->getSeparatorPrototype()->class("form-check-inline");
			$radioControl->getItemLabelPrototype()->class("form-check-label");

			//other privileges
			if (isset($otherPrivileges[$moduleId])) {
				$otherPrivilegesContainer = $moduleContainer->addContainer("otherPrivileges_".$moduleId);
				foreach ($otherPrivileges[$moduleId] as $subModuleId => $otherPrivilege) {
					$radioOPControl = $otherPrivilegesContainer->addRadioList((string) $subModuleId, $otherPrivilege["title"], array("deny"=>"deny", "allow"=>"allow"))
						->setDefaultValue("deny");
					$radioOPControl->getSeparatorPrototype()->class("form-check-inline");
					$radioOPControl->getItemLabelPrototype()->class("form-check-label");
				}
			}
		}

		$form->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formModuleSucceeded');
		return $form;
	}


	public function formModuleSucceeded($form, $values): void
	{
		unset($values->editId);
		$otherPrivileges = $this->modelModules->getAllOtherPrivileges()->fetchAssoc("parentId|id");
		$otherPrivilegesValues = array();
		foreach ($otherPrivileges as $moduleId => $otherPrivilege){
			$otherPrivilegesValues[$moduleId] = $values->modules["otherPrivileges_".$moduleId];
			unset($values->modules["otherPrivileges_".$moduleId]);
		}
		foreach ($values->modules as $moduleId => $privilege) {
			if ($privilege === "any") {
				$privilege = null;
			} else {
				$privilege = Acl::$privileges[$privilege];
			}
			$this->model->updateRoleModule((int) $this->getEditId(), $moduleId, $privilege);
		}
		foreach ($otherPrivileges as $moduleId => $otherPrivilege) {
			foreach ($otherPrivilege as $subModuleId => $privilege) {
				$this->model->updateRoleModule((int)  $this->getEditId(), $subModuleId,
					$otherPrivilegesValues[$moduleId][$subModuleId] == "allow" ? $privilege["privilege"] : null
					);
			}
		}

		/* if($this->isEditMode()){
		  $this->model->update($this->getEditId(), (array) $values);
		  }else{
		  $this->model->insert($values);
		  } */
	}


	/**
	 * Set default values to modal form
	 */
	public function setModuleFormDefaultValues(Form $form, int $editId): void
	{
		$defaults = $this->model->getRoleModules($editId);
		array_walk($defaults, function(&$value) {
			$value = array_search($value->privilege, Acl::$privileges);
			//value not exist between Acl::$privileges
		});

		$otherPrivileges = $this->modelModules->getAllOtherPrivileges()->fetchAssoc("id");
		$newDefaults = array();
		foreach ($defaults as $defaultModuleId => $value){
			if($value === false){
				$subModuleId = $otherPrivileges[$defaultModuleId]["id"];
				$moduleId = $otherPrivileges[$defaultModuleId]["parentId"];
				//create if not exist
				if(!isset($newDefaults["otherPrivileges_".$moduleId])){
					$newDefaults["otherPrivileges_".$moduleId] = array();
				}

				$newDefaults["otherPrivileges_".$moduleId][$subModuleId] = "allow";
			} else {
				$newDefaults[$defaultModuleId] = $value;;
			}
		}

		$form->setDefaults(array("modules" => $newDefaults));
	}

}
