<?php
declare(strict_types=1);

namespace App\Forms\CategorySubtype;

use App\Modules\UrlModule\UrlManager;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;

class DefaultFormPart implements ICategoryFormType
{

	private UrlManager $urlManager;


	public function __construct(UrlManager $urlManager)
	{
		$this->urlManager = $urlManager;
	}


	public function getType()
	{
		return "default";
	}


	public function createFormPart(Form $form)
	{
		$translationContainer = $form->addContainer('translation');

		$translationContainer->addText('title', 'Title')
			->setRequired(VALIDATE_REQUIRED)
			->getControlPrototype()->addClass("validate-url");

		//url
		$translationContainer->addText('url', 'URL')
			->getControlPrototype()->addClass("validate-url-output");

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
		$santizedUrl = $this->urlManager->validateUrl(empty($translationContainer->url) ? $translationContainer->title : $translationContainer->url, 'category', $editId, $editId);
		if ($santizedUrl != $translationContainer->url) {
			$translationContainer->url = $santizedUrl;
		}

		$values->translation = $translationContainer;

		return $values;
	}

}
