<?php declare(strict_types = 1);

namespace AlesWita\VisualPaginator;

use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Nette\Http\Session;
use Nette\Http\SessionSection;
use Nette\InvalidArgumentException;
use Nette\Utils\Arrays;
use Nette\Utils\Paginator;
use Nette\Utils\Strings;

/**
 * @property-read VisualPaginatorTemplate $template
 */
final class VisualPaginator extends Control
{

	public const string SESSION_SECTION = 'visual-paginator';

	#[Persistent]
	public ?int $page = null;

	public bool $ajax = false;

	public bool $canSetItemsPerPage = false;

	public ?string $itemsPerPageRepository = null;

	/** @var array<int, string> */
	public array $itemsPerPageList = [
		10 => '10',
		20 => '20',
		30 => '30',
		40 => '40',
		50 => '50',
		100 => '100',
	];

	/** @var array<string, string> */
	public array $messages = [
		'send' => 'Send',
		'itemsPerPage' => 'Items per page',
	];

	public string $templateFile = __DIR__ . '/templates/normal.latte';

	/** @var array<callable(): void> */
	public array $onPaginate = [];

	private int $itemsPerPage = 10;

	private readonly Paginator $paginator;

	private readonly SessionSection $sessionSection;

	public function __construct(Session $session)
	{
		$this->paginator = new Paginator();
		$this->sessionSection = $session->getSection(self::SESSION_SECTION);
	}

	/**
	 * Výchozí počet položek na stránku - bez výběru uživatelem a bez zápisu do session. Volá se při vytvoření
	 * komponenty, hodnota uložená v session (při $canSetItemsPerPage) má v loadState() přednost.
	 */
	public function setDefaultItemsPerPage(int $itemsPerPage): self
	{
		if ($itemsPerPage < 1) {
			throw new InvalidArgumentException(self::class . '::setDefaultItemsPerPage(): items per page must be positive, ' . $itemsPerPage . ' given.');
		}

		$this->itemsPerPage = $itemsPerPage;
		$this->paginator->setItemsPerPage($itemsPerPage);
		return $this;
	}

	public function setItemsPerPage(int $itemsPerPage): self
	{
		if (!$this->canSetItemsPerPage) {
			throw new InvalidArgumentException(self::class . '::setItemsPerPage(): can not set items per page. You can enabled it by set $canSetItemsPerPage to true.');
		}

		if (!array_key_exists($itemsPerPage, $this->itemsPerPageList)) {
			throw new InvalidArgumentException(self::class . '::setItemsPerPage(): $itemsPerPageList has not key "' . $itemsPerPage . '".');
		}

		$this->itemsPerPage = $itemsPerPage;
		// i po loadState() (odeslání formuláře přes AJAX) musí stránkovat s novou hodnotou
		$this->paginator->setItemsPerPage($itemsPerPage);
		$this->sessionSection->set($this->getSessionRepository(), $itemsPerPage);
		return $this;
	}

	public function setItemCount(int $count): self
	{
		$this->paginator->setItemCount($count);
		return $this;
	}

	public function getOffset(): int
	{
		return $this->paginator->getOffset();
	}

	public function getItemsPerPage(): int
	{
		return $this->paginator->getItemsPerPage();
	}

	public function getPaginator(): Paginator
	{
		return $this->paginator;
	}

	/**
	 * @param array<string, mixed> $params
	 */
	#[\Override]
	public function loadState(array $params): void
	{
		parent::loadState($params);

		// počet položek uložený v session platí jen při povoleném výběru a jen pokud je v nabídce,
		// jinak se ze session odstraní (dřív setItemsPerPage() v tom případě vyhodilo výjimku)
		$repository = $this->getSessionRepository();
		$stored = $this->sessionSection->get($repository);
		if ($stored !== null) {
			if ($this->canSetItemsPerPage && is_int($stored) && array_key_exists($stored, $this->itemsPerPageList)) {
				$this->itemsPerPage = $stored;
			} else {
				$this->sessionSection->remove($repository);
			}
		}

		$this->paginator->setPage($this->page ?? 1);
		$this->paginator->setItemsPerPage($this->itemsPerPage);
		$this->page = $this->paginator->getPage();
	}

	public function render(): void
	{
		$template = $this->template;
		$template->steps = Helpers::createSteps($this->paginator);
		$template->itemsPerPage = $this->canSetItemsPerPage;
		$template->paginator = $this->paginator;
		$template->ajax = $this->ajax;

		$template->setFile($this->templateFile);
		$template->render();
	}

	public function handlePaginate(): void
	{
		Arrays::invoke($this->onPaginate);

		if (!$this->getPresenter()->isAjax()) {
			$this->redirect('this');
		}
	}

	protected function createComponentItemsPerPage(): Form
	{
		$form = new Form();

		// výchozí počet (setDefaultItemsPerPage()) nemusí být v nabídce - pak select nic nepředvybere
		$select = $form->addSelect('itemsPerPage', $this->messages['itemsPerPage'], $this->itemsPerPageList)
			->setRequired()
			->setDefaultValue(array_key_exists($this->itemsPerPage, $this->itemsPerPageList) ? $this->itemsPerPage : null);

		$form->addSubmit('send', $this->messages['send']);

		$form->onSuccess[] = function () use ($select): void {
			// SelectBox vrací klíč z $itemsPerPageList, číselný klíč jako int
			$itemsPerPage = $select->getValue();
			if (is_int($itemsPerPage)) {
				$this->setItemsPerPage($itemsPerPage);
			}

			Arrays::invoke($this->onPaginate);

			if (!$this->getPresenter()->isAjax()) {
				$this->redirect('this');
			}
		};

		return $form;
	}

	private function getSessionRepository(): string
	{
		$repository = $this->itemsPerPageRepository
			?? $this->getPresenterIfExists()?->getName()
			?? 'default';

		return Strings::lower($repository);
	}

}
