<?php
declare(strict_types=1);

namespace App\Forms;

use App\Model;
use App\Service\LanguageService;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;


class FilesManagerFileNameFormFactory extends BaseFormFactory
{

	private Translator $translator;

	private Model\Files $model;

	private \App\Service\LanguageService $languages;


	public function __construct(FormFactory $factory, Translator $translator, Model\Files $model, LanguageService $languages)
	{
		parent::__construct($factory);
		$this->translator = $translator;
		$this->model = $model;
		$this->languages = $languages;
	}


	public function create(int|string $editId = null): Form
	{
		$form = parent::create($editId);

		$form->addText("new_name", $this->translator->translate("Name"))
			//->setRequired()
			->setTranslator(null);

		/*if ($this->isEditMode()) {
			$data = $this->model->findById($this->getEditId())->fetch();
			if (!$data) {
				throw new \InvalidArgumentException("Can not edit item with id '" . $this->getEditId() . "'");
			}
			$form->setDefaults($data);
		}*/

		$form->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formSucceeded');

		return $form;
	}


	public function formSucceeded(Form $form, $values)
	{
		unset($values->editId);

		if ($this->isEditMode()) {
			$this->model->update($this->getEditId(), $values);
		} else {
			throw new \InvalidArgumentException("Can not insert file name.");
			$id = $this->model->insert($values);
			$form->getPresenter()->id = $id;
		}

		$form->getPresenter()->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValues(Form $form, int $editId)
	{
		parent::setDefaultValues($form, $editId);

		$defaults = $this->model->findById($editId)->fetch();
		if ($defaults) {
			$defaults = $defaults->toArray();
			/*if(!$defaults["new_name"]){
				$defaults["new_name"] = $defaults["original_name"];
			}*/
			$form["new_name"]->getControlPrototype()->placeholder($defaults["original_name"]);

			$form->setDefaults($defaults);
		}
	}


}
