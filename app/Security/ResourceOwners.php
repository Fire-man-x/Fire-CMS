<?php
declare(strict_types=1);

namespace App\Security;

use App\Model\Database\BaseModel;
use App\Model\Exceptions\RecordNotFoundException;
use Nette\InvalidArgumentException;

/**
 * Mapa zdrojů ACL (názvy z #[Secured], resp. firecms_modules.name) na modely, ze kterých se zjistí autor
 * záznamu (sloupec createdBy). Autor smí se svým záznamem cokoliv (pravidlo v AuthorizatorFactory), stačí
 * se ptát přes isAllowed(new Resource($resource, $ownerId), $privilege).
 *
 * Mapa je v config.neon (služba resourceOwners), plugin přidá vlastní zdroj v config.plugin.neon:
 *	resourceOwners:
 *		setup:
 *			- add('Calendar', @App\Plugins\Calendar\Model\Events)
 */
final class ResourceOwners
{
	/** Sloupec s ID uživatele, který záznam vytvořil */
	public const ColumnOwner = 'createdBy';

	/** @var array<string, BaseModel> */
	private array $models = [];


	/**
	 * @param array<string, BaseModel> $models zdroj ACL => model
	 */
	public function __construct(array $models = [])
	{
		foreach ($models as $resource => $model) {
			$this->add($resource, $model);
		}
	}


	public function add(string $resource, BaseModel $model): void
	{
		$this->models[$resource] = $model;
	}


	/**
	 * Má zdroj záznamy s autorem?
	 */
	public function has(string $resource): bool
	{
		return isset($this->models[$resource]);
	}


	/**
	 * ID autora záznamu, null když autor není (createdBy NULL)
	 * @throws InvalidArgumentException zdroj nemá v mapě model
	 * @throws RecordNotFoundException záznam neexistuje
	 */
	public function getOwnerId(string $resource, int $id): ?int
	{
		$row = $this->getModel($resource)->findById($id)->select(self::ColumnOwner)->fetch();
		if (!$row) {
			throw new RecordNotFoundException("Record '$id' of resource '$resource' not found.");
		}

		$ownerId = $row[self::ColumnOwner];
		if ($ownerId === null) {
			return null;
		} elseif (!is_numeric($ownerId)) {
			throw new \UnexpectedValueException("Column '" . self::ColumnOwner . "' of resource '$resource' is not a user ID.");
		}

		return (int) $ownerId;
	}


	public function getModel(string $resource): BaseModel
	{
		return $this->models[$resource]
			?? throw new InvalidArgumentException("Resource '$resource' has no model with owner in ResourceOwners (config.neon, service resourceOwners).");
	}

}
