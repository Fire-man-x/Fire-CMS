<?php
declare(strict_types=1);

namespace App\Plugins\SimpleSignUp;

use Nette\Security\AuthenticationException;
use Nette\Security\IAuthenticator;
use Nette\Security\Identity;
use Nette\Security\IIdentity;
use Nette\SmartObject;

/**
 * Trivial implementation of IAuthenticator.
 */
class SimpleAuthenticator implements IAuthenticator
{
	use SmartObject;

	private string $simplePassword;


	/**
	 * SimpleAuthenticator constructor.
	 */
	public function __construct(string $simplePassword)
	{
		$this->simplePassword = $simplePassword;
	}


	/**
	 * Performs an authentication against e.g. database.
	 * and returns IIdentity on success or throws AuthenticationException
	 * @throws AuthenticationException
	 */
	public function authenticate(array $credentials): IIdentity
	{
		list($username, $password) = $credentials;
		if (strcasecmp('guest', $username) === 0) 	{
			if ((string) $this->simplePassword === (string) $password) {
				return new Identity('guest', 'guest');
			} else {
				throw new AuthenticationException('Invalid password.', self::INVALID_CREDENTIAL);
			}
		}
		throw new AuthenticationException("User '$username' not found.", self::IDENTITY_NOT_FOUND);
	}
}
