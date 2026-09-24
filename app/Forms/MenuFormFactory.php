<?php
declare(strict_types=1);

namespace App\Forms;

use App\Components\Menu\Model\Menus;
use App\Service\LanguageService;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;
use Nette\Utils\Html;


class MenuFormFactory extends BaseFormFactory
{

	private Menus $model;


	public function __construct(
		Menus $model,
		private LanguageService $languages,
		private Translator $translator,
	) {
		parent::__construct();
		$this->model = $model;
	}


	public function create(int|string $editId = null): Form
	{
		$form = parent::create($editId);

		$form->addCheckbox('active', 'Active');

		$form->addText('location', 'Template location')
			->setRequired(VALIDATE_REQUIRED);

		// název menu = nadpis po jazycích (firecms_menuDescriptions); menu nemá jiný název (`name` odstraněn),
		// proto je nadpis ve výchozím jazyce povinný - podle něj se menu pozná v administraci
		$defaultLanguage = $this->languages->getDefaultLanguage();
		$titles = $form->addContainer('titles');
		foreach ($this->languages->getLanguages() as $languageId => $shortcut) {
			$title = $titles->addText((string) $languageId, Html::el()->setText($this->translator->translate('Title') . ' (' . $shortcut . ')'))
				->setNullable()
				->setMaxLength(255);
			if ($languageId === $defaultLanguage) {
				$title->setRequired(VALIDATE_REQUIRED)
					->setOption('description', $this->translator->translate('Name of the menu in administration and its heading on the web.'));
			} else {
				$title->setOption('description', $this->translator->translate('Heading of the menu on the web, optional.'));
			}
		}

		$form->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formSucceeded');

		return $form;
	}


	public function formSucceeded(Form $form, $values)
	{
		unset($values->editId);

		$titles = (array) $values->titles;
		unset($values->titles);

		//santized location (bez vyplněné location z nadpisu ve výchozím jazyce)
		$fallback = (string) ($titles[$this->languages->getDefaultLanguage()] ?? '');
		$values->location = $this->model->getLocation(empty($values->location) ? $fallback : $values->location, $this->getEditId());

		if ($this->isEditMode()) {
			$menuId = (int) $this->getEditId();
			$this->model->update($menuId, (array) $values);
		} else {
			$menuId = (int) $this->model->insert($values);
		}
		$this->model->saveTitles($menuId, $titles);
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValues(Form $form, int $editId)
	{
		parent::setDefaultValues($form, $editId);

		$defaults = $this->model->getById($editId);
		$this->setEditId($editId);

		if(!$defaults){
			throw new \InvalidArgumentException("Can not edit item with id '".$editId."'");
		}

		$form->setDefaults($defaults->toArray() + ['titles' => $this->model->getTitles($editId)]);
	}

}
