<?php
declare(strict_types=1);

namespace App\Model;

use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

/**
 * Model, jehož data mají překladovou tabulku `*Descriptions` (jeden řádek na položku a jazyk, odkazující na
 * hlavní tabulku přes getForeignKeyColumn(), např. `firecms_articleDescriptions.articleId` → `firecms_articles.id`).
 *
 * Třída navíc definuje konstantu `TRANSLATION_TABLE_NAME` s názvem překladové tabulky. Název položky
 * pro gridy a výpisy v jazyce administrace poskytuje `TranslatedTitleTrait`.
 */
interface Translatable
{

	/**
	 * Překladová tabulka `*Descriptions`
	 * @return Selection<ActiveRow>
	 */
	public function getTranslationTable(): Selection;


	/**
	 * Sloupec, kterým překladová tabulka odkazuje na primární klíč hlavní tabulky
	 */
	public function getForeignKeyColumn(): string;

}
