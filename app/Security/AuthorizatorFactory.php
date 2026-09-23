<?php
declare(strict_types=1);

namespace App\Security;

use App\Model\Modules;
use App\Model\Roles;
use Nette\Security\Permission;

class AuthorizatorFactory
{

	protected static Modules $modelModules;

	protected static Roles $modelRoles;

	

	/**
	 * Acl constructor
	 */
	public static function create(Modules $modelModules, Roles $modelRoles): Permission
	{
		self::$modelModules = $modelModules;
		self::$modelRoles = $modelRoles;

		$acl = new Permission;

		//roles
		$roles = self::$modelRoles->getListWithName();
		foreach ($roles as $role) {
			$acl->addRole($role);
		}

		//modules
		$modules = self::$modelModules->getListWithName();
		foreach ($modules as $module) {
			$acl->addResource($module);
		}

		//ALLOW rules
		$roleHasSomePermission = [];
		$rules = self::$modelRoles->getRolesModules()->fetchAll();
		foreach ($rules as $rule) {
			if (isset(Acl::$privileges[$rule->privilege])) {
				//insert all lower permissions
				foreach (Acl::$privileges as $permission) {
					$acl->allow($rule->roleName, $rule->moduleName, $permission);

					if ($permission == $rule->privilege) {
						break;
					}
				}
			} else { //is other privilege
				$acl->allow($rule->roleName, $rule->moduleName, $rule->privilege);
			}

			$roleHasSomePermission[$rule->roleName] = $rule->roleName;
		}

		//has access to Admin
		$acl->addResource('Admin');
		foreach($roleHasSomePermission as $roleWithPermission)
		{
			$acl->allow($roleWithPermission, 'Admin', Permission::All);
		}

		//DENY everything, what you can't to do, but with own you can do everything
		$acl->deny(Permission::All, Permission::All, Permission::All, function (Permission $acl, $role, $resource, $privilege) {
			//dump("Deny function");
			//dump($acl, $role, $resource, $privilege);
			//if ($role === Role::admin)
			//	return true; // admin can everything
			/*if($acl->getQueriedResource() instanceof Resource){
				\Tracy\Debugger::barDump($acl->getQueriedRole(), "allow");
				\Tracy\Debugger::barDump($acl->getQueriedResource());
				\Tracy\Debugger::barDump($role);
				\Tracy\Debugger::barDump($resource);
				\Tracy\Debugger::barDump($privilege);
			}*/

			if($acl->getQueriedRole() instanceof Role && $acl->getQueriedResource() instanceof Resource){
				return $acl->getQueriedRole()->id != $acl->getQueriedResource()->id;
			}

			return true;
		});

		//ADMIN can do everything
		$acl->allow('admin', Permission::All, Permission::All);

		return $acl;
	}
}
