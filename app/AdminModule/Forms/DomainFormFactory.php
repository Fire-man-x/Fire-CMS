<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\Model;
use App\Service\LanguageService;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\TextInput;
use Nette\Utils\Strings;


class DomainFormFactory extends AdminFormFactory
{
	public function __construct(
		private readonly LanguageService $languages,
		private readonly Model\Database\Domains $model,
	) {
		parent::__construct();
	}


	public function create(int|string $editId = null): AdminForm
	{
		$form = parent::create($editId);

		$form->data->addCheckbox('active', 'Active');

		$form->data->addCheckbox('default', 'Default')
			->setOption('description', 'Used for generated links if a language has more than one domain.');

		$form->data->addSelect('languageId', 'Language', $this->languages->getLanguages())
			->setTranslator(null)
			->setRequired(VALIDATE_REQUIRED);

		$domainControl = $form->data->addText('domain', 'Domain')
			->setRequired(VALIDATE_REQUIRED)
			->setOption('description', 'Hostname only, e.g. "example.com", without scheme or path.')
			->addRule(Form::Pattern, '\'%label\' can contain only a hostname, e.g. "example.com".', '[a-z0-9]([a-z0-9.-]*[a-z0-9])?');
		$domainControl->getControlPrototype()->maxlength(255);

		$form->buttons->addSubmit('send', 'Save');

		$form->onValidate[] = array($this, 'formValidate');
		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}


	public function formValidate(AdminForm $form, AdminFormValues $values): void
	{
		$domain = Strings::lower($values->data['domain'] ?? '');

		$query = $this->model->findAll()->where('domain', $domain);
		if ($this->isEditMode()) {
			$query->where($this->model->getColumnId() . ' != ?', $this->getEditId());
		}

		if ($query->fetch()) {
			$domainControl = $form->data['domain'];
			if($domainControl instanceof TextInput){
				$domainControl->addError('This domain is already used.');
			}
			$form->getPresenter()->flashMessage('This domain is already used.', FLASH_FAILED);
		}
	}


	public function formSucceeded(AdminForm $form, AdminFormValues $values): void
	{
		unset($values->editId);
		$values->data['domain'] = Strings::lower($values->data['domain']);

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

		$form->setDefaults($defaults);
	}

}
