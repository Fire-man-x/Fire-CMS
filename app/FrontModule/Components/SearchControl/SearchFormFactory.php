<?php
declare(strict_types=1);

namespace App\FrontModule\Components\SearchControl;

use App\Forms\BaseFormFactory;
use App\Forms\FormFactory;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;


class SearchFormFactory extends BaseFormFactory
{
	private Translator $translator;

	public function __construct(FormFactory $factory, Translator $translator)
	{
		parent::__construct($factory);
		$this->translator = $translator;
	}


	public function create(int|string $editId = null): Form
	{
		$form = parent::create($editId);
		$form->setTranslator($this->translator);

		$form->getElementPrototype()
			->addClass("form-inline");

		$control = $form->addText('query', 'Search')
			->setRequired(VALIDATE_REQUIRED);
		$control->getLabelPrototype()
			->addClass("sr-only");
		$control->getControlPrototype()
			->type("search")
			->placeholder('Searching…');

		$form->addSubmit('send', 'Search');

		//defaults
		if($this->isEditMode()){
			$form->setDefaults(array(
				'query' => $this->getEditId()
			));
		}

		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}


	public function formSucceeded($form, $values): void
	{
		unset($values->editId);

		$form->getPresenter()->redirect(":Front:Search:default", array("query" => $values->query));
	}

}
