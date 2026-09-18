<?php
declare(strict_types=1);

namespace App\Security;

use Exception;
use Nette\Security\SimpleIdentity;
use Nette\Security\User;

class Role implements \Nette\Security\Role
{

	const guest = 'guest';
	const member = 'member';
	const admin = 'admin';

	public $id = null;
	public $role = null;


	public function __construct()
	{
		$args = func_get_args(); // get input args
		// instance of User or Identity
		if (func_num_args() == 1) {
			if ($args[0] instanceof User) {
				$this->role = $args[0]->getIdentity()->getRoles();
				$this->id = $args[0]->getIdentity()->id;
			} elseif ($args[0] instanceof SimpleIdentity) {
				$this->role = $args[0]->role;
				$this->id = $args[0]->id;
			} else {
				throw new Exception('Wrong input object.');
			}
		}
		// role / id
		if (func_num_args() >= 2) {
			$this->role = $args[0];
			$this->id = $args[1];
		}
	}


	public function getRoleId(): string
	{
		return $this->role;
	}

}
