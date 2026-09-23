<?php
declare(strict_types=1);

namespace App\Forms\CategorySubtype;

use App\Model;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;

class HomepageFormPart implements ICategoryFormType
{

	private Model\Categories $categoryModel;


	public function __construct(Model\Categories $categoryModel)
	{
		$this->categoryModel = $categoryModel;
	}


	public function getType()
	{
		return "homepage";
	}


	public function createFormPart(Form $form)
	{
		$translationContainer = $form->addContainer('translation');
		//$form->getco

		$translationContainer->addText('title', 'Title')
			->setRequired(VALIDATE_REQUIRED)
			->getControlPrototype()->addClass("validate-url");

		//excerpt
		$translationContainer->addTextArea('excerpt', 'Excerpt', null, 4)
			->getControlPrototype()->addClass(WYSIWYG_CLASS);

		//text
		$contentControl = $translationContainer->addTextArea('content', 'Text', null, 10);
		$contentControl->getControlPrototype()->addClass(WYSIWYG_CLASS);
		//$contentControl->setRequired(VALIDATE_REQUIRED);
		//SEO
		$translationContainer->addText('seoTitle', 'SEO title');
		$translationContainer->addText('seoDescription', 'SEO description');
		$translationContainer->addText('seoKeywords', 'SEO keywords');

		//Files
		$form->addHidden('images');
	}


	public function setDefaultValuesToFormPart(Form $form, $values)
	{
		return $values;
	}


	public function onSuccessFormPart(Form $form, ArrayHash $values, int $editId): ArrayHash
	{
		$translationContainer = $values->translation;

		//santized url
		//$translationContainer->url = "";

		$values->translation = $translationContainer;

		return $values;
	}

}
