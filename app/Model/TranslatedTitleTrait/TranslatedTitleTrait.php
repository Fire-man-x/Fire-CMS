<?php
declare(strict_types=1);

namespace App\Model\TranslatedTitleTrait;

use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

/**
 * Název položky (title/name) načtený z překladové tabulky "*Descriptions" pro zvolený jazyk (typicky jazyk
 * nastavený v administraci, parametr "language"). Nahrazuje dřívější duplicitní sloupec "gridName" na hlavní
 * tabulce (gridy, výpisy, řazení podle názvu).
 *
 * Vybírá se vždy právě jeden konkrétní překlad (poddotaz s LIMIT 1 a pevným pořadím), žádné GROUP BY
 * ani náhodný řádek. Pořadí: zvolený jazyk, pak výchozí jazyk webu. Pokud položka nemá ani jeden z nich,
 * vezme se překlad s nejnižším "languageId", aby grid neukazoval prázdný název.
 *
 * Třída, která trait používá, musí implementovat "App\Model\Translatable" (a mít konstantu
 * "TRANSLATION_TABLE_NAME") a vlastnost "LanguageService $languages".
 */
trait TranslatedTitleTrait
{
	/**
	 * Sloupec překladové tabulky s názvem položky
	 */
	abstract protected function getTitleColumn(): string;


	/**
	 * Hodnoty pro placeholdery "?" v getTitleSql() - [zvolený jazyk, výchozí jazyk webu]
	 * @param string|null $language Jazyk administrace; null nebo neexistující jazyk = výchozí jazyk webu
	 * @return array{string, string}
	 */
	public function getTitleParams(?string $language = null): array
	{
		$defaultLanguage = $this->languages->getDefaultLanguage();
		$language = $language !== null && $this->languages->existLanguage($language) ? $language : $defaultLanguage;
		return [$language, $defaultLanguage];
	}


	/**
	 * SQL poddotaz na název položky. Obsahuje právě dva placeholdery "?", za které se dosazuje
	 * getTitleParams(). Je to ručně složený korelovaný poddotaz (Selection to neumí: alias() je jen pro
	 * navázané tabulky, ne pro hlavní, a where() by $idColumn dosadil jako hodnotu, ne sloupec vnějšího dotazu).
	 * Identifikátory obaluje delimite() podle databáze (MariaDB `x`, PostgreSQL "x") - obalené je Nette Explorer
	 * nevykládá jako odkazy na navázané tabulky (tabulka.sloupec) a znovu je neobaluje.
	 * @param string $idColumn ID položky ve vnějším dotazu: "tabulka.sloupec" (obalí se samo, např.
	 *     "firecms_articles.id"), nebo hotový SQL výraz s obalenými identifikátory (např. poddotaz)
	 */
	public function getTitleSql(string $idColumn): string
	{
		$translation = $this->delimite('translation');
		$languageId = $translation . '.' . $this->delimite('languageId');
		if (preg_match('~^\w+(\.\w+)?$~', $idColumn)) {
			$idColumn = $this->delimite($idColumn);
		}

		return '(SELECT ' . $translation . '.' . $this->delimite($this->getTitleColumn())
			. ' FROM ' . $this->delimite(self::TRANSLATION_TABLE_NAME) . ' ' . $translation
			. ' WHERE ' . $translation . '.' . $this->delimite($this->getForeignKeyColumn()) . ' = ' . $idColumn
			. ' ORDER BY ' . $languageId . ' = ? DESC, ' . $languageId . ' = ? DESC, ' . $languageId
			. ' LIMIT 1)';
	}


	/**
	 * Přidá do výběru název položky ve zvoleném jazyce jako sloupec $alias
	 * @param Selection<ActiveRow> $selection
	 * @param string $idColumn ID položky ve vnějším dotazu, např. "firecms_articles.id" (viz getTitleSql())
	 * @param string|null $language Jazyk administrace; null = výchozí jazyk webu
	 * @return Selection<ActiveRow>
	 */
	public function selectTitle(Selection $selection, string $idColumn, ?string $language = null, string $alias = 'title'): Selection
	{
		// alias obalený, jinak by ho PostgreSQL převedl na malá písmena (categoryTitle -> categorytitle)
		return $selection->select($this->getTitleSql($idColumn) . ' AS ' . $this->delimite($alias), ...$this->getTitleParams($language));
	}

}
