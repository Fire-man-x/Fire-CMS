<?php
declare(strict_types=1);

namespace App\Mcp;

use Nette\Application\Response;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Psr\Http\Message\ResponseInterface;

/**
 * Odešle PSR-7 odpověď (z MCP SDK) přes Nette - kód, hlavičky a tělo.
 *
 * Tělo se čte po částech: SSE odpověď SDK (Mcp\Server\Transport\CallbackStream) při read() sama vypisuje
 * události a vrací prázdný řetězec, obyčejná JSON odpověď se vypíše celá.
 */
final class PsrHttpResponse implements Response
{

	public function __construct(
		private readonly ResponseInterface $response,
	) {
	}


	public function send(IRequest $httpRequest, IResponse $httpResponse): void
	{
		$httpResponse->setCode($this->response->getStatusCode());
		foreach ($this->response->getHeaders() as $name => $values) {
			// první hodnota přepíše případnou hlavičku nastavenou Nette dřív, další se přidají
			foreach (array_values($values) as $i => $value) {
				$i === 0 ? $httpResponse->setHeader((string) $name, $value) : $httpResponse->addHeader((string) $name, $value);
			}
		}

		$body = $this->response->getBody();
		if ($body->isSeekable()) {
			$body->rewind();
		}

		// prázdná část = konec (i u CallbackStream, který vypisuje sám a vrací '')
		do {
			$chunk = $body->read(8192);
			echo $chunk;
		} while ($chunk !== '' && !$body->eof());
	}

}
