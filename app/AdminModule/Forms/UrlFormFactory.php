<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\Model\Database\Urls;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\TextInput;

/**
 * Úprava hezké URL obsahu (:Admin:Settings:Urls). URL vzniká spolu s obsahem (UrlManager::saveUrl()),
 * tady jde jen upravit adresu existujícího záznamu - typ, klíč a jazyk se nemění.
 */
class UrlFormFactory extends AdminFormFactory
{

	public function __construct(private readonly Urls $model)
	{
		parent::__construct();
	}


	public function create(int|string|null $editId = null): AdminForm
	{
		$form = parent::create($editId);

		$form->data->addText('url', 'Url')
			->setRequired(VALIDATE_REQUIRED)
			->setMaxLength(2000);

		$form->buttons->addSubmit('send', 'Save');

		$form->onValidate[] = array($this, 'formValidate');
		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}


	public function formValidate(AdminForm $form, AdminFormValues $values): void
	{
		if (!$this->isEditMode()) {
			$form->addError('URL is created together with its content, here it can only be edited.');
			return;
		}

		// URL je unikátní napříč jazyky i typy, stejně jako v UrlManager::validateUrl()
		$exists = $this->model->findAll()
			->where('url', self::normalizeUrl($values->data['url']))
			->where($this->model->getColumnId() . ' != ?', $this->getEditId())
			->fetch();
		if ($exists) {
			$urlControl = $form->data['url'];
			if ($urlControl instanceof TextInput) {
				$urlControl->addError('This URL is already used.');
			}
		}
	}


	public function formSucceeded(AdminForm $form, AdminFormValues $values): void
	{
		$this->model->update((int) $this->getEditId(), ['url' => self::normalizeUrl($values->data['url'])]);
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


	/**
	 * URL se ukládá bez okrajových mezer a lomítek (CustomRouter hledá cestu bez úvodního "/")
	 */
	private static function normalizeUrl(mixed $url): string
	{
		return trim(is_string($url) ? $url : '', " \t\n\r\0\x0B/");
	}

}
