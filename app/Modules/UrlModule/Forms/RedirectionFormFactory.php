<?php
declare(strict_types=1);

namespace App\Modules\UrlModule\Forms;

use App\Modules\UrlModule\RedirectionsModel;
use App\Forms\BaseFormFactory;
use App\Forms\FormFactory;
use App\Service\LanguageService;
use Nette\Application\UI\Form;


class RedirectionFormFactory extends BaseFormFactory
{

	public function __construct(FormFactory $factory, private RedirectionsModel $model, private LanguageService $languages)
	{
		parent::__construct($factory);
	}


	public function create(int|string $editId = null): Form
	{
		$form = parent::create($editId);

		$form->addText('old_url', 'Old url')
			->setRequired(VALIDATE_REQUIRED);

		$form->addText('new_url', 'New url')
			->setRequired(VALIDATE_REQUIRED);

		$form->addSubmit('send', 'Save');

		$form->onValidate[] = array($this, 'formValidate');
		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}


	public function formValidate($form, $values)
	{
		//only if type is disabled by ajax
		/*if($form['type']->hasErrors()){
			unset($form['type']);
		}*/
	}


	public function formSucceeded($form, $values)
	{
		unset($values->editId);

		if ($this->isEditMode()) {
			$this->model->update($this->getEditId(), $values);
		} else {
			//todo zmenit jazyk
			$values["language_id"] = $this->languages->getDefaultLanguage();
			$this->model->insert($values);
		}
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValues(Form $form, int $editId)
	{
		parent::setDefaultValues($form, $editId);

		$defaults = $this->model->getById($editId);

		$form->setDefaults($defaults);
	}

}
