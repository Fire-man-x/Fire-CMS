<?php declare(strict_types = 1);

namespace App\AdminModule\Forms;

use Nette\Utils\ArrayHash;

final class AdminFormValues
{

	public int|string|null $editId;

	public ArrayHash $data;

	public ArrayHash $buttons;

	/**
	 * Vlastnost má typ `array`, proto Nette vrací i vnořené kontejnery jazyků jako pole (ne ArrayHash)
	 * @var array<string, array<string, mixed>> language => values
	 */
	public array $locales;

	public static function create(): static
	{
		return new static();
	}

}
