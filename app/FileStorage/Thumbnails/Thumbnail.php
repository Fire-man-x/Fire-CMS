<?php
declare(strict_types=1);

namespace App\FileStorage\Thumbnails;

use App\FileStorage\Exceptions\InvalidThumbnailException;
use App\FileStorage\Request\ImageRequest;
use Nette\Utils\Image;

/**
 * Náhled obrázku: rozměry, režim (zmenšení / ořez) a příznaky `Nette\Utils\Image::resize()`.
 *
 * Klíč náhledu (`getKey()`) je součástí URL generátoru náhledů i cesty náhledu v úložišti a zároveň je to
 * zápis, kterým se náhled povoluje v neonu (`fileStorage: thumbnails:`, viz AllowedThumbnails):
 * - `300x200`, `945x`, `x50` - zmenšení, chybějící rozměr se dopočítá z poměru stran
 * - `300x200-f1` - zmenšení s příznaky Image::resize() (zde Image::ShrinkOnly)
 * - `crop-130x130` - zmenšení a ořez ze středu na přesný rozměr (makro `{crop}`, vyžaduje oba rozměry)
 */
final class Thumbnail
{
	/** Největší povolený rozměr náhledu v px (ochrana proti překlepu v konfiguraci) */
	public const int MaxDimension = 10000;

	private const string KeyPattern = '~^(crop-)?(\d*)x(\d*)(?:-f(\d+))?$~';

	/** Všechny příznaky, které Image::resize() zná */
	private const int AllFlags = Image::ShrinkOnly | Image::Stretch | Image::OrBigger | Image::Cover;


	/**
	 * @throws InvalidThumbnailException
	 */
	public function __construct(
		public readonly ?int $width,
		public readonly ?int $height,
		public readonly bool $crop = false,
		public readonly int $flags = Image::OrSmaller,
	)
	{
		if ($width === null && $height === null) {
			throw new InvalidThumbnailException('Náhled musí mít zadanou šířku nebo výšku.');
		}

		foreach ([$width, $height] as $dimension) {
			if ($dimension !== null && ($dimension < 1 || $dimension > self::MaxDimension)) {
				throw new InvalidThumbnailException(sprintf('Rozměr náhledu musí být 1 až %d px, zadáno %d.', self::MaxDimension, $dimension));
			}
		}

		if ($crop && ($width === null || $height === null)) {
			throw new InvalidThumbnailException('Ořez (crop) vyžaduje šířku i výšku.');
		}

		if ($flags < 0 || ($flags & ~self::AllFlags) !== 0 || ($crop && $flags !== Image::OrSmaller)) {
			throw new InvalidThumbnailException(sprintf('Neplatné příznaky náhledu %d.', $flags));
		}

		if (($flags & (Image::Stretch | Image::Cover)) !== 0 && ($width === null || $height === null)) {
			throw new InvalidThumbnailException('Příznaky Image::Stretch a Image::Cover vyžadují šířku i výšku.');
		}
	}


	/**
	 * Náhled ze zápisu rozměrů v šabloně: `300x200`, `945x`, `x50` nebo `210` (= 210x210,
	 * stejně jako dřív FileStorage::processDimensions()).
	 *
	 * @throws InvalidThumbnailException
	 */
	public static function fromDimensions(string $dimensions, bool $crop = false, int $flags = Image::OrSmaller): self
	{
		$dimensions = trim($dimensions);
		if (ctype_digit($dimensions)) {
			return new self((int) $dimensions, (int) $dimensions, $crop, $flags);
		}

		$parts = explode('x', $dimensions);
		if (count($parts) !== 2 || !self::isDimension($parts[0]) || !self::isDimension($parts[1])) {
			throw new InvalidThumbnailException(sprintf("Neplatné rozměry náhledu '%s', očekává se např. '300x200', '945x' nebo 'x50'.", $dimensions));
		}

		return new self(
			$parts[0] === '' ? null : (int) $parts[0],
			$parts[1] === '' ? null : (int) $parts[1],
			$crop,
			$flags,
		);
	}


	/**
	 * Náhled z klíče (URL generátoru náhledů). Vrací null pro neplatný nebo nekanonický klíč
	 * (např. `0300x200`, `300x200-f0`), aby jeden náhled neměl víc adres.
	 */
	public static function fromKey(string $key): ?self
	{
		if (!preg_match(self::KeyPattern, $key, $matches)) {
			return null;
		}

		try {
			$thumbnail = new self(
				$matches[2] === '' ? null : (int) $matches[2],
				$matches[3] === '' ? null : (int) $matches[3],
				$matches[1] !== '',
				isset($matches[4]) ? (int) $matches[4] : Image::OrSmaller,
			);
		} catch (InvalidThumbnailException) {
			return null;
		}

		return $thumbnail->getKey() === $key ? $thumbnail : null;
	}


	/**
	 * @throws InvalidThumbnailException
	 */
	public static function fromRequest(ImageRequest $request): self
	{
		// makro {crop} posílá příznak OrSmaller, ořez má vlastní režim (Image::Cover)
		return $request->getCrop()
			? self::fromDimensions($request->getDimensions(), true)
			: self::fromDimensions($request->getDimensions(), false, $request->getFlags());
	}


	public function getKey(): string
	{
		return ($this->crop ? 'crop-' : '')
			. ($this->width ?? '') . 'x' . ($this->height ?? '')
			. ($this->flags !== Image::OrSmaller ? '-f' . $this->flags : '');
	}


	/**
	 * Zápis náhledu v seznamu `fileStorage: thumbnails: resize:` / `crop:` (klíč bez předpony `crop-`).
	 */
	public function getConfigEntry(): string
	{
		return $this->crop ? substr($this->getKey(), strlen('crop-')) : $this->getKey();
	}


	/**
	 * Upraví obrázek na tento náhled (mění předaný objekt).
	 */
	public function apply(Image $image): Image
	{
		if ($this->crop) {
			// stejné jako dřívější FileStorage::crop(): zmenšit tak, aby pokryl celý rozměr, a oříznout ze středu
			return $image->resize($this->width, $this->height, Image::Cover);
		}

		return $image->resize($this->width, $this->height, $this->flags & self::AllFlags);
	}


	private static function isDimension(string $value): bool
	{
		return $value === '' || ctype_digit($value);
	}
}
