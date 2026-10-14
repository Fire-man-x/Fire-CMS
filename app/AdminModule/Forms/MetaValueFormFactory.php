<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\Model;
use App\Service\LanguageService;
use App\Service\Meta;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;
use Nette\Utils\ArrayHash;
use Nette\Utils\Html;

class MetaValueFormFactory extends AdminFormFactory
{
	public string $type;


	public function __construct(
		private readonly Translator $translator,
		private readonly LanguageService $languages,
		private readonly Model\Database\Metas $metasModel,
		private readonly Meta $metasService,
	) {
		parent::__construct();
	}


	public function setType(string $type): static
	{
		$this->type = $type;
		return $this;
	}


	public function create(int|string|null $editId = null, ?callable $linkCallback = null, ?string $language = null): AdminForm
	{
		if ($language === null) {
			throw new \InvalidArgumentException('Language cannot be null');
		}

		$form = parent::create($editId);

		$items = $this->metasModel->findAll()
			->where("type", $this->type)
			->where("languageId = ? OR languageId IS NULL", $language)
			->fetchAll();

		if ($items) {
			foreach ($items as $item) {
				$metaId = $item->id;
				$key = $item->key;
				$languageId = $item->languageId;
				if ((!is_int($metaId) && !is_string($metaId)) || !is_string($key)) {
					continue;
				}
				$langDesc = is_string($languageId) && $languageId !== '' ? " (".$this->languages->getLanguage($languageId).")" : "";
				$form->data->addText((string) $metaId, $key.$langDesc)
					->setTranslator(null)
					->getControlPrototype()
						->placeholder($item->value);
			}
			$form->buttons->addSubmit('send', 'Save');
		} else {
			$noItem = $this->translator->translate("No item.");
			$form->addGroup($linkCallback !== null ? Html::el("a", array("href"=> $linkCallback("Settings:Metas:default")))->setText($noItem) : $noItem);
		}

		//edit mode
		if ($this->isEditMode()) {
			$defaults = $this->metasService->getStructureByColumnId($this->type, $language, (int) $this->getEditId(), true);
			$values = array();
			foreach ($defaults as $default){
				if ($default instanceof ArrayHash && (is_int($default->id) || is_string($default->id))) {
					$values[$default->id] = $default->value;
				}
			}

			$form->setDefaults(['data' => $values]);
		}

		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}


	public function formSucceeded(AdminForm $form, AdminFormValues $values): void
	{
		unset($values->editId);

		$model = $this->metasService->getModelByType($this->type);

		$model->useMetas((int) $this->getEditId(), (array) $values->data);

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
	public function setDefaultValues(Form $form, int $editId): void
	{
		parent::setDefaultValues($form, $editId);

		$defaults = $this->metasModel->getById($editId);
		if (!$defaults) {
			throw new \InvalidArgumentException("Can not edit item with id '" . $editId . "'");
		}

		$form->setDefaults($defaults);
	}

}
