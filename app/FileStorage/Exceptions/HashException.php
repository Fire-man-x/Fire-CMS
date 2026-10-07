<?php
declare(strict_types=1);

namespace App\FileStorage\Exceptions;


use Exception;

/**
 * Invalid hash exception
 */
class HashException extends Exception
{

	/**
	 * @param string $hash
	 */
	public function __construct($hash)
	{
		$message = sprintf('The image hash is invalid. It is required to be a SHA1 hash but %s given.', var_export($hash, true));

		parent::__construct($message);
	}

}
