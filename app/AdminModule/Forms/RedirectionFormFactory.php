<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\Model\Database\Redirections;
use App\Service\LanguageService;
use Nette\Application\UI\Form;


class RedirectionFormFactory extends AdminFormFactory
{

	public function __construct(
		private readonly Redirections $model,
		private readonly LanguageService $languages,
	) {
		parent::__construct();
	}


	public function create(int|string|null $editId = null): AdminForm
	{
		$form = parent::create($editId);

		$form->data->addText('oldUrl', 'Old url')
			->setRequired(VALIDATE_REQUIRED)
			->setMaxLength(2000);

		$form->data->addText('newUrl', 'New url')
			->setRequired(VALIDATE_REQUIRED)
			->setMaxLength(2000);

		$form->buttons->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}


	public function formSucceeded(AdminForm $form, AdminFormValues $values): void
	{
		if ($this->isEditMode()) {
			$this->model->update((int) $this->getEditId(), (array) $values->data);
		} else {
			//todo zmenit jazyk
			$values->data['languageId'] = $this->languages->getDefaultLanguage();
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
