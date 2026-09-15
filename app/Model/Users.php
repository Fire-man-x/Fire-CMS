<?php
declare(strict_types=1);

namespace App\Model;


/**
 * Users Model
 */
class Users extends BaseModel
{

	const
		TABLE_NAME = 'users',
		COLUMN_ID = 'user_id',
		COLUMN_NAME = 'username',
		COLUMN_PASSWORD_HASH = 'password',
		COLUMN_EMAIL = 'email',
		COLUMN_ROLE = 'role_id',
		COLUMN_OAUTH_SERVICE = 'oauth_service',
		COLUMN_OAUTH_ID = 'oauth_id';

	/**
	 * Users constructor.
	 */
	public function __construct(\Nette\Database\Explorer $database)
	{
		parent::__construct($database);

		$this->setTableName(self::TABLE_NAME);
		$this->setColumnId(self::COLUMN_ID);
	}


	public function findByName(string $username): \Nette\Database\Table\Selection
	{
		return $this->findAll()->where(self::COLUMN_NAME, $username);
	}


	public function findByEmail(string $email): \Nette\Database\Table\Selection
	{
		return $this->findAll()->where(self::COLUMN_EMAIL, $email);
	}


	public function findByOAuthId(string $oauthService, int $oauthId): \Nette\Database\Table\Selection
	{
		return $this->findAll()
			->where(self::COLUMN_OAUTH_SERVICE, $oauthService)
			->where(self::COLUMN_OAUTH_ID, $oauthId);
	}
}
