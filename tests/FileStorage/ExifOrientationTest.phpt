<?php

declare(strict_types=1);

namespace Tests\FileStorage;

use App\FileStorage\Images\ExifOrientation;
use Nette\Utils\Image;
use Nette\Utils\ImageType;
use Tester\Assert;
use Tester\TestCase;
use Tests\Helpers\TestImages;

require_once __DIR__ . '/../bootstrap.php';

/**
 * EXIF orientace fotek z mobilu: čtení tagu a narovnání obrázku.
 */
final class ExifOrientationTest extends TestCase
{
	public function testRead(): void
	{
		$jpeg = TestImages::halves();

		Assert::same(ExifOrientation::Normal, ExifOrientation::read($jpeg), 'bez EXIF');
		Assert::same(6, ExifOrientation::read(TestImages::withExifOrientation($jpeg, 6)));
		Assert::same(8, ExifOrientation::read(TestImages::withExifOrientation($jpeg, 8)));
		Assert::same(ExifOrientation::Normal, ExifOrientation::read(TestImages::halves(type: ImageType::PNG)), 'PNG');
	}


	public function testApplyRotatesPortraitPhoto(): void
	{
		// orientace 6 = fotka na výšku, uložená na ležato: pro zobrazení otočit o 90° po směru hodinových ručiček
		$image = Image::fromString(TestImages::halves());
		ExifOrientation::apply($image, 6);

		Assert::same([20, 40], [$image->getWidth(), $image->getHeight()]);
		[$r, , $b] = TestImages::rgbAt($image, 10, 5);
		Assert::true($r > 200 && $b < 60, 'levá (červená) strana je po otočení nahoře');
		[$r, , $b] = TestImages::rgbAt($image, 10, 35);
		Assert::true($b > 200 && $r < 60, 'pravá (modrá) strana je dole');
	}


	public function testApplyOrientation8And3(): void
	{
		$image = Image::fromString(TestImages::halves());
		ExifOrientation::apply($image, 8); // o 90° proti směru hodinových ručiček - levá strana dole
		[$r, , ] = TestImages::rgbAt($image, 10, 35);
		Assert::true($r > 200);

		$image = Image::fromString(TestImages::halves());
		ExifOrientation::apply($image, 3); // o 180° - levá strana vpravo
		Assert::same([40, 20], [$image->getWidth(), $image->getHeight()]);
		[$r, , ] = TestImages::rgbAt($image, 35, 10);
		Assert::true($r > 200);

		$image = Image::fromString(TestImages::halves());
		ExifOrientation::apply($image, 2); // zrcadlově - levá strana vpravo, rozměry beze změny
		[$r, , ] = TestImages::rgbAt($image, 35, 10);
		Assert::true($r > 200);
	}
}

(new ExifOrientationTest())->run();
