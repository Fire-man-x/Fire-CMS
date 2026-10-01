<?php
declare(strict_types=1);

namespace App\Components\FileManager\Request;

use App\Components\FileManager\Files\File;

/**
 * Image request interface
 */
interface Request
{

	const string ORIGINAL = '0';


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
	 */
	public function getFile(): File;


	/**
	 * Sets the requested image file information.
	 */
	public function setFile(File $file): void;

}