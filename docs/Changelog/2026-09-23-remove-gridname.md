# 2026-09-23 — Zrušen duplicitní sloupec `gridName`, gridy berou název z `*Descriptions`

**Co:** Z tabulek `firecms_articles`, `firecms_categories`, `firecms_tags` a `firecms_plugin_dynamicForms`
(`grid_name`) byl odstraněn sloupec `gridName`, a to z migrací i z kódu. Gridy, výpisy a řazení v administraci
berou název (`title`, u tagů `name`) přímo z překladové tabulky `*Descriptions` v jazyce nastaveném v administraci
(persistentní parametr `language` presenteru, tj. záložky jazyků).

**Proč:** `gridName` byla denormalizovaná kopie názvu z výchozího jazyka, kterou model udržoval ručně
(`updateGridName()` při každém uložení překladu). Duplicita se mohla rozejít s daty v `*Descriptions`.

**Dotčené soubory/oblasti:**
- `data/migrations/structures/20161115000000.sql`, `app/Plugins/DynamicForms/data/migrations/20171115000000.sql` — sloupec odstraněn.
- `app/Model/TranslatedTitleTrait/TranslatedTitleTrait.php` (nový) — `getTitleSql()`, `getTitleParams()` a
  `selectTitle($selection, $idColumn, $language)`.
  Používají ho `Articles`, `Categories` a `Tags` (`getTitleColumn()` vrací `title`/`name`).
- `Articles`, `Categories`, `Tags`, `DynamicForms` (modely) — odstraněny `updateGridName()` a jejich volání. Z
  `Categories` byla odstraněna i mrtvá metoda `recalculateLeftRightPositions()`, která obsahovala jen kopii
  téže logiky s nedefinovanými proměnnými (5 položek baseline bylo smazáno).
- Admin gridy: `ArticlesPresenter` (články, kategorie článku, výběr kategorie), `MenusPresenter` (položky menu,
  výběr kategorií ve stromu), `SettingsModule\TagsPresenter`, `DynamicFormsPresenter` (řazení podle `template_name`).
- `CategoriesMenu` (strom kategorií v administraci; data se načítají až v `render()` podle `setLanguage()`),
  `CategoryLinkFormPart` (select s kategoriemi, jazyk z `CategoryFormFactory`),
  `Service\Tag` (`findByName()`, `getRelationTags()`: alias `defaultName` místo `gridName`).
- `CommentsPresenter` — odstraněn nepoužívaný select `category_grid_name`, který navíc joinem násobil řádky gridu.
- `tests/Model/TranslatedTitleTraitTest.phpt` (nový).

**Rozhodnutí a kompromisy:**
- Název je v jazyce zvoleném v administraci (`?language=`). Když chybí nebo neexistuje, bere se výchozí jazyk webu.
  Když položka nemá překlad ani v jednom z nich, vezme se překlad s nejnižším `languageId`. Vždy jde o jeden
  konkrétní řádek s pevným pořadím, nikdy o náhodný řádek z `GROUP BY`.
- Grid tagů, záložka „všechny jazyky“: zrušen `GROUP BY` s `:tagDescriptions.name`, který vracel náhodný
  překlad. Název je ve výchozím jazyce a počet překladů (`language_count`) se počítá poddotazem. Na záložce
  konkrétního jazyka zůstává dosavadní chování: jen tagy přeložené do toho jazyka, řádek z `*Descriptions`
  právě toho jazyka. Sloupec „Tag“ už neodkazuje na starou tabulku `:tag_descriptions.name`, řadí se podle aliasu `title`.
- `Service\Tag` (`defaultName` a poznámka „(default)“ u štítků bez překladu) úmyslně zůstává na výchozím jazyce webu.
- Použit je korelovaný poddotaz místo `JOIN` + `GROUP BY`. JOIN na `*Descriptions` by vracel řádek za každý jazyk
  a `GROUP BY` by změnil počítání řádků v datagridu. Identifikátory v poddotazu jsou v backtickách a jazyk se
  předává jako parametr `?` (viz gotcha níže).
- Sloupec „Category“ v gridu článků ukazuje hlavní kategorii (`isMain DESC`). Dřívější
  `:categoryArticle.category.gridName` dělal LEFT JOIN, takže článek ve více kategoriích byl v gridu vícekrát.
- `MenusPresenter` (položky menu): název se nově escapuje (`Html::el()` místo skládání HTML řetězce s
  `setTemplateEscaping(false)`).
- `Model\Articles::getRelationCategory()` a `Menus::getRelationMenu()` už neřadí podle názvu kategorie (jen
  `isMain DESC`). Grid kategorií článku si řazení podle `title` přidává sám.

**Návaznost:**
- Update `docs/Architecture/*.md`? ne
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ano — gotchas.md, sekce o `id`/FK (poddotazy v Nette Explorer)
- DB migrace potřeba? ne, historické migrace přepsané (DB se resetuje)
