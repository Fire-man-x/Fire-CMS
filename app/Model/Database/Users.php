<?php
declare(strict_types=1);

namespace App\Model\Database;


use Nette\Database\Table\ActiveRow;

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


	public function getByName(string $username): ?ActiveRow
	{
		return $this->findAll()->where(self::COLUMN_NAME, $username)->fetch();
	}


	public function getByEmail(string $email): ?ActiveRow
	{
		return $this->findAll()->where(self::COLUMN_EMAIL, $email)->fetch();
	}


	public function getByOAuthId(string $oauthService, string $oauthId): ?ActiveRow
	{
		return $this->findAll()
			->where(self::COLUMN_OAUTH_SERVICE, $oauthService)
			->where(self::COLUMN_OAUTH_ID, $oauthId)
			->fetch();
	}
}
