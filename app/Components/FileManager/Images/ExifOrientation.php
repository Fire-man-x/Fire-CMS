<?php
declare(strict_types=1);

namespace App\Components\FileManager\Images;

use Nette\StaticClass;
use Nette\Utils\Image;
use Nette\Utils\ImageColor;

/**
 * EXIF orientace fotek z mobilů a fotoaparátů.
 *
 * Fotoaparát uloží snímek vždy "na ležato" a otočení na výšku zapíše jen do EXIF tagu Orientation.
 * Prohlížeče tag respektují, ale GD (Nette\Utils\Image) ho při načtení ignoruje a při uložení EXIF
 * zahodí - zmenšený obrázek nebo náhled by pak zůstal otočený. Proto se obrázek při uploadu podle tagu
 * fyzicky narovná a v přenesených metadatech se orientace nastaví na 1 (viz FlysystemStorage::upload(),
 * JpegMetadata::forImage()).
 */
final class ExifOrientation
{
	use StaticClass;

	public const int Normal = 1;


	/**
	 * Hodnota EXIF tagu Orientation (1-8) JPEG obrázku, pro ostatní formáty a bez tagu 1.
	 */
	public static function read(string $contents): int
	{
		if (!function_exists('exif_read_data') || !str_starts_with($contents, "\xFF\xD8")) {
			return self::Normal;
		}

		$stream = fopen('php://memory', 'r+b');
		if ($stream === false) {
			return self::Normal;
		}

		fwrite($stream, $contents);
		rewind($stream);
		$exif = @exif_read_data($stream); // @ - poškozená EXIF data jen vyhodí warning, orientace pak zůstane výchozí
		fclose($stream);

		$orientation = is_array($exif) ? ($exif['Orientation'] ?? null) : null;

		return is_int($orientation) && $orientation >= 1 && $orientation <= 8 ? $orientation : self::Normal;
	}


	/**
	 * Narovná obrázek podle hodnoty EXIF tagu Orientation (mění předaný objekt).
	 */
	public static function apply(Image $image, int $orientation): void
	{
		// GD otáčí proti směru hodinových ručiček: 270 = o 90° po směru
		switch ($orientation) {
			case 2:
				$image->flip(IMG_FLIP_HORIZONTAL);
				break;
			case 3:
				self::rotate($image, 180);
				break;
			case 4:
				$image->flip(IMG_FLIP_VERTICAL);
				break;
			case 5:
				self::rotate($image, 270, IMG_FLIP_HORIZONTAL);
				break;
			case 6:
				self::rotate($image, 270);
				break;
			case 7:
				self::rotate($image, 90, IMG_FLIP_HORIZONTAL);
				break;
			case 8:
				self::rotate($image, 90);
				break;
		}
	}


	/**
	 * Otočí obrázek o úhel proti směru hodinových ručiček (násobky 90°), volitelně ho pak překlopí.
	 */
	public static function rotate(Image $image, int $angle, ?int $flip = null): void
	{
		$image->rotate($angle, ImageColor::rgb(0, 0, 0, 0));
		$image->saveAlpha(true); // imagerotate() vrací nový obrázek - bez tohohle by PNG přišlo o průhlednost
		if ($flip !== null) {
			$image->flip($flip);
		}
	}
}
