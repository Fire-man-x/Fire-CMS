<?php
declare(strict_types=1);

namespace App\Model;


/**
 * Users Model
 */
class Users extends BaseModel
{

	const
		TABLE_NAME = 'firecms_users',
		COLUMN_ID = 'id',
		COLUMN_NAME = 'username',
		COLUMN_PASSWORD_HASH = 'password',
		COLUMN_EMAIL = 'email',
		COLUMN_ROLE = 'roleId',
		COLUMN_OAUTH_SERVICE = 'oauthService',
		COLUMN_OAUTH_ID = 'oauthId';

	/**
	 * Users constructor.
	 */
	public function __construct(\Nette\Database\Explorer $database)
	{
		parent::__construct($database);

		$this->setTableName(self::TABLE_NAME);
		$this->setColumnId(self::COLUMN_ID);
		$this->setForeignKeyColumn('userId');
	}


	public function findByName(string $username): \Nette\Database\Table\Selection
	{
		return $this->findAll()->where(self::COLUMN_NAME, $username);
	}


	public function findByEmail(string $email): \Nette\Database\Table\Selection
	{
		return $this->findAll()->where(self::COLUMN_EMAIL, $email);
	}


	public function findByOAuthId(string $oauthService, string $oauthId): \Nette\Database\Table\Selection
	{
		return $this->findAll()
			->where(self::COLUMN_OAUTH_SERVICE, $oauthService)
			->where(self::COLUMN_OAUTH_ID, $oauthId);
	}
}
