<?php
declare(strict_types=1);

namespace App\Components\FileManager\Files;

use App\Components\FileManager\Exceptions\HashException;
use Nette\Utils\Strings;

/**
 * Basic implementation of the file information
 */
class HashFileEntity extends FileEntity implements IHashFile
{

	/**
	 * File id
	 *
	 */
	private int $id;

	/**
	 * SHA1 hash of the original image
	 *
	 */
	private string $hash;


	/**
	 * @return int
	 */
	public function getId()
	{
		return $this->id;
	}


	/**
	 * @param  int $id
	 * @return HashFileEntity
	 */
	public function setId($id)
	{
		$this->id = $id;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getHash()
	{
		return $this->hash;
	}


	/**
	 * @param  string $hash
	 * @return HashFileEntity
	 */
	public function setHash($hash)
	{
		$this->hash = $this->checkHash($hash);

		return $this;
	}


	/**
	 * Checks that the given string is valid SHA1 hash and normalizes it to lower case.
	 *
	 * @param  string $hash Image hash to validate
	 * @throws HashException If the hash is not valid image hash
	 * @return string        The valid image hash
	 */
	protected function checkHash($hash)
	{
		if (!is_string($hash)) {
			throw new HashException($hash);
		}
		$hash = Strings::lower($hash);
		if (!preg_match('/^[0-9a-f]{40}$/', $hash)) {
			throw new HashException($hash);
		}

		return $hash;
	}

}
