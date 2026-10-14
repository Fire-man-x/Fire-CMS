<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\Model\Database\Pages;
use App\Modules\UrlModule\UrlManager;
use Nette\InvalidArgumentException;
use Nette\Localization\Translator;
use Nette\Security\User;

/**
 * Formulář stránky (:Admin:Pages:detail) - společné údaje stránky + překlad v jazyce $language
 * (název, obsah, URL, SEO). URL se ukládá přes UrlManager s typem `page`.
 */
class PageFormFactory extends AdminFormFactory
{
	public function __construct(
		private readonly Pages $model,
		private readonly UrlManager $urlManager,
		private readonly User $user,
		private readonly Translator $translator,
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
		$form->data->addCheckbox('public', 'Public')
			->setDefaultValue(true)
			->setOption('description', 'Unchecked = visible only for signed in users.');
		$form->data->addSelect('status', 'Status', Pages::Statuses)
			->setRequired(VALIDATE_REQUIRED)
			->setDefaultValue('draft');
		$form->data->addSelect('template', 'Template', Pages::Templates)
			->setPrompt('Default');

		// nadřazená stránka - bez stránky samotné a jejích podstránek (nevznikl by cyklus)
		$editId = $this->isEditMode() ? (int) $this->getEditId() : null;
		$form->data->addSelect('parentId', 'Parent page', $this->getParentOptions($editId, $language))
			->setTranslator(null)
			->setPrompt($this->translator->translate('— top level —'));

		$translation = $form->locales->addContainer($language);
		$translation->addText('title', 'Title')
			->setRequired(VALIDATE_REQUIRED)
			->setMaxLength(512)
			->getControlPrototype()->addClass('validate-url');
		$translation->addText('url', 'URL')
			->getControlPrototype()->addClass('validate-url-output');
		$translation->addTextArea('content', 'Text', null, 12)
			->getControlPrototype()->addClass(WYSIWYG_CLASS);

		$translation->addText('seoTitle', 'SEO title');
		$translation->addText('seoDescription', 'SEO description');
		$translation->addText('seoKeywords', 'SEO keywords');

		$form->buttons->addSubmit('send', 'Save');

		$form->onSuccess[] = function (AdminForm $form, AdminFormValues $values) use ($language): void {
			$this->formSucceeded($form, $values, $language);
		};

		if ($this->isEditMode()) {
			$this->setDefaults($form, (int) $this->getEditId(), $language);
		}

		return $form;
	}


	/**
	 * Stránky ve stromovém pořadí (odsazené), bez $editId a jejích podstránek
	 * @return array<int, string>
	 */
	private function getParentOptions(?int $editId, string $language): array
	{
		$excluded = $editId !== null ? $this->model->getSubtreeIds($editId) : [];
		$options = [];
		foreach ($this->model->getTree($language) as $page) {
			if (!in_array($page['id'], $excluded, true)) {
				$options[$page['id']] = str_repeat('— ', $page['level']) . ($page['title'] ?? '#' . $page['id']);
			}
		}

		return $options;
	}


	private function setDefaults(AdminForm $form, int $pageId, string $language): void
	{
		$page = $this->model->getById($pageId);
		if (!$page) {
			throw new \InvalidArgumentException("Can not edit page with id '$pageId'");
		}

		$translation = $this->model->getTranslation($pageId, $language)?->toArray() ?? [];
		try {
			$translation['url'] = $this->urlManager->getUrlByTypeAndKey(Pages::URL_TYPE, $pageId, $language);
		} catch (InvalidArgumentException) {
			// stránka v tomto jazyce zatím URL nemá
		}

		$form->setDefaults($page->toArray() + ['locales' => [$language => $translation]]);
	}


	private function formSucceeded(AdminForm $form, AdminFormValues $values, string $language): void
	{
		/** @var array{active: bool, public: bool, status: string, template: string|null, parentId: int|null} $data */
		$data = (array) $values->data;
		/** @var array{title: string, url: string, content: string, seoTitle: string, seoDescription: string, seoKeywords: string} $translation */
		$translation = $values->locales[$language];

		// URL z vyplněného pole, jinak z názvu (UrlManager ji převede na unikátní slug)
		$url = trim($translation['url']) !== '' ? $translation['url'] : $translation['title'];
		unset($translation['url']);

		if ($this->isEditMode()) {
			$pageId = (int) $this->getEditId();
			$this->model->updatePage($pageId, $data);
		} else {
			$pageId = $this->model->insertPage($data + ['createdBy' => $this->user->getId()]);
		}

		$this->model->saveTranslation($pageId, $language, $translation);
		$this->urlManager->saveUrl(Pages::URL_TYPE, $pageId, $language, $url);

		$presenter = $form->getPresenter();
		$presenter->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
		$presenter->redirect('this', ['id' => $pageId]);
	}
}
