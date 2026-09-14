<?php
declare(strict_types = 1);

namespace App\FrontModule\Components\DatagridExtended;

use Nette\Database\Explorer;
use Nette\Localization\Translator;

/**
 * Translator for extensions of Nextras\Datagrid
 *
 * @author     Vaclav Koterec
 */

class DatagridExtendedTranslator implements Translator
{
	/**
	 * Translates the given string.
	 */
	public function translate(string|\Stringable $message, mixed ...$parameters): string|\Stringable
	{

		$items = array(
			"Edit" => "Upravit",
			"No items" => "Žádná položka",
			"First" => "První",
			"Previous" => "Předchozí",
			"Next" => "Další",
			"Last" => "Poslední",
		);

		if(isset($items[$message])) {
			return $items[$message];
		} else {
			return $message;
		}
	}

}
