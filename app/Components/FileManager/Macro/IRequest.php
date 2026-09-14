<?php
declare(strict_types=1);

namespace App\Components\FileManager\Macro;

use App\Components\FileManager\Files\FileEntity;
use App\Components\FileManager\Files\IFile;
use App\Components\FileManager\Files\ImageEntity;

/**
 * Image request interface
 */
interface IRequest
{

	const ORIGINAL = 0;


	/**
	 * Returns the requested image thumbnail cropping flag.
	 *
	 */
	public function getCrop(): bool;


	/**
	 * Returns the requested image thumbnail dimensions.
	 *
	 */
	public function getDimensions(): string;


	/**
	 * Returns the requested image thumbnail flags.
	 *
	 */
	public function getFlags(): int;


	/**
	 * Returns the requested image file information.
	 *
	 * @return IFile|FileEntity|ImageEntity
	 */
	public function getFile();


	/**
	 * Sets the requested image file information.
	 *
	 * @return IRequest
	 */
	public function setFile(IFile $file);

}