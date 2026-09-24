<?php
declare(strict_types=1);

namespace App\Model\Exceptions;

use Exception;
use Throwable;


class DuplicateEmailException extends Exception
{
	public function __construct(string $message = "", int $code = 0, ?Throwable $previous = NULL)
	{
		if($message == ""){
			$message = "Email is already taken.";
		}

		parent::__construct($message, $code, $previous);
	}
}