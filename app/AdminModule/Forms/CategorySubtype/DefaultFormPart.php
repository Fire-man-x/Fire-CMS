<?php
declare(strict_types=1);

namespace App\AdminModule\Forms\CategorySubtype;

use App\AdminModule\Forms\AdminForm;
use App\AdminModule\Forms\AdminFormValues;
use App\Modules\UrlModule\UrlManager;

class DefaultFormPart implements ICategoryFormType
{

	public function __construct(private readonly UrlManager $urlManager)
	{
	}


	public function getType(): string
	{
		return "default";
	}


	public function createFormPart(AdminForm $form, string $language): void
	{
		$translationContainer = $form->locales->addContainer($language);

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
		$form->data->addHidden('images');
	}


	public function setDefaultValuesToFormPart(AdminForm $form, array $values): array
	{
		return $values;
	}


	public function onSuccessFormPart(AdminForm $form, AdminFormValues $values, string $language, int $editId): AdminFormValues
	{
		/** @var array{title: string, url: string} $translation */
		$translation = $values->locales[$language];

		//santized url
		$url = empty($translation['url']) ? $translation['title'] : $translation['url'];
		$values->locales[$language]['url'] = $this->urlManager->validateUrl($url, 'category', $editId, $editId);

		return $values;
	}

}
