# 2026-09-23 — Plugin DynamicForms převeden na camelCase sloupce a PK `id`

**Co:** Tabulky pluginu `app/Plugins/DynamicForms` (`firecms_plugin_dynamicForms`,
`firecms_plugin_dynamicFormDescriptions`, `firecms_plugin_dynamicFormSendedValues`) mají stejnou konvenci jako
jádro: camelCase sloupce, `AUTO_INCREMENT` PK `id` a FK `dynamicFormId`. Kód pluginu je převedený a model
`DynamicForms` implementuje `App\Model\Translatable`.

**Proč:** Při převodu jádra zůstal plugin v `app/Plugins` na starém pojmenování (`dynamic_form_id`,
`template_name`, …). Je ale součástí repozitáře jádra a jde do všech projektů, proto má mít stejnou konvenci.

**Dotčené soubory/oblasti:**
- `app/Plugins/DynamicForms/data/migrations/20171115000000.sql`:
  - sloupce, indexy a constrainty (`dynamicFormDescriptions_ibfk_3` → `firecms_plugin_dynamicForms (id)`);
  - `dynamic_form_sended_value_id` → `id`.
- `Model/DynamicForms.php`:
  - `setForeignKeyColumn('dynamicFormId')` (PK je výchozí `id`);
  - dotazy do překladové tabulky používají `getForeignKeyColumn()`;
  - `implements Translatable`.
- `Forms/*`, `Components/ContactFormControl.php`, `AdminModule/DynamicFormsPresenter.php`:
  - `templateName`, `languageId`, `createdBy`, `whereToSend`, `submitMessage`, `afterSendInformations`,
    `itemsSpecifications`;
  - řádek hlavní tabulky `$dynamicForm->id`.

**Rozhodnutí a kompromisy:**
- Klíč `send_to` uvnitř serializovaného `afterSendInformations` zůstává. Je to formát uložených dat, ne sloupec.
- Pole formuláře `after_send_informations_email` (jen ve formuláři, neukládá se) přejmenováno pro konzistenci
  na `afterSendInformationsEmail`.
- Pluginy `Sliders`, `Stalker` a `Statistics` už převedené byly: tabulky jsou v core migraci a inline
  `CREATE TABLE` v `Instalation.php`. `Statistics/Instalation.php` má starší chybu mimo rozsah: `KEY` a
  `FOREIGN KEY` na sloupec `createdBy`, který tabulka nemá.

**Ověření v administraci (po resetu DB):** Proklikáno: grid formulářů, vytvoření, editace (načtení i uložení,
včetně `send_to`), detail s položkami, přidání, editace a odebrání položky, smazání formuláře (kaskáda na
`*Descriptions`). Dotazy kontaktního formuláře (`findByTemplateName`, `getItems`, `getItemTranslation`,
`findTranslationBy`) jsou ověřené proti MariaDB. Komponentu `contactFormControl` teď žádná šablona nepoužívá.
Cestou opraveny starší chyby:
- `createdBy` se při vytvoření formuláře nenastavoval (ukládala se `0`), přitom se podle něj hlídá oprávnění.
- `testIfItemNameExist(..., string $notIn)` padal u nové položky (`null`), teď `?string $notIn = null`.
- `getItemTranslation()` vracel pro prázdné `items` hodnotu `false` a zápis do ní hlásil deprecation. Teď vrací
  prázdné pole.
- `removeItemTranslation()` ukládal do `items` celý řádek překladu místo pole položek.
- `DynamicFormItemFormFactory`: `editId` položky je její název, proto se předává jako `(string)`.
  `BaseFormFactory::setEditId()` číselné řetězce převádí na `int`.
- Neřešeno: `detail` bez ID (`$id` není inicializované). Odkaz na něj v administraci není, nový formulář se
  zakládá v modálním okně na seznamu.

**Návaznost:**
- Update `docs/Architecture/*.md`? ne
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ne
- DB migrace potřeba? ne, historická migrace pluginu přepsána. Tabulky DynamicForms je potřeba založit znovu
  (reset DB), protože `nextras/migrations` hlídá checksum už spuštěné migrace.
