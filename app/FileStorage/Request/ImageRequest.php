<?php
declare(strict_types=1);

namespace App\FileStorage\Request;


use App\FileStorage\Files\File;
use App\FileStorage\Files\ImageEntity;
use Nette\SmartObject;
use Nette\Utils\Image;

/**
 * Image request encapsulation
 *
 * Obrázek je libovolná ImageEntity - i obrázek mimo správce souborů (bez id a hashe), např. soubor na disku
 * načtený pluginem s vlastním úložištěm (IStorage). FlysystemStorage pracuje jen s obrázky s hashem (HashFile).
 */
class ImageRequest implements Request
{
	use SmartObject;

	/**
	 * The requested image file information.
	 *
	 */
	private ImageEntity $file;

	/**
	 * The requested image thumbnail dimensions
	 *
	 */
	private string $dimensions;

	/**
	 * The requested image thumbnail flags.
	 *
	 */
	private int $flags;

	/**
	 * The requested image thumbnail cropping flag.
	 *
	 */
	private bool $crop = false;


	/**
	 * Constructs the image request from the given file information, requested dimensions and flags.
	 */
	public function __construct(ImageEntity $file, string $dimensions = Request::ORIGINAL, int $flags = 0, bool $crop = false)
	{
		/*if((string)intval($dimensions) == $dimensions && intval($dimensions) === IRequest::ORIGINAL)
		{
			$dimensions = IRequest::ORIGINAL;
		}*/

		$this->file = $file;
		$this->dimensions = $dimensions;
		$this->flags = $flags;
		$this->crop = $crop;
	}


	/**
	 * Creates the image request from the crop macro arguments.
	 *
	 * @param array{0?: string} $args rozměry ořezu, např. ['130x130']
	 */
	public static function crop(ImageEntity $image, array $args = array()): ImageRequest
	{
		$dimensions = $args[0] ?? Request::ORIGINAL;
		$flags = Image::OrSmaller;

		$request = new ImageRequest($image, $dimensions, $flags, true);

		return $request;
	}


	/**
	 * Creates the image request from the image macro arguments.
	 *
	 * @param array{0?: string, 1?: int} $args rozměry a příznaky Image::*, např. ['300x200', Image::ShrinkOnly]
	 */
	public static function fromMacro(ImageEntity $image, array $args = array()): ImageRequest
	{
		return new ImageRequest($image, $args[0] ?? Request::ORIGINAL, $args[1] ?? 0);
	}


	public function getCrop(): bool
	{
		return $this->crop;
	}


	public function getFile(): ImageEntity
	{
		return $this->file;
	}


	public function setFile(File $file): void
	{
		if(!$file instanceof ImageEntity){
			throw new \LogicException('File is not instance of ImageEntity.');
		}

		$this->file = $file;
	}


	public function getDimensions(): string
	{
		return $this->dimensions;
	}


	public function getFlags(): int
	{
		return $this->flags;
	}

}
