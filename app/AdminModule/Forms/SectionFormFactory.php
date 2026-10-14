<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\Model\Database\Sections;
use App\Modules\UrlModule\UrlManager;
use Nette\InvalidArgumentException;

/**
 * Formulář sekce (:Admin:Sections:detail) - aktivita + překlad v jazyce $language (název, úvodní text,
 * URL výpisu sekce, SEO). URL se ukládá přes UrlManager s typem `section`.
 */
class SectionFormFactory extends AdminFormFactory
{
	public function __construct(
		private readonly Sections $model,
		private readonly UrlManager $urlManager,
	) {
		parent::__construct();
	}


	public function create(int|string|null $editId = null, ?string $language = null): AdminForm
	{
		if ($language === null) {
			throw new \InvalidArgumentException('Language cannot be null');
		}

		$form = parent::create($editId);

		$form->data->addCheckbox('active', 'Active')
			->setDefaultValue(true);

		$translation = $form->locales->addContainer($language);
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

		$form->buttons->addSubmit('send', 'Save');

		$form->onSuccess[] = function (AdminForm $form, AdminFormValues $values) use ($language): void {
			$this->formSucceeded($form, $values, $language);
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
			$form->setDefaults($section->toArray() + ['locales' => [$language => $translationValues]]);
		}

		return $form;
	}


	private function formSucceeded(AdminForm $form, AdminFormValues $values, string $language): void
	{
		/** @var array{active: bool} $data */
		$data = (array) $values->data;
		/** @var array{title: string, url: string, content: string, seoTitle: string, seoDescription: string, seoKeywords: string} $translation */
		$translation = $values->locales[$language];

		$url = trim($translation['url']) !== '' ? $translation['url'] : $translation['title'];
		unset($translation['url']);

		if ($this->isEditMode()) {
			$sectionId = (int) $this->getEditId();
			$this->model->update($sectionId, $data);
		} else {
			$sectionId = $this->model->insertSection($data);
		}

		$this->model->saveTranslation($sectionId, $language, $translation);
		$this->urlManager->saveUrl(Sections::URL_TYPE, $sectionId, $language, $url);

		$presenter = $form->getPresenter();
		$presenter->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
		$presenter->redirect('this', ['id' => $sectionId]);
	}
}
