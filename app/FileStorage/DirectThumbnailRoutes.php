<?php
declare(strict_types=1);

namespace App\FileStorage;

/**
 * Cesty úložišť s `directThumbnails` pro FileRouter: požadavek na chybějící soubor pod `publicUrl` takového
 * úložiště vede na Front:Files:missingThumbnail. Sestavuje je DI rozšíření z konfigurace - router nemůže
 * záviset na samotných úložištích, ta přes LinkGenerator závisí na routeru.
 */
final class DirectThumbnailRoutes
{
	/**
	 * @param array<string, string> $prefixes název úložiště => cesta `publicUrl` bez krajních lomítek, např. `foto/public`
	 */
	public function __construct(
		private readonly array $prefixes = [],
	)
	{
	}


	/**
	 * @return array<string, string>
	 */
	public function getPrefixes(): array
	{
		return $this->prefixes;
	}
}
