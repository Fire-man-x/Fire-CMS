<?php
declare(strict_types=1);

namespace App\Components\FileManager\Files;


use Nette\Utils\Image;

/**
 * "Image not found" entity
 */
class EmptyHashImage extends HashImageEntity
{

	public function getHash(): string
	{
		return '_empty';
	}


	public function getMimeType(): string
	{
		return Image::typeToMimeType(Image::PNG);
	}


	public function setHash($hash): void
	{
		throw new \LogicException('Unavailable image hash cannot be changed.');
	}


	public function setMimeType($mimeType): void
	{
		throw new \LogicException('Unavailable image hash cannot be changed.');
	}


	public function getHeight(): int
	{
		return 1080;
	}


	public function getWidth(): int
	{
		return 1920;
	}

}
