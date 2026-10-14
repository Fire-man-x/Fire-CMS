# 2026-10-14 — Formuláře administrace přesunuté z `app/Forms` na `AdminFormFactory`

**Co:** Formuláře článku, kategorie, stránky, menu, položky menu, mety a hodnot met jsou v `app/AdminModule/Forms/`
(namespace `App\AdminModule\Forms`) a dědí z `AdminFormFactory` stejně jako `DomainFormFactory`. Totéž platí pro
všechny ostatní formuláře v `app/AdminModule/Forms/` (druhá část níže). Pole jsou v
kontejnerech `data` (hodnoty řádku), `locales[jazyk]` (překlad) a `buttons`, `create()` vrací `AdminForm` a handlery
mají typy `(AdminForm $form, AdminFormValues $values): void`.

**Proč:** Sjednocení formulářů administrace na jeden formát (`AdminForm` + `AdminFormValues`) a správné typy místo
netypovaných `$values`/`Form`.

**Dotčené soubory/oblasti:**
- `app/AdminModule/Forms/{Article,Category,Page,Menu,MenuItem,Meta,MetaValue}FormFactory.php` — `extends
  AdminFormFactory`, pole v `$form->data`, překlad v `$form->locales->addContainer($language)`, tlačítka v
  `$form->buttons`, constructor injection přes promoted `readonly` vlastnosti. Prochází `composer stan9`.
- `ArticleFormFactory`, `CategoryFormFactory` — `TagInput` se vytváří přímo (`addComponent()`), ne přes
  `addTagInput()`. Parametr `$revision` je `int|string|null` (z URL přichází řetězec) a přetypuje se na `int`.
  Dřív se netypovaný řetězec předával do `findById(int)` a se `strict_types` padal na `TypeError`.
- `MenuFormFactory`, `MenuItemFormFactory` — nadpisy menu a popisky položek po jazycích jsou
  v `locales[languageId][title]` / `locales[languageId][label]` (dřív kontejnery `titles` / `labels`). V modalu
  položky menu jsou proto popisky až za „Open in new window“, před tlačítkem.
- `app/AdminModule/Forms/CategorySubtype/` — `ICategoryFormType` pracuje s `AdminForm`/`AdminFormValues`,
  `createFormPart()` a `onSuccessFormPart()` dostávají jazyk (překlad je v `locales[$language]`).
- `app/AdminModule/templates/{Articles/detail,Categories/default,Pages/detail}.latte` — jména polí přes kontejnery
  (`{input data-active:}`, `{input buttons-send}`, `$form['locales'][$actualLanguage][...]`). Articles a Categories mají
  `{varType}`.
- `app/AdminModule/Presenters/MenusPresenter.php` — `createComponentMenuItemForm(): AdminForm`
  (`setItemDefaults()` bere `AdminForm`).
- `app/AdminModule/Forms/AdminForm.php`, `AdminFormValues.php` — oprava anotací: `$locales` je `Container`,
  `AdminFormValues::$locales` je `array<string, array<string, mixed>>` (Nette vrací pole).
- `app/config/phpstan-baseline.neon` — odstraněné záznamy `addTagInput()` pro Article/Category.
- Druhá část: na `AdminFormFactory` převedeny i zbylé formuláře v `app/AdminModule/Forms/`: `Tag`,
  `Slider`, `SliderItem`, `Setting`, `FilesManagerUpload`, `DynamicForm` a `DynamicFormItem`. `SectionFormFactory`
  (už dědil z `AdminFormFactory`, ale pole měl přímo na formuláři) má pole v `data`/`locales`. `Role`, `Language`
  a `User` mají návratové typy a typované přístupy k polím. `Role` při duplicitním názvu volal `$form['name']`,
  které po přesunu do `data` neexistuje. `User::create()` volá `parent::create()` místo `new AdminForm($editId)`.
  - `SettingFormFactory` — globální hodnoty v `data` (dřív kontejner `global`), jazyky v `locales[jazyk]`. Skupiny
    formuláře (sekce „Global settings“ a „Language: …“) zůstávají, kontejnerům jazyků se nastavuje skupina ručně.
  - `TagFormFactory` — názvy po jazycích v `locales[jazyk][name]`.
  - `DynamicFormFormFactory`, `DynamicFormItemFormFactory` — překlady v `locales[jazyk]` (dřív `translation[jazyk]`).
  - `FilesManagerUploadFormFactory` — upload `files` zůstává přímo na formuláři (`setOmitted()`), jinak by ho
    knihovna jquery-fileupload nenašla (hledá podle plochého HTML jména). Tlačítko je v `buttons`.
  - Šablony `Sections/detail.latte` a `FilesManager/default.latte` (`$form['buttons']['send']`, přidané `{varType}`).
  - `app/config/phpstan-baseline.neon` — odstraněné záznamy chyb, které převod odstranil.

**Rozhodnutí a kompromisy:**
Překlady jsou v `locales[jazyk]`, ne v `data[translation]`. Takové rozdělení počítá `AdminForm::setDefaults()`
(řádek `*Descriptions` pro jazyk). Hodnoty v `locales` jsou pole, protože `AdminFormValues::$locales` má typ
`array`.

**Návaznost:**
- Update `docs/Architecture/*.md`? ano, `orm.md` (cesta k `CategorySubtype`)
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ano, oba (vzor form factory, jména polí v šablonách,
  upload mimo `data`, skupiny u kontejnerů jazyků)
- DB migrace potřeba? ne
