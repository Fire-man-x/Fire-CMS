<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Model\Database\BaseModel;
use App\Model\Exceptions\RecordNotFoundException;
use App\Security\ResourceOwners;
use Nette\Database\Explorer;
use Nette\InvalidArgumentException;
use Tester\Assert;
use Tester\TestCase;
use Tests\Helpers\SqliteDatabase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Mapa zdrojů ACL na modely (služba resourceOwners v config.neon): autor záznamu ze sloupce createdBy.
 */
final class ResourceOwnersTest extends TestCase
{
	private ResourceOwners $owners;


	protected function setUp(): void
	{
		$db = SqliteDatabase::create([
			'CREATE TABLE firecms_articles (id INTEGER PRIMARY KEY AUTOINCREMENT, createdBy INTEGER NULL, active INTEGER NOT NULL DEFAULT 1)',
		]);
		$db->query('INSERT INTO firecms_articles (id, createdBy) VALUES (1, 7), (2, NULL)');

		$articles = new class ($db) extends BaseModel {
			public function __construct(Explorer $database)
			{
				parent::__construct($database);
				$this->setTableName('firecms_articles');
			}
		};

		$this->owners = new ResourceOwners(['Articles' => $articles]);
	}


	public function testOwnerOfRecord(): void
	{
		Assert::same(7, $this->owners->getOwnerId('Articles', 1));
	}


	public function testRecordWithoutOwner(): void
	{
		Assert::null($this->owners->getOwnerId('Articles', 2));
	}


	public function testMissingRecord(): void
	{
		Assert::exception(fn() => $this->owners->getOwnerId('Articles', 3), RecordNotFoundException::class);
	}


	public function testResourceWithoutModel(): void
	{
		Assert::false($this->owners->has('Files'));
		Assert::exception(fn() => $this->owners->getOwnerId('Files', 1), InvalidArgumentException::class);
	}


	public function testAddFromPlugin(): void
	{
		$this->owners->add('Calendar', $this->owners->getModel('Articles'));

		Assert::true($this->owners->has('Calendar'));
		Assert::same(7, $this->owners->getOwnerId('Calendar', 1));
	}
}

(new ResourceOwnersTest())->run();
