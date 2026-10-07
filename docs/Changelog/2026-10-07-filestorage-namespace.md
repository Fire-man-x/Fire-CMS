# 2026-10-07 — Správce souborů přesunut do `app/FileStorage`, DI rozšíření `fileStorage:`

**Co:** Kód správce souborů (úložiště, náhledy, Latte makra, `TPresenter`, příkaz `files:migrate`) se přesunul
z `app/Components/FileManager/` do `app/FileStorage/` a namespace z `App\Components\FileManager` na
`App\FileStorage`. DI rozšíření se v neonu jmenuje `fileStorage:` místo `fileManager:`. Chování se nemění.

**Proč:** Nejde o UI komponentu se šablonou, ale o samostatnou část systému. V `app/Components/` byla
zavádějící.

**Dotčené soubory/oblasti:**
- `app/FileStorage/**` — přesunuté soubory, jen namespace a texty s názvem klíče (`fileStorage: thumbnails:`
  v chybové hlášce nepovoleného náhledu, `fileStorage: storages:` v hláškách konfigurace)
- `app/FileStorage/Macro/Nodes/*` — vygenerovaný kód šablon volá `App\FileStorage\Request\ImageRequest`
- `app/config/config.neon` — `fileStorage: App\FileStorage\DI\Extension` a sekce `fileStorage:`
- `theme/config/config.local.neon.dist` — sekce `fileStorage:`
- `app/AdminModule/templates/FilesManager/default.latte`, `app/FrontModule/templates/Pages/detail.latte` —
  názvy tříd v `instanceof` / `{varType}`
- `use` v presenterech, komponentách a modelech, které správce souborů používají
- `tests/Components/FileManager/` → `tests/FileStorage/` (namespace `Tests\FileStorage`)
- `app/config/phpstan-baseline.neon` — cesty a názvy tříd

**Rozhodnutí a kompromisy:**
Klíč `fileStorage:` je konzistentnější s tím, co sekce nastavuje (úložiště a náhledy). Cenou je změna
konfigurace v každém projektu včetně neverzovaného `config.local.neon` na serveru. Alias pro starý klíč
nevznikl: Nette spojuje název sekce s názvem rozšíření a dvě rozšíření nad stejnými službami by se
přetahovala o definice. Starý klíč proto shodí start aplikace hned (`Found section 'fileManager' in
configuration, but corresponding extension is missing`), ne potichu.

Služba `FileManager` a cache náhledů (`FileManager.thumbnails.<úložiště>.<verze>`) se nepřejmenovávají.
Náhledy se proto nemusí generovat znovu.

**Co musí udělat projekt při mergi jádra:**
1. Kód pluginů a šablon v projektu (`theme/`, vlastní `app/Plugins/*`):
   `grep -rlF 'App\Components\FileManager' theme app/Plugins | xargs -r sed -i 's/App\\Components\\FileManager/App\\FileStorage/g'`.
   Hledejte i relativní zápisy uvnitř namespace `App\Components` (např. `FileManager\FileManager`).
   Pozor na šablony: `instanceof` s neexistující třídou nehlásí chybu, jen vrací `false`.
2. Neon: sekce `fileManager:` → `fileStorage:` v `theme/config/theme.neon`, v `config.plugin.neon`
   klientských pluginů a v `theme/config/config.local.neon`. Odkazy na služby
   `@fileManager.filesystem.<název>` → `@fileStorage.filesystem.<název>`.
3. **`config.local.neon` na serveru upravte ve stejném okamžiku jako nasazení** (je mimo git, deploy ho
   nezmění). S neupraveným souborem web po deployi nenastartuje.
4. Smažte `temp/cache` (zkompilovaný DI kontejner a šablony).

**Návaznost:**
- Update `docs/Architecture/*.md`? ano — `components.md`, `configuration.md`, `file-storage.md`
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ano — `gotchas.md` (názvy tříd, klíč, cesta testu)
- DB migrace potřeba? ne
