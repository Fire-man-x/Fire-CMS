<?php
declare(strict_types=1);

namespace App\Model;

use App\Security\Role;
use Nette\Database\UniqueConstraintViolationException;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Security\IIdentity;
use Nette\Security\Passwords;
use Nette\Security\SimpleIdentity;
use Nette\SmartObject;
use Nette\Utils\ArrayHash;


/**
 * Users management.
 */
class UserManager implements Authenticator
{
	use SmartObject;

	private Passwords $passwords;

	private Users $usersModel;

	private Roles $rolesModel;


	/**
	 * Constructor of UserManager
	 */
	public function __construct(Passwords $passwords, Users $usersModel, Roles $rolesModel)
	{
		$this->passwords = $passwords;
		$this->usersModel = $usersModel;
		$this->rolesModel = $rolesModel;
	}


	/**
	 * Performs an authentication.
	 * @throws AuthenticationException
	 */
	public function authenticate(string $username, string $password): IIdentity
	{
		/*if ($username == "admin") {
			if ($password != "admin") {
				throw new Nette\Security\AuthenticationException('The password is incorrect.', self::INVALID_CREDENTIAL);
			}
			/*$row = $this->getAll()->where(self::COLUMN_ID, 1)->fetch()->toArray();
			unset($row[self::COLUMN_PASSWORD_HASH]);
			return new Nette\Security\Identity(1, array("admin"), $row);* /
			return new Nette\Security\Identity(1, array("admin"), array("username"=>"Administrátor"));
		}*/

		$row = $this->usersModel->findByName($username)->fetch();

		if (!$row) {
			throw new AuthenticationException('The username is incorrect.', self::IDENTITY_NOT_FOUND);

		} elseif (!$this->passwords->verify($password, $row[Users::COLUMN_PASSWORD_HASH])) {
			throw new AuthenticationException('The password is incorrect.', self::INVALID_CREDENTIAL);

		} elseif ($this->passwords->needsRehash($row[Users::COLUMN_PASSWORD_HASH])) {
			$row->update([
				Users::COLUMN_PASSWORD_HASH => $this->passwords->hash($password),
			]);
		}

		$arr = $row->toArray();
		$arr["makedName"] = $this->makeName($arr);
		unset($arr[Users::COLUMN_PASSWORD_HASH]);
		$role = $this->rolesModel->getById($row[Users::COLUMN_ROLE]);
		if(!$role){
			throw new RecordNotFoundException();
		}
		return new SimpleIdentity($arr[Users::COLUMN_ID], array(new Role($role["name"], $arr[Users::COLUMN_ID])), $arr);
	}


	/**
	 * Performs an OAuth authentication.
	 * @throws AuthenticationException
	 */
	public function authenticateByOauth(string $oauthService, int $oauthId, string $oauthEmail): SimpleIdentity
	{
		$user = $this->usersModel->findByOAuthId($oauthService, $oauthId);
		if(!$user) {
			// found existing user with the same email, error and force them to login using password
			$user = $this->usersModel->findByEmail($oauthEmail);
		}

		$user = $user->fetch();

		if(!$user) {
			throw new AuthenticationException('Authentication by OAuth is incorrect.', self::IDENTITY_NOT_FOUND);
		}

		$arr = $user->toArray();
		$arr["makedName"] = $this->makeName($arr);
		unset($arr[Users::COLUMN_PASSWORD_HASH]);
		$role = $this->rolesModel->getById($user[Users::COLUMN_ROLE]);
		if(!$role){
			throw new RecordNotFoundException();
		}
		return new SimpleIdentity($arr[Users::COLUMN_ID], array(new Role($role["name"], $arr[Users::COLUMN_ID])), $arr);
	}


	/**
	 * Adds new user.
	 * @throws DuplicateNameException
	 */
	public function add(string $username, string $email, string $password, ArrayHash $data): int
	{
		if(!isset($data[Users::COLUMN_ROLE])){
			$data[Users::COLUMN_ROLE] = $this->rolesModel->findByName("subscriber")->fetchField($this->rolesModel->getColumnId());
		}
		try {
			$data[Users::COLUMN_NAME] = $username;
			$data[Users::COLUMN_PASSWORD_HASH] = $password ? $this->passwords->hash($password) : '';
			$data[Users::COLUMN_EMAIL] = $email;

			 return $this->usersModel->insert($data);
		} catch (UniqueConstraintViolationException $e) {
			throw new DuplicateNameException;
		}
	}


	/**
	 * Make name from user credentials
	 */
	public function makeName(array $data): string
	{
		if (!empty($data["nickname"])) {
			return $data["nickname"];
		} else {
			return trim($data["first_name"] . " " . $data["surname"]);
		}
	}

}
