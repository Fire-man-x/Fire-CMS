<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Security\Role;
use Nette\Security\Permission;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Čistý unit test bez DB. App\Security\Role je role ACL s ID přihlášeného uživatele - vytváří ji
 * App\Security\User::isAllowed() z názvu role v identitě, aby pravidlo "autor smí se svým záznamem cokoliv"
 * v AuthorizatorFactory porovnalo ID uživatele s autorem záznamu (App\Security\Resource::$createdById).
 */
final class RoleTest extends TestCase
{
	public function testConstructedFromRoleNameAndUserId(): void
	{
		$role = new Role('admin', 5);

		Assert::same('admin', $role->getRoleId());
		Assert::same(5, $role->actualUserId);
	}

	public function testImplementsNetteRoleInterface(): void
	{
		Assert::true(new Role('member', 1) instanceof \Nette\Security\Role);
	}

	public function testPermissionResolvesRoleByName(): void
	{
		$acl = new Permission;
		$acl->addRole('author');
		$acl->addResource('Articles');
		$acl->allow('author', 'Articles', 'view');

		Assert::true($acl->isAllowed(new Role('author', 5), 'Articles', 'view'));
		Assert::false($acl->isAllowed(new Role('author', 5), 'Articles', 'edit'));
	}
}

(new RoleTest())->run();
