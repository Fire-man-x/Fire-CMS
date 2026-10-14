<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\Model;
use App\Service\LanguageService;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;


class MetaFormFactory extends AdminFormFactory
{
	public static string $allLanguages = "All languages";

	/**
	 * Types
	 * @var array<string, string>
	 */
	public static array $types = array(
		'article' => 'Article',
		'category' => 'Category',
		'file' => 'File'
	);


	public function __construct(
		private readonly Translator $translator,
		private readonly LanguageService $languages,
		private readonly Model\Database\Metas $model,
	) {
		parent::__construct();
	}


	public function create(int|string|null $editId = null): AdminForm
	{
		$form = parent::create($editId);

		$form->data->addSelect('languageId', $this->translator->translate('Language'), $this->languages->getLanguages())
			->setTranslator(null)
			->setPrompt($this->translator->translate(self::$allLanguages));

		$form->data->addSelect('type', 'Type', self::$types)
			->setRequired(VALIDATE_REQUIRED);

		$form->data->addText('key', 'Key')
			->setRequired(VALIDATE_REQUIRED)
			->addRule(Form::Pattern, '\'%label\' can contains only this chars "a-z" or "_".', '[a-z_]+');

		$form->data->addText('value', 'Default value');

		$form->buttons->addSubmit('send', 'Save');

		$form->onValidate[] = array($this, 'formValidate');
		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}


	public function formValidate(AdminForm $form, AdminFormValues $values): void
	{
		$query = $this->model->findAll()
			->where("type", $values->data['type'])
			->where("key", $values->data['key']);

		if ($this->isEditMode()) {
			$query->where($this->model->getColumnId()." != ?", $this->getEditId());
		}

		$exist = $query->fetchAll();

		if ($exist) {
			if (!$values->data['languageId']) {
				//every item must be without language
				//- can be only once
				$error = true;
			} else {
				//every item must be with language
				$error = false;
				foreach ($exist as $item) {
					if ($item->languageId == null) {
						//cannot be twice
						$error = true;
					} elseif ($item->languageId == $values->data['languageId']) {
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


	public function formSucceeded(AdminForm $form, AdminFormValues $values): void
	{
		unset($values->editId);

		if ($this->isEditMode()) {
			$this->model->update((int) $this->getEditId(), (array) $values->data);
		} else {
			$this->model->insert($values->data);
		}
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValues(Form $form, int $editId): void
	{
		parent::setDefaultValues($form, $editId);

		$defaults = $this->model->getById($editId);
		if (!$defaults) {
			throw new \InvalidArgumentException("Can not edit item with id '" . $editId . "'");
		}

		$form->setDefaults($defaults);
	}

}
