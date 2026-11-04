<?php
declare(strict_types=1);

namespace App\Mcp;

use GuzzleHttp\Psr7\HttpFactory;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * MCP server webu (oficiální SDK `mcp/sdk`) s nástroji všech služeb McpToolProvider - obslouží jeden HTTP
 * požadavek na endpoint `/mcp` (App\Presenters\McpPresenter).
 *
 * Nástroje se registrují ručně přes Builder::addTool() s instancí služby z DI, ne přes discovery SDK
 * (`setDiscovery()` prochází soubory a třídy vytváří samo, bez závislostí z kontejneru).
 *
 * Klienti handshake revizí (`initialize`, do 2025-11-25) potřebují MCP session mezi požadavky - každý HTTP
 * požadavek obslouží jiný PHP proces, session jsou proto v souborech v `$sessionDir`. Klienti bezstavové revize
 * 2026-07-28 session nepotřebují, oba režimy obslouží tentýž endpoint (StreamableHttpTransport požadavek rozliší sám).
 *
 * Endpoint je veřejný a bez přihlášení - nástroje proto smí vracet jen veřejná data. CORS je povolený pro libovolný
 * web (bez cookies), aby šlo server zkoušet i z prohlížeče (MCP Inspector). Ochrana proti DNS rebindingu z výchozích
 * middlewarů SDK je vypnutá: chrání servery běžící na localhostu, veřejný web má Host hlídaný webserverem.
 */
final class McpServer
{

	/**
	 * @param McpToolProvider[] $toolProviders
	 */
	public function __construct(
		private readonly array $toolProviders,
		private readonly string $serverName,
		private readonly string $serverVersion,
		private readonly string $sessionDir,
		private readonly LoggerInterface $logger,
	) {
	}


	/**
	 * Bez nástrojů endpoint `/mcp` neodpovídá (404) - projekty bez pluginu s nástroji o MCP serveru nevědí
	 */
	public function hasTools(): bool
	{
		return $this->toolProviders !== [];
	}


	public function handle(ServerRequestInterface $request): ResponseInterface
	{
		$factory = new HttpFactory();
		$transport = new StreamableHttpTransport(
			$request,
			$factory,
			$factory,
			$this->logger,
			[new CorsMiddleware(allowedOrigins: ['*'])],
		);

		return $this->createServer()->run($transport);
	}


	private function createServer(): Server
	{
		$builder = Server::builder()
			->setServerInfo($this->serverName, $this->serverVersion)
			->setInstructions($this->getInstructions())
			->setSession(new FileSessionStore($this->sessionDir))
			->setLogger($this->logger);

		foreach ($this->toolProviders as $provider) {
			foreach ((new \ReflectionObject($provider))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
				foreach ($method->getAttributes(McpTool::class) as $attribute) {
					$tool = $attribute->newInstance();
					$builder->addTool(
						[$provider, $method->getName()],
						name: $tool->name,
						title: $tool->title,
						description: $tool->description,
						annotations: $tool->annotations,
						icons: $tool->icons,
						meta: $tool->meta,
						outputSchema: $tool->outputSchema,
					);
				}
			}
		}

		return $builder->build();
	}


	private function getInstructions(): ?string
	{
		$instructions = [];
		foreach ($this->toolProviders as $provider) {
			$text = $provider->getInstructions();
			if ($text !== null && trim($text) !== '') {
				$instructions[] = trim($text);
			}
		}

		return $instructions !== [] ? implode("\n\n", $instructions) : null;
	}

}
