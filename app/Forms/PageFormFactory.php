<?php
declare(strict_types=1);

namespace App\Forms;

use App\Model\Pages;
use App\Modules\UrlModule\UrlManager;
use Nette\Application\UI\Form;
use Nette\InvalidArgumentException;
use Nette\Localization\Translator;
use Nette\Security\User;

/**
 * Formulář stránky (:Admin:Pages:detail) - společné údaje stránky + překlad v jazyce $language
 * (název, obsah, URL, SEO). URL se ukládá přes UrlManager s typem `page`.
 */
class PageFormFactory extends BaseFormFactory
{
	public function __construct(
		private readonly Pages $model,
		private readonly UrlManager $urlManager,
		private readonly User $user,
		private readonly Translator $translator,
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
		$form->addCheckbox('public', 'Public')
			->setDefaultValue(true)
			->setOption('description', 'Unchecked = visible only for signed in users.');
		$form->addSelect('status', 'Status', Pages::Statuses)
			->setRequired(VALIDATE_REQUIRED)
			->setDefaultValue('draft');

		// nadřazená stránka - bez stránky samotné a jejích podstránek (nevznikl by cyklus)
		$editId = $this->isEditMode() ? (int) $this->getEditId() : null;
		$form->addSelect('parentId', 'Parent page', $this->getParentOptions($editId, $language))
			->setTranslator(null)
			->setPrompt($this->translator->translate('— top level —'));

		$translation = $form->addContainer('translation');
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

		$form->addSubmit('send', 'Save');

		$form->onSuccess[] = function (Form $form) use ($language): void {
			$this->formSucceeded($form, $language);
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


	private function setDefaults(Form $form, int $pageId, string $language): void
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

		$form->setDefaults($page->toArray() + ['translation' => $translation]);
	}


	private function formSucceeded(Form $form, string $language): void
	{
		/** @var array{active: bool, public: bool, status: string, parentId: int|null, translation: array{title: string, url: string, content: string, seoTitle: string, seoDescription: string, seoKeywords: string}} $values */
		$values = $form->getValues('array');
		$translation = $values['translation'];
		unset($values['translation']);

		// URL z vyplněného pole, jinak z názvu (UrlManager ji převede na unikátní slug)
		$url = trim($translation['url']) !== '' ? $translation['url'] : $translation['title'];
		unset($translation['url']);

		if ($this->isEditMode()) {
			$pageId = (int) $this->getEditId();
			$this->model->updatePage($pageId, $values);
		} else {
			$pageId = $this->model->insertPage($values + ['createdBy' => $this->user->getId()]);
		}

		$this->model->saveTranslation($pageId, $language, $translation);
		$this->urlManager->saveUrl(Pages::URL_TYPE, $pageId, $language, $url);

		$presenter = $form->getPresenter();
		$presenter->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
		$presenter->redirect('this', ['id' => $pageId]);
	}
}
