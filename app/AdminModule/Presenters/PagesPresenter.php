<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use App\Attributes\Privilege;
use App\Attributes\Resource;
use App\Attributes\Secured;
use App\Forms\PageFormFactory;
use App\Model\Files;
use App\Model\Pages;
use App\Modules\UrlModule\UrlManager;
use Contributte\Datagrid\Datagrid;
use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Form;

/**
 * Stránky (App\Model\Pages) - seznam a detail s překlady po jazycích (parametr `language`)
 */
#[Secured]
#[Resource('Pages')]
#[Privilege('view')]
class PagesPresenter extends BasePresenter
{
	#[Persistent]
	public ?int $id = null;


	public function __construct(
		private readonly Pages $pagesModel,
		private readonly PageFormFactory $pageFactory,
		private readonly UrlManager $urlManager,
		private readonly Files $filesModel,
	) {
		parent::__construct();
	}


	protected function startup(): void
	{
		parent::startup();

		$this->addBreadCrumbLink('Pages', $this->link(':Admin:Pages:default', ['id' => null]));
	}


	#[Secured]
	#[Resource('Pages')]
	#[Privilege('edit')]
	public function actionDetail(): void
	{
		if ($this->id !== null) {
			if (!$this->pagesModel->getById($this->id)) {
				$this->error("Page '$this->id' doesn't exist.");
			}
			$translation = $this->pagesModel->getTranslation($this->id, $this->editLocale);
			$this->addBreadCrumbLink(
				$translation && is_string($translation->title) ? $translation->title : 'New translation',
				$this->link(':Admin:Pages:detail', ['id' => $this->id]),
				null,
				!$translation,
			);
		} else {
			$this->addBreadCrumbLink('New', $this->link(':Admin:Pages:detail', ['id' => null]));
		}
	}


	public function renderDetail(): void
	{
		$this->template->pageId = $this->id;
		$this->template->hasUrl = $this->id !== null
			&& $this->urlManager->existUrlByTypeAndKey(Pages::URL_TYPE, $this->id, $this->editLocale);

		$files = [];
		if ($this->id !== null) {
			foreach ($this->pagesModel->getFiles($this->id) as $file) {
				$files[] = $this->filesModel->toFileEntity($file);
			}
		}
		$this->template->files = $files;
	}


	protected function createComponentPagesGrid(string $name): Datagrid
	{
		// plochý seznam ve stromovém pořadí (podstránky odsazené); přetažení mění pořadí jen mezi sourozenci,
		// rodič se mění ve formuláři stránky (datagrid neumí řadit strom)
		$grid = new Datagrid($this, $name);
		$grid->setPrimaryKey('id');
		// ColumnStatus porovnává hodnotu striktně s volbami 0/1 - `active` proto jako int
		$grid->setDataSource(array_map(
			fn(array $page): array => ['active' => (int) $page['active']] + $page,
			$this->pagesModel->getTree($this->editLocale),
		));
		$grid->setTranslator($this->translator);
		$grid->setSortable();
		$grid->setPagination(false);

		$active = $grid->addColumnStatus('active', 'A.');
		$active->addOption(0, 'Unactive')
			->setClass('btn-danger')
			->setIcon('ban')
			->setTitle('Set as active');
		$active->addOption(1, 'Active')
			->setClass('btn-success')
			->setIcon('check-circle')
			->setTitle('Set as unactive');
		$active->onChange[] = function ($id, $value): void {
			$this->handleActivate((int) $id, (bool) $value);
		};

		$grid->addColumnText('title', 'Title')
			->setRenderer(fn(array $row): string => str_repeat('— ', $row['level']) . ($row['title'] ?? '#' . $row['id']));

		$grid->addColumnText('status', 'Status')
			->setRenderer(fn(array $row): string => (string) $this->translator->translate(Pages::Statuses[$row['status']] ?? $row['status']));

		$grid->addAction('edit', 'Edit', 'detail', ['id' => 'id'])
			->setClass('btn btn-primary btn-sm')
			->setIcon(ICON_EDIT)
			->setTitle('Edit');

		$grid->addAction('delete', 'Delete', 'delete!', ['pageId' => 'id'])
			->setClass('btn btn-danger btn-sm ajax')
			->setIcon(ICON_DELETE)
			->setTitle('Delete')
			->addAttributes([
				'data-bs-toggle' => 'modal',
				'data-bs-target' => '#confirm-modal',
				'data-confirm-text' => $this->translator->translate('Delete the page including its URL and menu items?'),
			]);

		return $grid;
	}


