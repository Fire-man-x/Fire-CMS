# 2026-09-17 — Jazykové mutace na vlastních doménách

**Co:** Jazyk (`firecms_languages`) může mít vlastní doménu/domény (nová tabulka `firecms_domains`,
spravovaná v Administraci > Nastavení > Domény). Request na přiřazenou doménu je automaticky v tom
jazyce bez `/xx/` prefixu; starý prefixový odkaz na jazyk, co už doménu má, dostane 301 na kanonickou
doménu. Bez vyplněné domény se chování nemění — funkce je čistě přídavná.

**Proč:** Požadavek, aby šlo provozovat např. `example.cz` (cs) a `example.en` (en) jako dvě domény
jednoho projektu místo `example.cz` + `example.cz/en/`.

**Dotčené soubory/oblasti:**
- `data/migrations/structures/20260917103000.sql` — nová tabulka `firecms_domains`
  (`domain_id`, `language_id` FK, `domain`, `active`, `default`, `position`).
- `app/Model/Domains.php`, `app/Service/DomainService.php` — model + cache/lookup služba
  (doména → jazyk, jazyk → kanonická doména).
- `app/Router/CustomRouter.php` — `match()`: locale nejdřív podle domény (`DomainService`), pak fallback
  na `/xx/` prefix (jen pro jazyky bez vlastní domény); prefix na jazyk s doménou → `Front:Redirect`
  (301). `constructUrl()`: pro jazyk s doménou generuje absolutní URL na tu doménu.
- `app/Router/FrontRouter.php` — protažení `DomainService` do `CustomRouter`.
- `app/FrontModule/Presenters/RedirectPresenter.php` — nový, jen `redirectUrl($url, 301)`.
- `app/Forms/DomainFormFactory.php`, `app/AdminModule/SettingsModule/Presenters/DomainsPresenter.php`
  (+ `templates/Domains/default.latte`) — admin CRUD nad doménami, stejný vzor jako `LanguagesPresenter`.
- `app/AdminModule/templates/@layout.latte` — odkaz "Domains" v menu Nastavení.
- `app/config/config.neon` — registrace `App\Model\Domains`, `App\Forms\DomainFormFactory`,
  `domains: App\Service\DomainService`.

**Rozhodnutí a kompromisy:**
- Domény jsou ve VLASTNÍ tabulce s vlastním `domain_id` (ne sloupec přímo na `firecms_languages`) — jazyk
  tak může mít 0-N domén (např. do budoucna víc TLD pro stejný jazyk); `default` per jazyk určuje, která
  je kanonická pro generování odkazů a pro redirect.
- Konfigurace domén je v DB/administraci (self-service, bez nasazení), ne v `theme.neon` — konzistentní
  s tím, kde se dnes spravuje vše kolem jazyků, a klient/PM si doménu může změnit sám.
- Starý prefixový odkaz na jazyk s doménou → 301 (zachová SEO hodnotu/zpětné odkazy), ne 404 a ne "necháme
  fungovat obojí" (duplicitní obsah).
- Mimochodem opraveno: `CustomRouter::match()` nikdy nezapisoval spočtený `$presenter` do `$params` (mrtvá
  proměnná) — bez toho by match() nad DB-podloženou hezkou URL (article/category/homepage) nikdy nevrátil
  presenter a Nette by request nedokázalo dispatchnout. Netestováno v produkci (žádná testovací sada),
  ale je to nutná oprava přímo v místě, které tahle změna stejně přepisovala.

**Návaznost:**
- Update `docs/Architecture/routing.md` — ano, sekce "Lokalizace a domény".
- Update `docs/AI-Context/gotchas.md` — ne (nejde o gotchu/past, jde o novou funkci zdokumentovanou v
  `routing.md`).
- DB migrace potřeba — ano, `data/migrations/structures/20260917103000.sql` (spustí se přes
  `bin/console migrations:continue` jako ostatní).

**Manuální krok mimo kód:** aby doména z `firecms_domains` fakticky fungovala, musí na ni ukazovat
Apache vhost (na stejný `www/` document root jako hlavní doména projektu) — to je mimo tento repozitář.
