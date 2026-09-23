<?php
declare(strict_types=1);

namespace App\Forms;

use App\Service\LanguageService;
use App\Model;
use App\Service\Meta;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;

class MetaValueFormFactory extends BaseFormFactory
{

	private Translator $translator;

	private LanguageService $languages;

	private Model\Metas $metasModel;

	private Meta $metasService;

	public string $type;


	public function __construct(FormFactory $factory, Translator $translator, LanguageService $languages, Model\Metas $metasModel, Meta $metaService)
	{
		parent::__construct($factory);
		$this->translator = $translator;
		$this->languages = $languages;
		$this->metasModel = $metasModel;
		$this->metasService = $metaService;
	}


	public function setType($type)
	{
		$this->type = $type;
		return $this;
	}


	public function create(int|string $editId = null, $linkCallback = null, $language = null): Form
	{
		$form = parent::create($editId);

		$items = $this->metasModel->findAll()
			->where("type", $this->type)
			->where("languageId = ? OR languageId IS NULL", $language)
			->fetchAll();

		if ($items) {
			foreach ($items as $item) {
				$langDesc = $item->languageId ? " (".$this->languages->getLanguage($item->languageId).")" : "";
				$form->addText($item->id, $item->key.$langDesc)
					->setTranslator(null)
					->getControlPrototype()
						->placeholder($item->value);
			}
			$form->addSubmit('send', 'Save');
		} else {
			$form->addGroup(\Nette\Utils\Html::el("a", array("href"=> $linkCallback("Settings:Metas:default")))->setText($this->translator->translate("No item.")));
		}

		//edit mode
		if ($this->isEditMode()) {
			$defaults = $this->metasService->getStructureByColumnId($this->type, $language, $this->getEditId(), true);
			$values = array();
			foreach ($defaults as $default){
				$values[$default["id"]] = $default["value"];
			}

			$form->setValues($values);
		}

		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}


	public function formSucceeded($form, $values)
	{
		unset($values->editId);

		$model = $this->metasService->getModelByType($this->type);

		$model->useMetas($this->getEditId(), $values);

		/*
		if ($this->isEditMode()) {
			$model->update($this->getEditId(), (array) $values);
		} else {
			$model->insert($values);
		}
		 */
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValues(Form $form, int $editId)
	{
		parent::setDefaultValues($form, $editId);

		$defaults = $this->metasModel->getById($editId);

		$form->setDefaults($defaults);
	}

}
