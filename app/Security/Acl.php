<?php
declare(strict_types=1);

namespace App\Security;

use Nette\Security\Permission;

/**
 * Acl
 * @package App\Security
 * @author Vaclav Koterec
 */
class Acl extends Permission
{

	/**
	 * Privileges
	 */
	public static array $privileges = array(
		"view"=>"view",
		"add"=>"add",
		"edit"=>"edit",
		"delete"=>"delete"
	);

	/**
	 * Privileges
	 */
	public static array $privilegesPriority = array(
		1=>"view",
		2=>"add",
		3=>"edit",
		4=>"delete"
	);

	/*public function isAllowed($role = self::ALL, $resource = self::ALL, $privilege = self::ALL)
	{
		\Tracy\Debugger::barDump($role, "role");
		$parent = parent::isAllowed($role, $resource, $privilege);
		\Tracy\Debugger::barDump(array(
			$role,$resource,$privilege, "parent"=>$parent
		),"isAllowed");
		return $parent;
	}*/

}
