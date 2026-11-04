<?php
declare(strict_types=1);

namespace App\Presenters;

use App\Mcp\McpServer;
use App\Mcp\PsrHttpResponse;
use GuzzleHttp\Psr7\ServerRequest;
use Nette\Application\BadRequestException;
use Nette\Application\IPresenter;
use Nette\Application\Request;
use Nette\Application\Response;
use Nette\Http\IRequest;
use Tracy\Debugger;

/**
 * Endpoint MCP serveru: `/mcp` (Streamable HTTP, POST/GET/DELETE) - AI asistenti (Claude, MCP connector Claude API,
 * MCP Inspector, ...) přes něj volají nástroje služeb App\Mcp\McpToolProvider, viz App\Mcp\McpServer.
 *
 * Záměrně holý IPresenter jako CronPresenter - bez layoutu, jazyků, session a přihlášení. Nette požadavek jen převede
 * na PSR-7 pro SDK a jeho PSR-7 odpověď pošle zpět (PsrHttpResponse). Bez zaregistrovaných nástrojů 404.
 */
final class McpPresenter implements IPresenter
{

	public function __construct(
		private readonly McpServer $server,
		private readonly IRequest $httpRequest,
	) {
	}


	public function run(Request $request): Response
	{
		if (!$this->server->hasTools()) {
			throw new BadRequestException('MCP server has no tools.');
		}

		// odpovědi bez Content-Type (202 na notifikaci) by Tracy v debug režimu brala jako HTML a připojila k nim bar
		Debugger::$showBar = false;

		$psrRequest = new ServerRequest(
			$this->httpRequest->getMethod(),
			$this->httpRequest->getUrl()->getAbsoluteUrl(),
			$this->httpRequest->getHeaders(),
			$this->httpRequest->getRawBody() ?? '',
			'1.1',
			$_SERVER,
		);

		return new PsrHttpResponse($this->server->handle($psrRequest));
	}

}
