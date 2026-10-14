<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\Model;
use App\Service\LanguageService;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\TextInput;
use Nette\Utils\ArrayHash;


class TagFormFactory extends AdminFormFactory
{
	public function __construct(
		private readonly Model\Database\Tags $model,
		private readonly LanguageService $languages,
	) {
		parent::__construct();
	}


	public function create(int|string|null $editId = null): AdminForm
	{
		$form = parent::create($editId);

		$form->setTranslator(null);

		// název štítku po jazycích (firecms_tagDescriptions, kontejner locales[languageId][name])
		foreach ($this->languages->getLanguages() as $languageId => $language) {
			$form->locales->addContainer((string) $languageId)
				->addText("name", $language)
				->setNullable()
				->setTranslator(null);
		}

		$form->buttons->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formSucceeded');
		$form->onValidate[] = array($this, 'formValidate');
		return $form;
	}


	public function formValidate(AdminForm $form, AdminFormValues $values): void
	{
		foreach ($values->locales as $languageId => $value) {
			$query = $this->model->getTranslationTable()
				->where("languageId", $languageId)
				->where("name", $value['name']);

			if($this->isEditMode()){
				$query->where($this->model->getForeignKeyColumn()." != ?", $this->getEditId());
			}
			$name = $query->fetch();
			if($name){
				$nameControl = $form->getComponent('locales-' . $languageId . '-name', false);
				if ($nameControl instanceof TextInput) {
					$nameControl->addError("Name '%value' already exist.");
				}
				// LiveTranslator dosazuje parametry do %s sám (bez parametru by dosadil 0)
				$message = (string) $form->getTranslator()?->translate("Tag in language '%s' already exist.", $languageId);
				$form->getPresenter()->flashMessage($message, FLASH_FAILED);
			}
		}
	}


	public function formSucceeded(AdminForm $form, AdminFormValues $values): void
	{
		unset($values->editId);

		$allEmpty = true;
		foreach ($values->locales as $value){
			if($value["name"] != null){
				$allEmpty = false;
			}
		}

		if ($this->isEditMode()) {
			foreach ($values->locales as $languageId => $value) {
				if($value["name"]){
					$this->model->updateTranslation((int) $this->getEditId(), (string) $languageId, ArrayHash::from($value));
				}
			}
		} elseif(!$allEmpty) {
			$id = $this->model->insert(ArrayHash::from([]));
			foreach ($values->locales as $languageId => $value) {
				if($value["name"]){
					$this->model->insertTranslation($id, (string) $languageId, ArrayHash::from($value));
				}
			}
		}
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValues(Form $form, int $editId): void
	{
		$defaults = $this->model->getTranslationTable()
			->where($this->model->getForeignKeyColumn(), $editId)
			->fetchAssoc("languageId");

		$form->setDefaults(['locales' => $defaults]);
	}

}
