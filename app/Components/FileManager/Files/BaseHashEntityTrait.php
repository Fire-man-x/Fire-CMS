<?php
declare(strict_types=1);

namespace App\Components\FileManager\Files;


use App\Components\FileManager\Exceptions\HashException;
use Nette\Utils\Strings;

trait BaseHashEntityTrait
{

	/**
	 * File id
	 */
	private int $id;

	/**
	 * SHA1 hash of the original image
	 */
	private string $hash;


	public function getId(): int
	{
		return $this->id;
	}


	public function setId(int $id): void
	{
		$this->id = $id;
	}


	public function getHash(): string
	{
		return $this->hash;
	}


	/**
	 * @throws HashException
	 */
	public function setHash(string $hash): void
	{
		$this->hash = $this->checkHash($hash);
	}


	/**
	 * Checks that the given string is valid SHA1 hash and normalizes it to lower case.
	 * @param string $hash Image hash to validate
	 * @return string The valid image hash
	 * @throws HashException If the hash is not valid image hash
	 */
	protected function checkHash(string $hash): string
	{
		$hash = Strings::lower($hash);
		if (!preg_match('/^[0-9a-f]{40}$/', $hash)) {
			throw new HashException($hash);
		}

		return $hash;
	}

}
