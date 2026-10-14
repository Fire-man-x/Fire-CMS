<?php declare(strict_types = 1);

namespace Tests;

use AlesWita\VisualPaginator\VisualPaginator;
use App\TestPresenter;
use Nette\Application\IPresenterFactory;
use Nette\Application\Request;
use Nette\Application\Responses\TextResponse;
use Nette\Application\UI\Template;
use Nette\DI\Container;
use Nette\Forms\Controls\SelectBox;
use Nette\Http\IRequest;
use Nette\Http\Session;
use Nette\InvalidArgumentException;
use Tester\Assert;
use Tester\DomQuery;
use Tester\TestCase;

$container = require __DIR__ . '/../bootstrap.php';

final class PresenterTest extends TestCase
{

	private IPresenterFactory $presenterFactory;

	public function __construct(
		private readonly Container $container,
	)
	{
		$this->presenterFactory = $this->container->getByType(IPresenterFactory::class);
	}

	public function test01(): void
	{
		$presenter = $this->createPresenter();

		$response = $presenter->run(new Request('Test', IRequest::Get));
		Assert::type(TextResponse::class, $response);

		$source = $response->getSource();
		Assert::type(Template::class, $source);

		$dom = DomQuery::fromHtml($source->renderToString());

		$tag = $dom->find('span');
		Assert::count(2, $tag);
		Assert::same('«', (string) $tag[0]);
		Assert::same('…', (string) $tag[1]);

		$tag = $dom->find('a');
		Assert::count(5, $tag);

		Assert::same('1', (string) $tag[0]);
		Assert::same('/?paginator-page=1&do=paginator-paginate', (string) $tag[0]['href']);

		Assert::same('2', (string) $tag[1]);
		Assert::same('/?paginator-page=2&do=paginator-paginate', (string) $tag[1]['href']);

		Assert::same('3', (string) $tag[2]);
		Assert::same('/?paginator-page=3&do=paginator-paginate', (string) $tag[2]['href']);

		Assert::same('100', (string) $tag[3]);
		Assert::same('/?paginator-page=100&do=paginator-paginate', (string) $tag[3]['href']);

		Assert::same('»', (string) $tag[4]);
		Assert::same('/?paginator-page=2&do=paginator-paginate', (string) $tag[4]['href']);
	}

	public function test02(): void
	{
		$paginator = $this->createPaginator();

		$paginator->setItemCount(1000);

		Assert::exception(static function () use ($paginator): void {
			$paginator->setItemsPerPage(10);
		}, InvalidArgumentException::class, 'AlesWita\VisualPaginator\VisualPaginator::setItemsPerPage(): can not set items per page. You can enabled it by set $canSetItemsPerPage to true.');

		$paginator->canSetItemsPerPage = true;

		Assert::exception(static function () use ($paginator): void {
			$paginator->setItemsPerPage(15);
		}, InvalidArgumentException::class, 'AlesWita\VisualPaginator\VisualPaginator::setItemsPerPage(): $itemsPerPageList has not key "15".');

		$paginator->setItemsPerPage(20);
		$paginator->loadState([]);

		Assert::same(20, $paginator->getItemsPerPage());
	}

	/**
	 * setItemsPerPage() po loadState() (odeslání formuláře přes AJAX) se musí projevit hned, ne až po přesměrování
	 */
	public function test03(): void
	{
		$paginator = $this->createPaginator();
		$paginator->canSetItemsPerPage = true;
		$paginator->setItemCount(1000);
		$paginator->loadState(['page' => 3]);

		$paginator->setItemsPerPage(50);

		Assert::same(50, $paginator->getItemsPerPage());
		Assert::same(100, $paginator->getOffset());
	}

	/**
	 * Hodnota v session se při vypnutém výběru počtu položek ignoruje a odstraní, nesmí vyhodit výjimku
	 */
	public function test04(): void
	{
		// presenter neprošel run(), nemá jméno - paginator použije úložiště "default"
		$section = $this->container->getByType(Session::class)->getSection(VisualPaginator::SESSION_SECTION);
		$section->set('default', 30);

		$paginator = $this->createPaginator();
		$paginator->loadState([]);

		Assert::same(10, $paginator->getItemsPerPage());
		Assert::null($section->get('default'));
	}

	/**
	 * onSuccess formuláře počítá s tím, že SelectBox vrací číselný klíč nabídky jako int (z HTTP přijde řetězec)
	 */
	public function test05(): void
	{
		$paginator = $this->createPaginator();
		$paginator->canSetItemsPerPage = true;

		$select = $paginator->getComponent('itemsPerPage-itemsPerPage');
		Assert::type(SelectBox::class, $select);

		$select->setValue('50');
		Assert::same(50, $select->getValue());
	}

	/**
	 * setDefaultItemsPerPage(): pevný počet položek bez výběru a bez session; volba uložená v session má přednost
	 */
	public function test06(): void
	{
		$section = $this->container->getByType(Session::class)->getSection(VisualPaginator::SESSION_SECTION);
		$section->remove('default');

		$paginator = $this->createPaginator();
		$paginator->setItemCount(1000);
		$paginator->setDefaultItemsPerPage(25);
		$paginator->loadState(['page' => 2]);

		Assert::same(25, $paginator->getItemsPerPage());
		Assert::same(25, $paginator->getOffset());
		Assert::null($section->get('default'));

		$section->set('default', 50);
		$paginator->canSetItemsPerPage = true;
		$paginator->loadState([]);

		Assert::same(50, $paginator->getItemsPerPage());

		Assert::exception(static function () use ($paginator): void {
			$paginator->setDefaultItemsPerPage(0);
		}, InvalidArgumentException::class, 'AlesWita\VisualPaginator\VisualPaginator::setDefaultItemsPerPage(): items per page must be positive, 0 given.');
	}

	private function createPresenter(): TestPresenter
	{
		$presenter = $this->presenterFactory->createPresenter('Test');
		Assert::type(TestPresenter::class, $presenter);

		return $presenter;
	}

	private function createPaginator(): VisualPaginator
	{
		$paginator = $this->createPresenter()['paginator'];
		Assert::type(VisualPaginator::class, $paginator);

		return $paginator;
	}

}

(new PresenterTest($container))->run();
