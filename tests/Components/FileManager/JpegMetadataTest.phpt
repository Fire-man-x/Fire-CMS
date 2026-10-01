<?php

declare(strict_types=1);

namespace Tests\Components\FileManager;

use App\Components\FileManager\Images\JpegMetadata;
use Nette\Utils\Image;
use Nette\Utils\ImageType;
use Tester\Assert;
use Tester\TestCase;
use Tests\Helpers\TestImages;

require_once __DIR__ . '/../../bootstrap.php';

/**
 * Metadata JPEG (EXIF, XMP, IPTC, ICC): přenos do obrázku přeuloženého přes GD, narovnaná orientace, nové
 * rozměry a odstranění GPS polohy - v obou pořadích bajtů EXIF (Android II, iPhone MM).
 */
final class JpegMetadataTest extends TestCase
{
	/**
	 * @return array<string, mixed> výsledek exif_read_data() po sekcích
	 */
	private static function readExif(string $jpeg): array
	{
		$stream = fopen('php://memory', 'r+b') ?: throw new \RuntimeException();
		fwrite($stream, $jpeg);
		rewind($stream);
		$exif = @exif_read_data($stream, null, true);

		return is_array($exif) ? $exif : [];
	}


	private static function photo(bool $littleEndian = true): string
	{
		return TestImages::withSegments(TestImages::halves(), [
			[0xE1, TestImages::exif(6, littleEndian: $littleEndian)],
			[0xE1, TestImages::xmp()],
			[0xE2, TestImages::iccProfile()],
			[0xED, "Photoshop 3.0\x00" . '8BIM fake IPTC'],
			[0xE2, "MPF\x00" . 'multi-picture'], // nepřenáší se
		]);
	}


	/**
	 * @return list<array{bool}>
	 */
	public function getByteOrders(): array
	{
		return [[true], [false]];
	}


	public function testExtract(): void
	{
		$metadata = JpegMetadata::fromJpeg(self::photo());

		Assert::false($metadata->isEmpty());
		Assert::true($metadata->hasGps());
		Assert::true(JpegMetadata::fromJpeg(TestImages::halves())->isEmpty(), 'GD metadata nezapisuje');
		Assert::true(JpegMetadata::fromJpeg(TestImages::halves(type: ImageType::PNG))->isEmpty());
		Assert::true(JpegMetadata::fromJpeg('not an image')->isEmpty());
	}


	/**
	 * @dataProvider getByteOrders
	 */
	public function testTransferToResizedImage(bool $littleEndian): void
	{
		$source = self::photo($littleEndian);
		$resized = Image::fromString($source)->resize(10, null)->toString(ImageType::JPEG, 90);
		Assert::same([], array_intersect_key(self::readExif($resized), ['IFD0' => 1, 'EXIF' => 1]), 'GD samo EXIF nezapíše');

		$result = JpegMetadata::fromJpeg($source)->forImage(10, 5)->applyTo($resized);

		$exif = self::readExif($result);
		Assert::same(1, $exif['IFD0']['Orientation'] ?? null, 'obrázek je fyzicky narovnaný - prohlížeč ho nesmí otočit podruhé');
		Assert::same(10, $exif['EXIF']['ExifImageWidth'] ?? null);
		Assert::same(5, $exif['EXIF']['ExifImageLength'] ?? null);
		Assert::false(isset($exif['THUMBNAIL']['JPEGInterchangeFormat']), 'vložený náhled v původní orientaci se zahodí');
		Assert::same(['50/1', '4/1', '30/1'], $exif['GPS']['GPSLatitude'] ?? null, 'bez withoutGps() poloha zůstává');
		Assert::contains('tiff:Orientation="1"', $result);
		Assert::contains('exif:PixelXDimension="10"', $result);
		Assert::contains(TestImages::iccProfile(), $result);
		Assert::contains('8BIM fake IPTC', $result);
		Assert::notContains('multi-picture', $result);

		$image = Image::fromString($result); // obrazová data zůstala platná
		Assert::same([10, 5], [$image->getWidth(), $image->getHeight()]);
	}


	/**
	 * @dataProvider getByteOrders
	 */
	public function testWithoutGps(bool $littleEndian): void
	{
		$source = self::photo($littleEndian);
		$metadata = JpegMetadata::fromJpeg($source)->withoutGps();

		Assert::false($metadata->hasGps());
		$result = $metadata->applyTo($source);
		$exif = self::readExif($result);
		Assert::false(isset($exif['GPS']));
		Assert::same(6, $exif['IFD0']['Orientation'] ?? null, 'ostatní EXIF beze změny');
		Assert::same(40, $exif['EXIF']['ExifImageWidth'] ?? null);
		Assert::notContains(pack($littleEndian ? 'V6' : 'N6', ...TestImages::GpsLatitude), $result, 'souřadnice nezůstanou ani v bajtech souboru');
		Assert::notContains('GPS', substr($result, 0, 2000), 'ani v XMP');
		Assert::contains('tiff:Orientation="6"', $result);
		// hlavní SOS je poslední výskyt FF DA - v obrazových datech se FF vždy doplňuje 00, dřívější výskyt je náhled v EXIF
		Assert::same(substr($source, strrpos($source, "\xFF\xDA") ?: 0), substr($result, strrpos($result, "\xFF\xDA") ?: 0), 'obrazová data beze změny');
	}


	public function testOnlyColorProfile(): void
	{
		$result = JpegMetadata::fromJpeg(self::photo())->onlyColorProfile()->applyTo(TestImages::halves());

		Assert::contains(TestImages::iccProfile(), $result);
		Assert::same([], array_intersect_key(self::readExif($result), ['IFD0' => 1, 'GPS' => 1]));
		Assert::notContains('xmpmeta', $result);
	}


	public function testBrokenExifIsDropped(): void
	{
		$broken = "Exif\x00\x00II*\x00" . pack('V', 5000); // IFD0 mimo data
		$source = TestImages::withSegments(TestImages::halves(), [[0xE1, $broken], [0xE2, TestImages::iccProfile()]]);
		$metadata = JpegMetadata::fromJpeg($source);

		Assert::true($metadata->hasGps(), 'nečitelné EXIF - počítá se s tím, že polohu obsahuje');
		$result = $metadata->forImage(10, 10)->applyTo(TestImages::halves());
		Assert::notContains($broken, $result, 'poškozené EXIF se zahodí');
		Assert::contains(TestImages::iccProfile(), $result);
	}
}

(new JpegMetadataTest())->run();
