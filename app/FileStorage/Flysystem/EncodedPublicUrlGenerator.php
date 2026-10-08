<?php
declare(strict_types=1);

namespace App\FileStorage\Flysystem;

use League\Flysystem\Config;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;

/**
 * Veřejná URL souboru = `publicUrl` úložiště + klíč souboru s kódovanými částmi cesty.
 *
 * Flysystem (PrefixPublicUrlGenerator) klíč nekóduje - u hashových názvů správce souborů to nevadí, názvy
 * souborů a alb ale můžou obsahovat mezery, diakritiku, `#` nebo `?`. Lomítka mezi částmi cesty zůstávají.
 */
final class EncodedPublicUrlGenerator implements PublicUrlGenerator
{
	private PathPrefixer $prefixer;


	public function __construct(string $urlPrefix)
	{
		$this->prefixer = new PathPrefixer($urlPrefix, '/');
	}


	public function publicUrl(string $path, Config $config): string
	{
		return $this->prefixer->prefixPath(implode('/', array_map(rawurlencode(...), explode('/', $path))));
	}
}
