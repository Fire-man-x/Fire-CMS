<?php

declare(strict_types=1);

namespace Tests\LiveTranslator;

use LiveTranslator\Panel\Panel;
use LiveTranslator\Storage\File;
use LiveTranslator\Translator;
use Nette\Application\Application;
use Nette\Application\IPresenter;
use Nette\Application\IPresenterFactory;
use Nette\Http\IRequest;
use Nette\Http\Response;
use Nette\Http\Session;
use Nette\Http\UrlScript;
use Nette\Routing\Router;
use Tester\Assert;
use Tester\TestCase;
use Tests\Helpers\RequestFactory;

require_once __DIR__ . '/../bootstrap.php';

final class NullRouter implements Router
{
	public function match(IRequest $httpRequest): ?array
	{
		return null;
	}

	public function constructUrl(array $params, UrlScript $refUrl): ?string
	{
		return null;
	}
}

final class NullPresenterFactory implements IPresenterFactory
{
	public function getPresenterClass(string &$name): string
	{
		throw new \LogicException('Not implemented in test double.');
	}

	public function createPresenter(string $name): IPresenter
	{
		throw new \LogicException('Not implemented in test double.');
	}
}

/**
 * Regrese na "TypeError: LiveTranslator\Translator::getPresenterLanguageParam(): Return value
 * must be of type string, array returned" nahlášenou po přesunu libs/LiveTranslator (viz
 * docs/Changelog za dnešní datum). Root cause byl `private $presenterLanguageParam = array();`
 * s návratovým typem `: string` — `setPresenterLanguageParam()` se v `config.neon` nikdy
 * nevolá (je zakomentovaná), takže hodnota zůstávala navždy na svém rozbitém defaultu.
 * Panel je navíc zaregistrovaný v Tracy baru (`tracy: bar: [...]`), takže se `getPanel()`
 * volá na každém requestu v debug módu — proto to spadlo plošně, ne jen při reálném použití
 * vícejazyčného přepínání.
 */
final class TranslatorPhp83RegressionTest extends TestCase
{
	private Translator $translator;

	protected function setUp(): void
	{
		parent::setUp();

		$storageDir = TEMP_DIR . '/livetranslator-' . uniqid();
		mkdir($storageDir, 0777, true);

		$httpRequest = RequestFactory::fromUrl('http://example.com/');
		$application = new Application(new NullPresenterFactory(), new NullRouter(), $httpRequest, new Response());
		$session = new Session($httpRequest, new Response());

		$this->translator = new Translator('en', new File($storageDir), $session, $application);
	}

	public function testGetPresenterLanguageParamIsNullByDefaultInsteadOfCrashing(): void
	{
		Assert::null($this->translator->getPresenterLanguageParam());
	}

	public function testGetPresenterLinkIsNullWhenParamNotConfigured(): void
	{
		Assert::null($this->translator->getPresenterLink('cs'));
	}

	public function testPanelRendersWithoutCrashing(): void
	{
		// Přesně tohle Tracy volá na každém requestu (tracy: bar: [LiveTranslator\Panel\Panel]).
		$panel = new Panel($this->translator, RequestFactory::fromUrl('http://example.com/'));

		Assert::type('string', $panel->getTab());
		Assert::type('string', $panel->getPanel());
	}
}

(new TranslatorPhp83RegressionTest())->run();
