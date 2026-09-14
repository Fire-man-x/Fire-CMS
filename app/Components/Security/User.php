<?php
declare(strict_types=1);

namespace App\Security;

use Nette\Security\Authorizator;

/**
 * Class User
 * @package App\Security
 * @author Vaclav Koterec
 */
class User extends \Nette\Security\User
{


	/*public function __construct(UserStorage $storage, Authenticator $authenticator = null, Authorizator $authorizator = null)
	{
		parent::__construct($storage, $authenticator, $authorizator);
	}*/


	/**
	 * Is a user in the specified effective role?
	 * @note Fix functionality with IRole
	 * @param  string
	 * /
	public function isInRole($role)
	{
		$roles = array_map(function($item) {
			if ($item instanceof IRole) {
				return $item->getRoleId();
			}
			return $item;
		}, $this->getRoles());

		return in_array($role, $roles, true);
	}*/


	/**
	 * Has a user effective access to the Resource?
	 * If $resource is null, then the query applies to all resources.
	 */
	public function isAllowed(mixed $resource = Authorizator::All, mixed $privilege = Authorizator::All, ?int $createdByUserId = null): bool
	{
		//bdump(func_get_args(), 'isAllowed');
		$isAllowed = parent::isAllowed($resource, $privilege);

		if ($resource instanceof IUserAccessibleEntity && $isAllowed === false) {
			return $resource->checkAccess($this->id, $privilege, $createdByUserId);
		}

		return $isAllowed;
	}

}
