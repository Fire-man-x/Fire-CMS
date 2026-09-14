<?php
declare(strict_types=1);

namespace App\Forms\CategorySubtype;

use App\Model;
use Nette\Application\UI\Form;

class TextBoxFormPart implements ICategoryFormType
{

	private Model\Categories $categoryModel;


	public function __construct(Model\Categories $categoryModel)
	{
		$this->categoryModel = $categoryModel;
	}


	public function getType()
	{
		return "textBox";
	}


	public function createFormPart(Form $form)
	{
		$translationContainer = $form->addContainer('translation');

		$translationContainer->addText('title', 'Title')
			->setRequired(VALIDATE_REQUIRED);

		//text
		$contentControl = $translationContainer->addTextArea('content', 'Text', null, 10);
		$contentControl->getControlPrototype()->addClass(WYSIWYG_CLASS);
	}


	public function setDefaultValuesToFormPart(Form $form, $values)
	{
		return $values;
	}


	public function onSuccessFormPart(Form $form, $values, $editId)
	{
		$translationContainer = $values->translation;

		$values->translation = $translationContainer;

		return $values;
	}

}
