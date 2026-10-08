<?php
declare(strict_types=1);

namespace App\FileStorage\Thumbnails;

use App\FileStorage\Exceptions\InvalidThumbnailException;
use App\FileStorage\Request\ImageRequest;

/**
 * Seznam povolených náhledů jednoho úložiště (`fileStorage: <název>: thumbnails:` v neonu).
 *
 * Náhledy se generují až na vyžádání, takže bez seznamu by si kdokoliv mohl změnou URL nechat vygenerovat
 * libovolný rozměr. Jádro povoluje náhledy svých šablon v `app/config/config.neon`, projekt a pluginy
 * přidávají svoje ve vlastním neonu (seznamy se při načítání konfigurace slučují).
 */
final class AllowedThumbnails
{
	/** @var array<string, Thumbnail> klíč náhledu => náhled */
	private array $thumbnails = [];

	/** @var array<string, string> pojmenované rozměry => rozměry */
	private array $aliases = [];


	/**
	 * @param list<string|int> $resize rozměry pro {image}/n:src, např. `300x200`, `945x`, `300x200-f1` (s příznaky)
	 * @param list<string|int> $crop rozměry pro {crop}/n:crop, např. `130x130`
	 * @param array<string, string|int> $aliases pojmenované rozměry ze šablon => rozměry, např. `smallest: x192`;
	 *   výsledný náhled musí být povolený v $resize / $crop
	 * @throws InvalidThumbnailException
	 */
	public function __construct(array $resize = [], array $crop = [], array $aliases = [])
	{
		foreach ($resize as $entry) {
			$this->add(self::parse((string) $entry, false));
		}

		foreach ($crop as $entry) {
			$this->add(self::parse((string) $entry, true));
		}

		foreach ($aliases as $alias => $dimensions) {
			try {
				Thumbnail::fromDimensions((string) $dimensions);
			} catch (InvalidThumbnailException $e) {
				throw new InvalidThumbnailException(sprintf('thumbnails.aliases.%s: %s', $alias, $e->getMessage()), 0, $e);
			}

			$this->aliases[$alias] = (string) $dimensions;
		}
	}


	/**
	 * Náhled požadovaný šablonou, pojmenované rozměry převede na rozměry. Nekontroluje, jestli je povolený.
	 *
	 * @throws InvalidThumbnailException neplatné rozměry
	 */
	public function fromRequest(ImageRequest $request): Thumbnail
	{
		return Thumbnail::fromRequest($request, $this->aliases[$request->getDimensions()] ?? null);
	}


	public function isAllowed(Thumbnail $thumbnail): bool
	{
		return isset($this->thumbnails[$thumbnail->getKey()]);
	}


	/**
	 * Povolený náhled podle klíče z URL, null pokud neexistuje nebo není povolený.
	 */
	public function get(string $key): ?Thumbnail
	{
		return $this->thumbnails[$key] ?? null;
	}


	/**
	 * @return list<Thumbnail>
	 */
	public function getAll(): array
	{
		return array_values($this->thumbnails);
	}


	private function add(Thumbnail $thumbnail): void
	{
		$this->thumbnails[$thumbnail->getKey()] = $thumbnail;
	}


	/**
	 * @throws InvalidThumbnailException
	 */
	private static function parse(string $entry, bool $crop): Thumbnail
	{
		$flags = 0;
		if (!$crop && preg_match('~^(.+)-f(\d+)$~', $entry, $matches)) {
			$entry = $matches[1];
			$flags = (int) $matches[2];
		}

		try {
			return Thumbnail::fromDimensions($entry, $crop, $flags);
		} catch (InvalidThumbnailException $e) {
			throw new InvalidThumbnailException(sprintf('thumbnails.%s: %s', $crop ? 'crop' : 'resize', $e->getMessage()), 0, $e);
		}
	}
}
