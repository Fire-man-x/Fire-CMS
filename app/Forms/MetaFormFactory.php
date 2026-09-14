<?php
declare(strict_types=1);

namespace App\Forms;

use App\Service\LanguageService;
use App\Model;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;


class MetaFormFactory extends BaseFormFactory
{
	public static string $allLanguages = "All languages";

	private Translator $translator;

	private Model\Metas $model;

	private LanguageService $languages;

	/**
	 * Types
	 */
	public static array $types = array(
		'article' => 'Article',
		'category' => 'Category',
		'file' => 'File'
	);


	public function __construct(FormFactory $factory, Translator $translator, LanguageService $languages, Model\Metas $model)
	{
		parent::__construct($factory);
		$this->translator = $translator;
		$this->languages = $languages;
		$this->model = $model;
	}


	public function create(int|string $editId = null): Form
	{
		$form = parent::create($editId);

		$form->addSelect('language_id', $this->translator->translate('Language'), $this->languages->getLanguages())
			->setTranslator(null)
			->setPrompt($this->translator->translate(self::$allLanguages));

		$form->addSelect('type', 'Type', self::$types)
			->setRequired(VALIDATE_REQUIRED);

		$keyControl =$form->addText('key', 'Key')
			->setRequired(VALIDATE_REQUIRED);
		$keyControl->addRule(Form::PATTERN, '\'%label\' can contains only this chars "a-z" or "_".', '[a-z_]+');

		$form->addText('value', 'Default value');

		$form->addSubmit('send', 'Save');

		$form->onValidate[] = array($this, 'formValidate');
		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}


	public function formValidate(Form $form, array $values)
	{
		$query = $this->model->getAll()
			->where("type", $values->type)
			->where("key", $values->key);

		if ($this->isEditMode()) {
			$query->where($this->model->getColumnId()." != ?", $this->getEditId());
		}

		$exist = $query->fetchAll();

		if ($exist) {
			if (!$values->language_id) {
				//every item must be without language
				//- can be only once
				$error = true;
			} else {
				//every item must be with language
				$error = false;
				foreach ($exist as $item) {
					if ($item->language_id == null) {
						//cannot be twice
						$error = true;
					} elseif ($item->language_id == $values->language_id) {
						//already exist
						$error = true;
					}
				}
			}

			if ($error) {
				$form->addError(FAIL_SAVE);
				$form->getPresenter()->flashMessage(FAIL_SAVE, FLASH_FAILED);
			}
		}
	}


	public function formSucceeded($form, $values)
	{
		unset($values->editId);

		if ($this->isEditMode()) {
			$this->model->update($this->getEditId(), $values);
		} else {
			$this->model->insert($values);
		}
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValues(Form $form, int $editId)
	{
		parent::setDefaultValues($form, $editId);

		$defaults = $this->model->findById($editId)->fetch();

		$form->setDefaults($defaults);
	}

}
