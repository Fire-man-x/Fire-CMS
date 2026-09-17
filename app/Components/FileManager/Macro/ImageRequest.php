<?php
declare(strict_types=1);

namespace App\Components\FileManager\Macro;


use App\Components\FileManager\Files\IFile;
use App\Components\FileManager\Files\ImageEntity;
use Nette\SmartObject;
use Nette\Utils\Image;

/**
 * Image request encapsulation
 */
class ImageRequest implements IRequest
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
	 *
	 * @param integer $dimensions
	 * @param integer $flags
	 * @param boolean $crop
	 */
	public function __construct(ImageEntity $file, $dimensions = IRequest::ORIGINAL, $flags = IRequest::ORIGINAL, $crop = false)
	{
		if((string)intval($dimensions) == $dimensions && intval($dimensions) === IRequest::ORIGINAL)
		{
			$dimensions = IRequest::ORIGINAL;
		}

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
	public static function crop(ImageEntity $image = null, array $args = array())
	{
		$dimensions = isset($args[0]) ? $args[0] : IRequest::ORIGINAL;
		$flags = Image::FIT;

		$request = new ImageRequest($image, $dimensions, $flags, true);

		return $request;
	}


	/**
	 * Creates the image request from the image macro arguments.
	 *
	 * @return ImageRequest
	 */
	public static function fromMacro(ImageEntity $image = null, array $args = array())
	{
		return new ImageRequest($image, isset($args[0]) ? $args[0] : IRequest::ORIGINAL, isset($args[1]) ? $args[1] : IRequest::ORIGINAL);
	}


	public function getCrop(): bool
	{
		return $this->crop;
	}


	public function getFile(): ImageEntity
	{
		return $this->file;
	}


	public function setFile(IFile|ImageEntity $file): void
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
