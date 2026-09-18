<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Security\Role;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Čistý unit test bez DB — App\Security\Role od 2026-09-18 fyzicky leží přímo v
 * app/Security/ (dřív v app/Components/Security/, viz Changelog), takže ho Composer PSR-4
 * najde bez pomocného `classmap` v composer.json.
 *
 * Testuje jen konstruktor `new Role(string $role, $id)`, což je jediný způsob, jakým se
 * Role v aplikaci reálně vytváří (viz App\Model\UserManager). Konstruktor má i větev pro
 * jediný argument typu Nette\Security\User/App\Security\Identity, kterou ale nikde v repu
 * nikdo nepoužívá — a `App\Security\Identity` ani `App\Security\Exception` v repu vůbec
 * neexistují, takže by ta větev při skutečném zavolání spadla na fatální
 * "Class ... not found" místo očekávané výjimky. Záměrně netestováno jako mrtvý/rozbitý kód.
 */
final class RoleTest extends TestCase
{
	public function testConstructedFromRoleNameAndId(): void
	{
		$role = new Role('admin', 5);

		Assert::same('admin', $role->getRoleId());
		Assert::same('admin', $role->role);
		Assert::same(5, $role->id);
	}

	public function testImplementsNetteRoleInterface(): void
	{
		Assert::true(new Role('member', 1) instanceof \Nette\Security\Role);
	}
}

(new RoleTest())->run();