	protected function createComponentPageForm(): Form
	{
		$form = $this->pageFactory->create($this->id, $this->editLocale);
		$form->setTranslator($this->translator);

		return $form;
	}


	#[Secured]
	#[Resource('Pages')]
	#[Privilege('edit')]
	public function handleActivate(int $pageId, bool $status = false): void
	{
		$this->pagesModel->update($pageId, ['active' => $status]);
		$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
		$this->redirect('this');
	}


	/**
	 * Drag & drop v gridu stránek - jen mezi sourozenci, viz Pages::movePage()
	 */
	#[Secured]
	#[Resource('Pages')]
	#[Privilege('edit')]
	public function handleSort(?int $item_id = null, ?int $prev_id = null, ?int $next_id = null): void
	{
		if ($item_id === null) {
			$this->error('Missing item_id.');
		}
		$this->pagesModel->movePage($item_id, $prev_id, $next_id);
		$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);

		if ($this->isAjax()) {
			$this->redrawControl('flashes');
			$this->redrawControl('pagesGrid');
		} else {
			$this->redirect('this');
		}
	}


	/**
	 * Obrázky vybrané ve správci souborů (modal, administration/js/main.js - selectedFilesFromIframeUrl)
	 * @param array<int|string> $files
	 */
	#[Secured]
	#[Resource('Pages')]
	#[Privilege('edit')]
	public function handleAddImages(array $files): void
	{
		$this->pagesModel->addFiles($this->getPageIdOrFail(), array_values(array_map('intval', $files)));
		$this->redrawControl('files');
	}


	#[Secured]
	#[Resource('Pages')]
	#[Privilege('edit')]
	public function handleRemoveImage(int $fileId): void
	{
		$this->pagesModel->removeFile($this->getPageIdOrFail(), $fileId);
		if ($this->isAjax()) {
			$this->redrawControl('files');
		} else {
			$this->redirect('this');
		}
	}


	/**
	 * Nové pořadí obrázků po přetažení (první = hlavní obrázek)
	 * @param array<int|string> $items
	 */
	#[Secured]
	#[Resource('Pages')]
	#[Privilege('edit')]
	public function handleSortImages(array $items): void
	{
		$this->pagesModel->sortFiles($this->getPageIdOrFail(), array_values(array_map('intval', $items)));
		$this->redrawControl('files');
	}


	#[Secured]
	#[Resource('Pages')]
	#[Privilege('delete')]
	public function handleDelete(int $pageId): void
	{
		$this->pagesModel->delete($pageId);
		$this->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);
		$this->redirect('this', ['id' => null]);
	}


	/**
	 * Obrázky jde přiřadit jen uložené stránce
	 */
	private function getPageIdOrFail(): int
	{
		if ($this->id === null || !$this->pagesModel->getById($this->id)) {
			$this->error('Save the page first.');
		}

		return $this->id;
	}


	/**
	 * Návrh URL z názvu (administration/js/main.js, třída validate-url)
	 */
	#[Secured]
	#[Resource('Pages')]
	#[Privilege('edit')]
	public function handleValidateUrl(string $text): void
	{
		$this->payload->url = $this->urlManager->validateUrl($text, Pages::URL_TYPE, $this->id, $this->id);
		$this->sendPayload();
	}
}
