<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use App\AdminModule\Forms\SectionFormFactory;
use App\Attributes\Privilege;
use App\Attributes\Resource;
use App\Attributes\Secured;
use App\Model\Sections;
use App\Modules\UrlModule\UrlManager;
use App\Service\LanguageService;
use Contributte\Datagrid\Datagrid;
use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Form;

/**
 * Sekce (App\Model\Sections) - seznam s řazením přetažením a detail s překlady po jazycích. Články a
 * kategorie sekce spravují :Admin:Articles / :Admin:Categories s parametrem `section`.
 */
#[Secured]
#[Resource('Sections')]
#[Privilege('view')]
class SectionsPresenter extends BasePresenter
{
	#[Persistent]
	public ?int $id = null;

	#[Persistent]
	public ?string $language = null;

	private string $actualLanguage;


	/** model sekcí je `$this->sectionsModel` z BasePresenter (injectSections()) */
	public function __construct(
		private readonly SectionFormFactory $sectionFactory,
		private readonly LanguageService $languages,
		private readonly UrlManager $urlManager,
	) {
		parent::__construct();
	}


	protected function startup(): void
	{
		parent::startup();

		$this->addBreadCrumbLink('Sections', $this->link(':Admin:Sections:default', ['id' => null]));

		if ($this->language === $this->languages->getDefaultLanguage()) {
			$this->redirect('this', ['language' => null]);
		}
		$this->actualLanguage = $this->language !== null && $this->languages->existLanguage($this->language)
			? $this->language
			: $this->languages->getDefaultLanguage();
	}


	#[Secured]
	#[Resource('Sections')]
	#[Privilege('edit')]
	public function actionDetail(): void
	{
		if ($this->id !== null) {
			if (!$this->sectionsModel->getById($this->id)) {
				$this->error("Section '$this->id' doesn't exist.");
			}
			$translation = $this->sectionsModel->getTranslation($this->id, $this->actualLanguage);
			$this->addBreadCrumbLink(
				$translation && is_string($translation->title) ? $translation->title : 'New translation',
				$this->link(':Admin:Sections:detail', ['id' => $this->id]),
				null,
				!$translation,
			);
		} else {
			$this->addBreadCrumbLink('New', $this->link(':Admin:Sections:detail', ['id' => null]));
		}
	}


	public function renderDetail(): void
	{
		$this->template->languages = $this->languages->getLanguages();
		$this->template->actualLanguage = $this->actualLanguage;
		$this->template->sectionId = $this->id;
		$this->template->hasUrl = $this->id !== null
			&& $this->urlManager->existUrlByTypeAndKey(Sections::URL_TYPE, $this->id, $this->actualLanguage);
	}


	protected function createComponentSectionsGrid(string $name): Datagrid
	{
		$rows = [];
		foreach ($this->sectionsModel->getList($this->actualLanguage) as $id => $title) {
			$section = $this->sectionsModel->getById($id);
			$rows[] = [
				'id' => $id,
				'title' => $title,
				'active' => (int) (bool) $section?->active,
				'content' => $this->sectionsModel->countContent($id),
			];
		}

		$grid = new Datagrid($this, $name);
		$grid->setPrimaryKey('id');
		$grid->setDataSource($rows);
		$grid->setTranslator($this->translator);
		$grid->setSortable();
		$grid->setPagination(false);

		$active = $grid->addColumnStatus('active', 'A.');
		$active->addOption(0, 'Unactive')->setClass('btn-danger')->setIcon('ban')->setTitle('Set as active');
		$active->addOption(1, 'Active')->setClass('btn-success')->setIcon('check-circle')->setTitle('Set as unactive');
		$active->onChange[] = function ($id, $value): void {
			$this->handleActivate((int) $id, (bool) $value);
		};

		$grid->addColumnText('title', 'Title');
		$grid->addColumnNumber('content', 'Categories and articles');

		$grid->addAction('articles', 'Articles', ':Admin:Articles:default', ['section' => 'id'])
			->setClass('btn btn-secondary btn-sm')
			->setIcon('file-alt')
			->setTitle('Articles');
		$grid->addAction('categories', 'Categories', ':Admin:Categories:default', ['section' => 'id'])
			->setClass('btn btn-secondary btn-sm')
			->setIcon('sitemap')
			->setTitle('Categories');
		$grid->addAction('edit', 'Edit', 'detail', ['id' => 'id'])
			->setClass('btn btn-primary btn-sm')
			->setIcon(ICON_EDIT)
			->setTitle('Edit');
		$grid->addAction('delete', 'Delete', 'delete!', ['sectionId' => 'id'])
			->setClass(fn(array $row): string => 'btn btn-danger btn-sm ajax' . ($row['content'] > 0 ? ' disabled' : ''))
			->setIcon(ICON_DELETE)
			->setTitle('Delete')
			->addAttributes([
				'data-bs-toggle' => 'modal',
				'data-bs-target' => '#confirm-modal',
				'data-confirm-text' => $this->translator->translate('Delete?'),
			]);

		return $grid;
	}


	protected function createComponentSectionForm(): Form
	{
		$form = $this->sectionFactory->create($this->id, $this->actualLanguage);
		$form->setTranslator($this->translator);

		return $form;
	}


	#[Secured]
	#[Resource('Sections')]
	#[Privilege('edit')]
	public function handleActivate(int $sectionId, bool $status = false): void
	{
		$this->sectionsModel->update($sectionId, ['active' => $status]);
		$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
		$this->redirect('this');
	}


	/**
	 * Drag & drop v gridu sekcí
	 */
	#[Secured]
	#[Resource('Sections')]
	#[Privilege('edit')]
	public function handleSort(?int $item_id = null, ?int $prev_id = null, ?int $next_id = null): void
	{
		$ids = array_keys($this->sectionsModel->getList());
		if ($item_id === null || !in_array($item_id, $ids, true)) {
			$this->error('Unknown section.');
		}
		$ids = array_values(array_diff($ids, [$item_id]));
		$index = $prev_id !== null && in_array($prev_id, $ids, true)
			? (int) array_search($prev_id, $ids, true) + 1
			: ($next_id !== null && in_array($next_id, $ids, true) ? (int) array_search($next_id, $ids, true) : count($ids));
		array_splice($ids, $index, 0, [$item_id]);
		foreach ($ids as $position => $id) {
			$this->sectionsModel->update($id, ['position' => $position + 1]);
		}

		$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
		if ($this->isAjax()) {
			$this->redrawControl('flashes');
			$this->redrawControl('sectionsGrid');
		} else {
			$this->redirect('this');
		}
	}


	#[Secured]
	#[Resource('Sections')]
	#[Privilege('delete')]
	public function handleDelete(int $sectionId): void
	{
		try {
			$this->sectionsModel->delete($sectionId);
			$this->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);
		} catch (\LogicException) {
			$this->flashMessage('The section contains categories or articles - move or delete them first.', FLASH_FAILED);
		}
		$this->redirect('this', ['id' => null]);
	}


	#[Secured]
	#[Resource('Sections')]
	#[Privilege('edit')]
	public function handleValidateUrl(string $text): void
	{
		$this->payload->url = $this->urlManager->validateUrl($text, Sections::URL_TYPE, $this->id, $this->id);
		$this->sendPayload();
	}
}
