<?php
declare(strict_types=1);

namespace App\Security;

interface IUserAccessibleEntity
{

	/**
	 * Check Access for user
	 */
	public function checkAccess(int $userId, ?string $privilege = null, $createdByUserId = null);

}
