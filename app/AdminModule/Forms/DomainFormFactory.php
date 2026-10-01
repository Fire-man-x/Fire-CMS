<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\Forms\BaseFormFactory;
use App\Model;
use App\Service\LanguageService;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\TextInput;
use Nette\Localization\Translator;
use Nette\Utils\ArrayHash;
use Nette\Utils\Strings;


class DomainFormFactory extends BaseFormFactory
{

	private TextInput $domainControl;


	public function __construct(
		private readonly Translator             $translator,
		private readonly LanguageService        $languages,
		private readonly Model\Database\Domains $model,
	) {
		parent::__construct();
	}


	public function create(int|string $editId = null): Form
	{
		$form = parent::create($editId);

		$form->addCheckbox('active', 'Active');

		$form->addCheckbox('default', 'Default')
			->setOption('description', 'Used for generated links if a language has more than one domain.');

		$form->addSelect('languageId', 'Language', $this->languages->getLanguages())
			->setTranslator(null)
			->setRequired(VALIDATE_REQUIRED);

		$this->domainControl = $form->addText('domain', 'Domain')
			->setRequired(VALIDATE_REQUIRED)
			->setOption('description', 'Hostname only, e.g. "example.com", without scheme or path.')
			->addRule(Form::Pattern, '\'%label\' can contain only a hostname, e.g. "example.com".', '[a-z0-9]([a-z0-9.-]*[a-z0-9])?');
		$this->domainControl->getControlPrototype()->maxlength(255);

		$form->addSubmit('send', 'Save');

		$form->onValidate[] = array($this, 'formValidate');
		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}


	/**
	 * @param array<string,mixed> $values
	 */
	public function formValidate(Form $form, array $values): void
	{
		$domain = Strings::lower($values['domain'] ?? '');

		$query = $this->model->findAll()->where('domain', $domain);
		if ($this->isEditMode()) {
			$query->where($this->model->getColumnId() . ' != ?', $this->getEditId());
		}

		if ($query->fetch()) {
			$this->domainControl->addError('This domain is already used.');
			$form->getPresenter()->flashMessage('This domain is already used.', FLASH_FAILED);
		}
	}


	/**
	 * @param array<string,mixed> $values
	 */
	public function formSucceeded(Form $form, array $values): void
	{
		unset($values['editId']);
		$values['domain'] = Strings::lower($values['domain']);

		if ($this->isEditMode()) {
			$this->model->update((int) $this->getEditId(), $values);
		} else {
			$this->model->insert(ArrayHash::from($values));
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
