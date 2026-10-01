<?php declare(strict_types = 1);

namespace App\AdminModule\Forms;

use Nette\Utils\ArrayHash;

final class AdminFormValues
{

	public int|string|null $editId;

	public ArrayHash $data;

	public ArrayHash $buttons;

	/** @var array<string,ArrayHash> language => values */
	public array $locales;

	public static function create(): static
	{
		return new static();
	}

}
