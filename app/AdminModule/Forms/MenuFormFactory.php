<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\Components\Menu\Model\Menus;
use App\Service\LanguageService;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;
use Nette\Utils\Html;


class MenuFormFactory extends AdminFormFactory
{
	public function __construct(
		private readonly Menus $model,
		private readonly LanguageService $languages,
		private readonly Translator $translator,
	) {
		parent::__construct();
	}


	public function create(int|string|null $editId = null): AdminForm
	{
		$form = parent::create($editId);

		$form->data->addCheckbox('active', 'Active');

		$form->data->addText('location', 'Template location')
			->setRequired(VALIDATE_REQUIRED);

		// název menu = nadpis po jazycích (firecms_menuDescriptions, kontejner locales[languageId][title]); menu nemá
		// jiný název (`name` odstraněn), proto je nadpis ve výchozím jazyce povinný - podle něj se menu pozná v administraci
		$defaultLanguage = $this->languages->getDefaultLanguage();
		foreach ($this->languages->getLanguages() as $languageId => $shortcut) {
			$title = $form->locales->addContainer((string) $languageId)
				->addText('title', Html::el()->setText($this->translator->translate('Title') . ' (' . $shortcut . ')'))
				->setNullable()
				->setMaxLength(255);
			if ($languageId === $defaultLanguage) {
				$title->setRequired(VALIDATE_REQUIRED)
					->setOption('description', $this->translator->translate('Name of the menu in administration and its heading on the web.'));
			} else {
				$title->setOption('description', $this->translator->translate('Heading of the menu on the web, optional.'));
			}
		}

		$form->buttons->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formSucceeded');

		return $form;
	}


	public function formSucceeded(AdminForm $form, AdminFormValues $values): void
	{
		unset($values->editId);

		$titles = [];
		foreach ($values->locales as $languageId => $locale) {
			$title = $locale['title'] ?? null;
			$titles[(string) $languageId] = is_string($title) ? $title : null;
		}

		//santized location (bez vyplněné location z nadpisu ve výchozím jazyce)
		$fallback = $titles[$this->languages->getDefaultLanguage()] ?? '';
		$location = $values->data['location'];
		$values->data['location'] = $this->model->getLocation(
			empty($location) || !is_string($location) ? $fallback : $location,
			$this->isEditMode() ? (int) $this->getEditId() : null,
		);

		if ($this->isEditMode()) {
			$menuId = (int) $this->getEditId();
			$this->model->update($menuId, (array) $values->data);
		} else {
			$menuId = $this->model->insert($values->data);
		}
		$this->model->saveTitles($menuId, $titles);
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValues(Form $form, int $editId): void
	{
		parent::setDefaultValues($form, $editId);

		$defaults = $this->model->getById($editId);
		$this->setEditId($editId);

		if(!$defaults){
			throw new \InvalidArgumentException("Can not edit item with id '".$editId."'");
		}

		$locales = [];
		foreach ($this->model->getTitles($editId) as $languageId => $title) {
			$locales[$languageId] = ['title' => $title];
		}

		$form->setDefaults($defaults->toArray() + ['locales' => $locales]);
	}

}
