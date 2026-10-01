<?php
declare(strict_types=1);

namespace App\Components\FileManager\Files;


use App\Components\FileManager\Exceptions\DirectoryException;
use Nette\SmartObject;

/**
 * Directory wrapper
 */
class Directory
{
	use SmartObject;

	private string $name;


	public function __construct(string $name, bool $tryCreateDirectories = true)
	{
		$this->name = $this->check($name, $tryCreateDirectories);
	}


	/**
	 * Checks a directory to be existing and writable.
	 * Tries to create it if it does not exist.
	 *
	 */
	private function check(string $name, bool $tryCreateDirectories = true): string
	{
		$exists = true;
		$isWritable = true;

		if (!file_exists($name)) {
			$exists = false;
			if ($tryCreateDirectories) {
				umask(0002);
				$exists = @mkdir($name, 0777, true); // @: will be escalated to exception on failure
			}
		}
		if (!is_writable($name)) {
			$isWritable = false;
		}

		if (!$exists || !$isWritable) {
			throw new DirectoryException($name);
		}

		return $name;
	}


	/**
	 * @return string
	 */
	public function __toString()
	{
		return (string) $this->name;
	}


	/**
	 * Returns true if the compared directory is the same as this, false otherwise.
	 *
	 */
	public function is(Directory $to): bool
	{
		return realpath($this->name) === realpath($to->name);
	}


}
