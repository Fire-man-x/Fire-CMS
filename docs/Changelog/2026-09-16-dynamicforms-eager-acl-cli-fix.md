# 2026-09-16 — `DynamicForms` blokoval `bin/console` na prázdné DB (eager ACL build v `initialize()`)

**Co:** `App\Plugins\DynamicForms\DI\Extension::afterCompile()` teď guarduje svoje vkládání do
kontejnerové `initialize()` metody na `%consoleMode%` — v CLI se `ContactFormControl`/`application.
application` eagerly nestaví.

**Proč:** Uživatel nahlásil, že `php bin/console migrations:reset` na prázdné databázi padá na
`SQLSTATE[42S02]: Base table or view not found: 1146 Table 'fire-cms.roles' doesn't exist`. Diagnóza
(čtením vygenerovaného kontejneru, ne jen zdrojového kódu) ukázala, že za to nemůže pořadí migrací, ale
`DynamicForms`: jeho `Extension::afterCompile()` vkládá do `initialize()` (běží nepodmíněně při KAŽDÉM
bootu kontejneru, web i CLI) volání, které eagerly staví `ContactFormControl` → `ContactFormFactory` →
`App\Modules\CommentsModule\Comment` (potřebuje `security.user`) → `App\Security\User` (potřebuje
`authorizator`) → `App\Security\AuthorizatorFactory::create()`, jejíž tělo OKAMŽITĚ volá
`Roles::getListWithName()`. Na prázdné DB tabulka `roles` ještě neexistuje (na to teprve slouží
migrace) — klasický chicken-and-egg. Netýkalo se to jen `migrations:reset`; stejně by spadl i běžný web
request na čerstvé instalaci před prvním nasazením schématu.

**Dotčené soubory/oblasti:**
- `app/Plugins/DynamicForms/DI/Extension.php` — `afterCompile()` teď na začátku vrací, pokud
  `$this->getContainerBuilder()->parameters['consoleMode']` je `true`.

**Rozhodnutí a kompromisy:**
Zvažováno i tvrdší řešení v jádru — obalit `App\Security\AuthorizatorFactory::create()` tak, aby na
chybějící `roles`/`modules` tabulku reagovalo prázdným `Permission` místo pádu (řešilo by to i běžný web
request na neinicializovanou DB, ne jen CLI). Zamítnuto pro tuhle změnu: je to zásah do sdíleného jádra
ACL (`app/Components/Security/AuthorizatorFactory.php`), se širším dopadem na všechny klientské projekty,
zatímco nahlášený problém byl konkrétně o `bin/console` na prázdné DB. Oprava v `DynamicForms` (plugin,
ne jádro) je menší, bezpečnější a přesně cílená — CLI skript stejně nikdy nevykresluje Latte shortcode
"contactForm", takže se nic nefunkčního neztrácí. Pokud se v budoucnu ukáže potřeba i běžný web request
na čerstvě založenou DB (bez proběhlých migrací), bude to samostatné, vědomé rozšíření core
`AuthorizatorFactory`, ne vedlejší efekt týhle opravy.

**Návaznost:**
- Update `docs/AI-Context/gotchas.md` — ano, nová sekce "`DynamicForms` eagerně stavěl ACL při KAŽDÉM
  bootu kontejneru — spadlo to na prázdné DB".
- Update `docs/Architecture/*.md` — ne (jde o opravu chování jednoho pluginu, ne o architektonickou
  změnu).
- DB migrace potřeba? Ne.
- **Pozor:** po týhle opravě je nutné invalidovat zkompilovaný kontejner (`temp/cache/nette.configurator/
  Container_*.php*`), jinak se stará (nefixnutá) verze použije dál beze změny (produkční mód
  nekontroluje mtime configu/kódu při každém requestu). V sandboxu, kde tahle oprava vznikla, jsou tyhle
  soubory vlastněné `www-data` a nejde je smazat z běžného shellu — potřeba smazat mimo něj.
