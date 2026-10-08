<?php
declare(strict_types=1);

namespace App\FileStorage\Storages;

/**
 * Úložiště z `fileStorage: <název>:` podle názvu - pro generátor náhledů (Front:Files:thumbnail), který název
 * úložiště dostane z URL. Jiná úložiště, než jsou v neonu, generátor neobslouží.
 */
final class StorageRegistry
{
	/**
	 * @param array<string, FlysystemStorage> $storages název z neonu => úložiště
	 */
	public function __construct(
		private readonly array $storages,
	)
	{
	}


	public function find(string $name): ?FlysystemStorage
	{
		return $this->storages[$name] ?? null;
	}


	/**
	 * @throws \InvalidArgumentException úložiště není v neonu
	 */
	public function get(string $name): FlysystemStorage
	{
		return $this->find($name)
			?? throw new \InvalidArgumentException(sprintf("Úložiště '%s' není v neonu (fileStorage: <název>:).", $name));
	}


	/**
	 * @return array<string, FlysystemStorage>
	 */
	public function getAll(): array
	{
		return $this->storages;
	}
}
