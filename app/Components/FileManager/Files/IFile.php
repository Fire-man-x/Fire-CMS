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
	 */
	public function getName(): string;

	/**
	 * Returns the file extension.
	 */
	public function getExtension(): string;

	/**
	 * Returns the file name with extension.
	 */
	public function getNameWithExtension(): string;


	/**
	 * Returns the file mime type.
	 */
	public function getMimeType(): string;


	/**
	 * Returns the file size.
	 */
	public function getSize(): int;


	/**
	 * Sets the file name.
	 */
	public function setName(string $name): void;


	/**
	 * Sets the file extension.
	 */
	public function setExtension(string $extension): void;


	/**
	 * Sets the file mime type.
	 */
	public function setMimeType(string $mimeType): void;


	/**
	 * Sets the file size.
	 */
	public function setSize(int $size): void;
}
