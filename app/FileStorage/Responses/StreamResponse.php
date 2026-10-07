<?php
declare(strict_types=1);

namespace App\FileStorage\Responses;

use Nette\Application\Response;
use Nette\Http\IRequest;
use Nette\Http\IResponse;

/**
 * Odešle obsah souboru z úložiště (řetězec nebo stream z Flysystemu) - náhrada FileResponse, která umí
 * jen soubor na lokálním disku. Bez podpory HTTP Range (stahování po částech).
 */
final class StreamResponse implements Response
{
	/**
	 * @param string|resource $body obsah souboru nebo stream (po odeslání se zavře)
	 * @param string|null $downloadName název souboru ke stažení (Content-Disposition: attachment), null = zobrazit
	 * @param string|null $expiration jak dlouho smí prohlížeč odpověď cachovat (např. '1 day'), null = beze změny
	 */
	public function __construct(
		private readonly mixed $body,
		private readonly string $contentType,
		private readonly ?string $downloadName = null,
		private readonly ?int $contentLength = null,
		private readonly ?string $expiration = null,
	)
	{
		if (!is_string($body) && !is_resource($body)) {
			throw new \InvalidArgumentException('Obsah odpovědi musí být řetězec nebo stream.');
		}
	}


	public function send(IRequest $httpRequest, IResponse $httpResponse): void
	{
		$httpResponse->setContentType($this->contentType);

		if ($this->downloadName !== null) {
			$httpResponse->setHeader(
				'Content-Disposition',
				'attachment; filename="' . str_replace(['"', "\r", "\n"], '', $this->downloadName) . '"'
				. "; filename*=utf-8''" . rawurlencode($this->downloadName),
			);
		}

		$length = is_string($this->body) ? strlen($this->body) : $this->contentLength;
		if ($length !== null) {
			$httpResponse->setHeader('Content-Length', (string) $length);
		}

		if ($this->expiration !== null) {
			$httpResponse->setExpiration($this->expiration);
		}

		if (is_string($this->body)) {
			if ($httpRequest->getMethod() !== 'HEAD') {
				echo $this->body;
			}

			return;
		}

		if ($httpRequest->getMethod() !== 'HEAD') {
			fpassthru($this->body);
		}

		fclose($this->body);
	}
}
