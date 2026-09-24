<?php
declare(strict_types=1);

namespace App\Forms;

use App\Service\LanguageService;
use App\Model;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;


class TagFormFactory extends BaseFormFactory
{

	private Model\Tags $model;

	private Model\Modules $modelModules;

	private LanguageService $languages;


	public function __construct(Model\Tags $model, Model\Modules $modelModules, LanguageService $languages)
	{
		parent::__construct();
		$this->model = $model;
		$this->modelModules = $modelModules;
		$this->languages = $languages;
	}


	public function create(int|string $editId = null): Form
	{
		$form = parent::create($editId);

		foreach ($this->languages->getLanguages() as $languageId => $language) {
			$container = $form->addContainer($languageId);
			$container->addText("name", $language)
				->setNullable()
				->setTranslator(null);
		}

		$form->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formSucceeded');
		$form->onValidate[] = array($this, 'formValidate');
		return $form;
	}


	public function formValidate(Form $form, $values)
	{
		unset($values->editId);
		
		foreach ($values as $languageId => $value) {
			$query = $this->model->getTranslationTable()
				->where("languageId", $languageId)
				->where("name", $value->name);

			if($this->isEditMode()){
				$query->where($this->model->getForeignKeyColumn()." != ?", $this->getEditId());
			}
			$name = $query->fetch();
			if($name){
				$form[$languageId]['name']->addError("Name '%value' already exist.");
				$form->getPresenter()->flashMessage(sprintf($form->getTranslator()->translate("Tag in language '%s' already exist."), $languageId), FLASH_FAILED, false);
			}
		}
	}


	public function formSucceeded(Form $form, $values)
	{
		unset($values->editId);

		$allFilled = true;
		$allEmpty = true;
		foreach ($values as $value){
			if($value["name"] == null){
				$allFilled = false;
			}
			if($value["name"] != null){
				$allEmpty = false;
			}
		}

		if ($this->isEditMode()) {
			foreach ($this->languages->getLanguages() as $languageId => $language) {
				if($values[$languageId]["name"]){
					$this->model->updateTranslation((int) $this->getEditId(), $languageId, $values[$languageId]);
				}
			}
		} elseif(!$allEmpty) {
			$id = $this->model->insert(ArrayHash::from([]));
			foreach ($this->languages->getLanguages() as $languageId => $language) {
				if($values[$languageId]["name"]){
					$this->model->insertTranslation($id, $languageId, $values[$languageId]);
				}
			}
		}
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValues(Form $form, int $editId)
	{
		$defaults = $this->model->getTranslationTable()
			->where($this->model->getForeignKeyColumn(), $editId)
			->fetchAssoc("languageId");

		$form->setDefaults($defaults);
	}

}
