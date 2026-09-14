<?php
declare(strict_types=1);

namespace App\Forms\CategorySubtype;

use App\Model;
use Nette\Application\UI\Form;

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
		$translationContainer->addText('seo_title', 'SEO title');
		$translationContainer->addText('seo_description', 'SEO description');
		$translationContainer->addText('seo_keywords', 'SEO keywords');

		//Files
		$form->addHidden('images');
	}


	public function setDefaultValuesToFormPart(Form $form, $values)
	{
		return $values;
	}


	public function onSuccessFormPart(Form $form, $values, $editId)
	{
		$translationContainer = $values->translation;

		//santized url
		//$translationContainer->url = "";

		$values->translation = $translationContainer;

		return $values;
	}

}
