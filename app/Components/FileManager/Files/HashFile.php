<?php
declare(strict_types=1);

namespace App\Components\FileManager\Files;


/**
 * File special meta information interface
 */
interface HashFile extends File
{

	/**
	 * Returns the file id.
	 */
	public function getId(): int;


	/**
	 * Returns the file SHA1 hash.
	 */
	public function getHash(): string;


	/**
	 * Sets the file id.
	 */
	public function setId(int $id): void;


	/**
	 * Sets the file SHA1 hash.
	 */
	public function setHash(string $hash): void;
}
