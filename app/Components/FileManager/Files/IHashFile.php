<?php
declare(strict_types=1);

namespace App\Components\FileManager\Files;


/**
 * File special meta information interface
 */
interface IHashFile extends IFile
{

	/**
	 * Returns the file id.
	 *
	 * @return int
	 */
	public function getId();


	/**
	 * Returns the file SHA1 hash.
	 *
	 * @return string
	 */
	public function getHash();


	/**
	 * Sets the file id.
	 *
	 * @param string $id
	 */
	public function setId($id);


	/**
	 * Sets the file SHA1 hash.
	 *
	 * @param string $hash
	 */
	public function setHash($hash);
}
