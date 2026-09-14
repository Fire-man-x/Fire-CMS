<?php
declare(strict_types=1);

namespace App\Forms\CategorySubtype;

use Nette\Application\UI\Form;

class UrlFormPart implements ICategoryFormType
{


	public function getType()
	{
		return "url";
	}


	public function createFormPart(Form $form)
	{
		$translationContainer = $form->addContainer('translation');

		$translationContainer->addText('title', 'Title')
			->setRequired(VALIDATE_REQUIRED);

		//url
		$translationContainer->addText('url', 'URL');
	}


	public function setDefaultValuesToFormPart(Form $form, $values)
	{
		return $values;
	}


	public function onSuccessFormPart(Form $form, $values, $editId)
	{
		return $values;
	}

}
