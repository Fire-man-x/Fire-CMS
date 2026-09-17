<?php
declare(strict_types=1);

namespace App\Components\FileManager\Request;


use App\Components\FileManager\Files\File;
use App\Components\FileManager\Files\HashImageEntity;
use Nette\SmartObject;
use Nette\Utils\Image;

/**
 * Image request encapsulation
 */
class ImageRequest implements Request
{
	use SmartObject;

	/**
	 * The requested image file information.
	 *
	 */
	private HashImageEntity $file;

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
	public function __construct(HashImageEntity $file, string $dimensions = Request::ORIGINAL, int $flags = 0, bool $crop = false)
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
	 * @return ImageRequest
	 */
	public static function crop(HashImageEntity $image = null, array $args = array())
	{
		$dimensions = $args[0] ?? Request::ORIGINAL;
		$flags = Image::OrSmaller;

		$request = new ImageRequest($image, $dimensions, $flags, true);

		return $request;
	}


	/**
	 * Creates the image request from the image macro arguments.
	 */
	public static function fromMacro(HashImageEntity $image = null, array $args = array()): ImageRequest
	{
		return new ImageRequest($image, $args[0] ?? Request::ORIGINAL, $args[1] ?? 0);
	}


	public function getCrop(): bool
	{
		return $this->crop;
	}


	public function getFile(): HashImageEntity
	{
		return $this->file;
	}


	public function setFile(File|HashImageEntity $file): void
	{
		if(!$file instanceof HashImageEntity){
			throw new \LogicException('File is not instance of HashImageEntity.');
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
