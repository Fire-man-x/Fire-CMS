<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\Forms\BaseFormFactory;
use App\Model\Sections;
use App\Modules\UrlModule\UrlManager;
use Nette\Application\UI\Form;
use Nette\InvalidArgumentException;

/**
 * Formulář sekce (:Admin:Sections:detail) - aktivita + překlad v jazyce $language (název, úvodní text,
 * URL výpisu sekce, SEO). URL se ukládá přes UrlManager s typem `section`.
 */
class SectionFormFactory extends BaseFormFactory
{
	public function __construct(
		private readonly Sections $model,
		private readonly UrlManager $urlManager,
	) {
		parent::__construct();
	}


	public function create(int|string|null $editId = null, ?string $language = null): Form
	{
		if ($language === null) {
			throw new \InvalidArgumentException('Language cannot be null');
		}

		$form = parent::create($editId);

		$form->addCheckbox('active', 'Active')
			->setDefaultValue(true);

		$translation = $form->addContainer('translation');
		$translation->addText('title', 'Title')
			->setRequired(VALIDATE_REQUIRED)
			->setMaxLength(255)
			->getControlPrototype()->addClass('validate-url');
		$translation->addText('url', 'URL')
			->getControlPrototype()->addClass('validate-url-output');
		$translation->addTextArea('content', 'Text', null, 8)
			->getControlPrototype()->addClass(WYSIWYG_CLASS);
		$translation->addText('seoTitle', 'SEO title');
		$translation->addText('seoDescription', 'SEO description');
		$translation->addText('seoKeywords', 'SEO keywords');

		$form->addSubmit('send', 'Save');

		$form->onSuccess[] = function (Form $form) use ($language): void {
			$this->formSucceeded($form, $language);
		};

		if ($this->isEditMode()) {
			$sectionId = (int) $this->getEditId();
			$section = $this->model->getById($sectionId);
			if (!$section) {
				throw new \InvalidArgumentException("Can not edit section with id '$sectionId'");
			}
			$translationValues = $this->model->getTranslation($sectionId, $language)?->toArray() ?? [];
			try {
				$translationValues['url'] = $this->urlManager->getUrlByTypeAndKey(Sections::URL_TYPE, $sectionId, $language);
			} catch (InvalidArgumentException) {
				// sekce v tomto jazyce zatím URL nemá
			}
			$form->setDefaults($section->toArray() + ['translation' => $translationValues]);
		}

		return $form;
	}


	private function formSucceeded(Form $form, string $language): void
	{
		/** @var array{active: bool, translation: array{title: string, url: string, content: string, seoTitle: string, seoDescription: string, seoKeywords: string}} $values */
		$values = $form->getValues('array');
		$translation = $values['translation'];
		unset($values['translation']);

		$url = trim($translation['url']) !== '' ? $translation['url'] : $translation['title'];
		unset($translation['url']);

		if ($this->isEditMode()) {
			$sectionId = (int) $this->getEditId();
			$this->model->update($sectionId, $values);
		} else {
			$sectionId = $this->model->insertSection($values);
		}

		$this->model->saveTranslation($sectionId, $language, $translation);
		$this->urlManager->saveUrl(Sections::URL_TYPE, $sectionId, $language, $url);

		$presenter = $form->getPresenter();
		$presenter->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
		$presenter->redirect('this', ['id' => $sectionId]);
	}
}
