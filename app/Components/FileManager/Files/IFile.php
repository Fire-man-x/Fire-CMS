<?php
declare(strict_types=1);

namespace App\Components\FileManager\Files;


/**
 * File meta information interface
 */
interface IFile
{
	/**
	 * Returns the file name.
	 *
	 * @return string
	 */
	public function getName();

	/**
	 * Returns the file extension.
	 *
	 * @return string
	 */
	public function getExtension();

	/**
	 * Returns the file name with extension.
	 *
	 * @return string
	 */
	public function getNameWithExtension();


	/**
	 * Returns the file mime type.
	 *
	 * @return string
	 */
	public function getMimeType();


	/**
	 * Returns the file size.
	 *
	 * @return int
	 */
	public function getSize();


	/**
	 * Sets the file name.
	 *
	 * @param string $name
	 */
	public function setName($name);


	/**
	 * Sets the file extension.
	 *
	 * @param string $extension
	 */
	public function setExtension($extension);


	/**
	 * Sets the file mime type.
	 *
	 * @param string $mimeType
	 */
	public function setMimeType($mimeType);


	/**
	 * Sets the file size.
	 *
	 * @param int $size
	 */
	public function setSize($size);
}
