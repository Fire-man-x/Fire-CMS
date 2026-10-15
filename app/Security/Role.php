<?php
declare(strict_types=1);

namespace App\Security;

use Exception;
use Nette\Security\SimpleIdentity;
use Nette\Security\User;

class Role implements \Nette\Security\Role
{
	public function __construct(private string $name, public int $actualUserId)
	{
	}


	public function getRoleId(): string
	{
		return $this->name;
	}

}
