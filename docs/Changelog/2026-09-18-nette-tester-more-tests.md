# 2026-09-18 — Rozšíření testů: `CustomRouter`, `App\Security\Role`, zbytek `UrlManager`

**Co:** Navazuje na [2026-09-18-nette-tester-setup.md](2026-09-18-nette-tester-setup.md). Přidány:
- `tests/Security/RoleTest.phpt` — čistý unit test `App\Security\Role` (bez DB).
- `tests/Router/CustomRouterTest.phpt` — integrační test `App\Router\CustomRouter::match()` nad in-memory
  SQLite (přímý zásah, jazykový prefix, doménová redirekce, jazyk podle domény requestu).
- `tests/Modules/UrlModule/UrlManagerLookups.phpt` — zbylé čtecí metody `UrlManager`.
- `tests/Helpers/RequestFactory.php` — staví `Nette\Http\Request` pro testy routerů.
- `composer.json` → `autoload.classmap: ["app/Components/Security"]`, aby Composer PSR-4 autoloader (na
  kterém stojí `tests/bootstrap.php` i PHPStan) vůbec našel `App\Security\*` třídy fyzicky ležící mimo
  odpovídající PSR-4 cestu.

**Proč:** Uživatel chtěl po ověření proveditelnosti (viz předchozí changelog) rozšířit pokrytí na další
kód aplikace, ne zůstat jen u jednoho pilotního testu.

**Dotčené soubory/oblasti:** viz výše + `docs/AI-Context/gotchas.md` (nová sekce "Rozšíření (2026-09-18)").

**Rozhodnutí a kompromisy:**
- `CustomRouter` byl vybrán jako priorita, protože jde o business-kritickou routovací logiku jádra, ve
  které byl 2026-09-17 nedávno opravený reálný bug (nezapisovaný presenter) — přesně typ kódu, který si
  regresní test zaslouží nejvíc.
- Při psaní testu na `App\Security\Role` vyšlo najevo, že jednoargumentová varianta konstruktoru
  (`Nette\Security\User`/`App\Security\Identity`) je mrtvý/rozbitý kód — `App\Security\Identity` a
  `App\Security\Exception` v repu vůbec neexistují, takže by při použití spadla na fatální chybu místo
  očekávané výjimky. Ověřeno (`grep -rn "new Role("`), že se v celém repu reálně používá jen dvouargumentová
  varianta. **Neopravováno** — je to změna chování jádra mimo zadání "doplnit testy", ne úklid, který by měl
  vzniknout jako vedlejší produkt psaní testů. Doporučuji rozhodnout samostatně, jestli tu větev
  opravit/smazat, nebo nechat být coby netestovaný/nepoužívaný kód.
- `Nette\Http\Request`/`UrlScript` pro testy routerů se staví ručně (bez DI kontejneru) — stejný přístup
  jako u `SqliteDatabase`, konzistentní s tím, jak byla založena testovací infrastruktura.

**Návaznost:**
- Update `docs/Architecture/*.md`? Ne (nemění se popsaná architektura, jen přibylo pokrytí).
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? Ano — `gotchas.md`.
- DB migrace potřeba? Ne.
