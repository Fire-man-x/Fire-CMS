<?php

declare(strict_types=1);

namespace Tests\Helpers;

use Nette\Http\FileUpload;
use Nette\Utils\Image;
use Nette\Utils\ImageColor;
use Nette\Utils\ImageType;

/**
 * Testovací obrázky pro správce souborů: JPEG s EXIF orientací (jako z mobilu) a nahrané soubory
 * (Nette\Http\FileUpload) nad dočasným souborem.
 */
final class TestImages
{
	/**
	 * Obrázek na ležato, levá polovina červená, pravá modrá - po narovnání jde poznat, kam se otočil.
	 *
	 * @param int<1, max> $width
	 * @param int<1, max> $height
	 */
	public static function halves(int $width = 40, int $height = 20, int $type = ImageType::JPEG): string
	{
		$image = Image::fromBlank($width, $height, ImageColor::rgb(0, 0, 255));
		$image->filledRectangleWH(0, 0, intdiv($width, 2), $height, ImageColor::rgb(255, 0, 0));

		return $image->toString($type === ImageType::PNG ? ImageType::PNG : ImageType::JPEG, $type === ImageType::PNG ? null : 95);
	}


	/** GPS šířka ve fixture EXIF (50° 4' 30") jako bajty RATIONAL - po odstranění GPS nesmí v souboru zůstat */
	public const array GpsLatitude = [50, 1, 4, 1, 30, 1];


	/**
	 * Vloží do JPEG segment APP1 s EXIF tagem Orientation (minimální TIFF struktura, little-endian).
	 */
	public static function withExifOrientation(string $jpeg, int $orientation): string
	{
		return self::withSegments($jpeg, [[0xE1, self::exif($orientation, gps: false, thumbnail: false, pixelDimensions: false)]]);
	}


	/**
	 * Vloží do JPEG hned za začátek souboru segmenty [marker, obsah].
	 *
	 * @param list<array{int, string}> $segments
	 */
	public static function withSegments(string $jpeg, array $segments): string
	{
		$raw = '';
		foreach ($segments as [$marker, $payload]) {
			$raw .= "\xFF" . chr($marker) . pack('n', strlen($payload) + 2) . $payload;
		}

		return substr($jpeg, 0, 2) . $raw . substr($jpeg, 2);
	}


	/**
	 * Obsah segmentu APP1 s EXIF jako z mobilu: IFD0 s orientací, Exif IFD s rozměry (PixelXDimension jako
	 * SHORT, PixelYDimension jako LONG), volitelně GPS IFD se šířkou (hodnoty mimo položku) a IFD1 s náhledem.
	 */
	public static function exif(int $orientation, bool $gps = true, bool $thumbnail = true, bool $pixelDimensions = true, bool $littleEndian = true): string
	{
		$short = $littleEndian ? 'v' : 'n';
		$long = $littleEndian ? 'V' : 'N';
		$ifd = static function (array $entries, int $next) use ($short, $long): string {
			$out = pack($short, count($entries));
			foreach ($entries as [$tag, $type, $count, $value]) {
				$out .= pack($short . $short . $long, $tag, $type, $count) . $value;
			}

			return $out . pack($long, $next);
		};
		$ifdSize = static fn(int $count): int => 2 + 12 * $count + 4;
		$inlineShort = static fn(int $value): string => pack($short, $value) . "\x00\x00";

		$ifd0Count = 1 + ($pixelDimensions ? 1 : 0) + ($gps ? 1 : 0);
		$offset = 8 + $ifdSize($ifd0Count);
		$exifIfdOffset = $offset;
		$offset += $pixelDimensions ? $ifdSize(2) : 0;
		$gpsIfdOffset = $offset;
		$offset += $gps ? $ifdSize(2) : 0;
		$gpsDataOffset = $offset;
		$offset += $gps ? 24 : 0;
		$ifd1Offset = $offset;
		$thumbnailData = $thumbnail ? self::halves(8, 4) : '';
		$thumbnailOffset = $offset + $ifdSize(2);

		$ifd0 = [[0x0112, 3, 1, $inlineShort($orientation)]];
		if ($pixelDimensions) {
			$ifd0[] = [0x8769, 4, 1, pack($long, $exifIfdOffset)];
		}
		if ($gps) {
			$ifd0[] = [0x8825, 4, 1, pack($long, $gpsIfdOffset)];
		}

		$tiff = ($littleEndian ? 'II' : 'MM') . pack($short, 42) . pack($long, 8)
			. $ifd($ifd0, $thumbnail ? $ifd1Offset : 0)
			. ($pixelDimensions ? $ifd([[0xA002, 3, 1, $inlineShort(40)], [0xA003, 4, 1, pack($long, 20)]], 0) : '')
			. ($gps ? $ifd([[0x0001, 2, 2, "N\x00\x00\x00"], [0x0002, 5, 3, pack($long, $gpsDataOffset)]], 0) . pack($long . '6', ...self::GpsLatitude) : '')
			. ($thumbnail ? $ifd([[0x0201, 4, 1, pack($long, $thumbnailOffset)], [0x0202, 4, 1, pack($long, strlen($thumbnailData))]], 0) . $thumbnailData : '');

		return "Exif\x00\x00" . $tiff;
	}


	/**
	 * Obsah segmentu APP1 s XMP (orientace a GPS poloha jako atributy).
	 */
	public static function xmp(int $orientation = 6): string
	{
		return "http://ns.adobe.com/xap/1.0/\x00"
			. '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
			. '<rdf:Description xmlns:tiff="http://ns.adobe.com/tiff/1.0/" xmlns:exif="http://ns.adobe.com/exif/1.0/"'
			. ' tiff:Orientation="' . $orientation . '" exif:PixelXDimension="40" exif:GPSLatitude="50,4.5N"'
			. ' exif:GPSLongitude="14,25.0E"><exif:GPSAltitude>250/1</exif:GPSAltitude></rdf:Description>'
			. '</rdf:RDF></x:xmpmeta>';
	}


	/** Obsah segmentu APP2 s (zkráceným) barevným profilem ICC */
	public static function iccProfile(): string
	{
		return "ICC_PROFILE\x00\x01\x01" . str_repeat("\x42", 64);
	}


	/**
	 * Nahraný soubor jako z formuláře (dočasný soubor v $dir).
	 */
	public static function upload(string $dir, string $name, string $contents): FileUpload
	{
		$path = $dir . '/' . uniqid('upload', true);
		file_put_contents($path, $contents);

		return new FileUpload([
			'name' => $name,
			'full_path' => $name,
			'size' => strlen($contents),
			'tmp_name' => $path,
			'error' => UPLOAD_ERR_OK,
		]);
	}


	/**
	 * Barva pixelu jako [r, g, b].
	 *
	 * @return array{int, int, int}
	 */
	public static function rgbAt(Image $image, int $x, int $y): array
	{
		$gd = $image->getImageResource();
		$index = imagecolorat($gd, $x, $y);
		$color = imagecolorsforindex($gd, $index === false ? 0 : $index);

		return [$color['red'], $color['green'], $color['blue']];
	}
}
