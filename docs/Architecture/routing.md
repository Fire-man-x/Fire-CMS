# Routing

Tři poskytovatelé routerů v `app/Router/`, automaticky registrovaní přes Nette DI extension `search`
(cokoliv implementující `App\Router\RouterProvider` nalezené pod `%appDir%/Router`, viz `configuration.md`),
seřazení sestupně podle `router.priorities` v `config.neon` (vyšší číslo = zkouší se dřív):

| Router | Priorita | Maska | Modul |
|---|---|---|---|
| `AdminRouter` | 100 | `/administrace/<presenter>/<action>[/<id>]` | `Admin` |
| `FileRouter` | -50 | `files/<imagePath .+>` (generování náhledů) | `Front` |
| `FrontRouter` | -100 | deleguje na `CustomRouter`, fallback `[<locale>/]<presenter>/<action>[/<id>]` | `Front` |

Registrace a řazení (včetně tagu `router: [priority: N]` jako alternativy ke config sekci) řeší
`App\Router\DI\RouterProviderExtension` — vlastní CompilerExtension, ne `search` samotný (ten jen NAJDE
třídy implementující `RouterProvider`, seřazení a přidání do `RouteList` dělá až tahle extension v
`beforeCompile()`).

## `CustomRouter` — DB-podložená hezká URL

`FrontRouter::create()` do svého `RouteList` přidá `CustomRouter` (implementuje `Nette\Routing\Router`
přímo, ne přes `Route` masku) jako PRVNÍ položku — zkouší se před fallback maskou. `CustomRouter::match()`:

1. Zjistí locale — nejdřív podle domény (`App\Service\DomainService::getLanguageByDomain()`, viz
   Lokalizace a domény níže), a jen když doména žádnému jazyku nepatří, zkusí volitelný `<locale>/` prefix
   na začátku URL (2 znaky, pak `existLanguage()` check).
2. Zbytek URL hledá v `App\Modules\UrlModule\UrlManager::getUrlInfoByUrl()` — databázová tabulka
   spravovaná přes `UrlModule` (viz `orm.md` obsahový model).
3. Nenajde-li se, zkusí `getRedirectionInfoByUrl()` (přesměrování, s "last_usage_date" auditem).
4. Podle `type` (`default`/`user`/`article`/`category`) namapuje na `Front:Default` / `Users` /
  `Front:Articles` / `Front:Categories`, `action` = `detail` pro article/category, jinak `default`.

Presenter jméno na maskách (`<presenter>`) musí být **malými písmeny, případně s pomlčkami** — Nette
`Route`/`Router` defaultně vyžaduje `[a-z][a-z0-9.-]*` a teprve `path2presenter`/`presenter2path` filtr
převádí na PascalCase (`dynamic-forms` ↔ `DynamicForms`). URL jako `/administrace/DynamicForms/default`
(velké písmeno přímo v cestě) **nikdy nematchne** a spadne jako "no route" — viz `gotchas.md`.

## Lokalizace a domény

Každý jazyk (`firecms_languages`) může mít 0-N domén v `firecms_domains` (`App\Model\Domains`,
`App\Service\DomainService`), spravovaných v Administrace > Nastavení > Domény
(`App\AdminModule\SettingsModule\Presenters\DomainsPresenter`). Řešení locale v `CustomRouter`:

- **Doména přiřazená jazyku** (Host hlavička requestu odpovídá řádku v `firecms_domains`): locale je dané
  doménou, žádný `/xx/` prefix se neparsuje — celá cesta za doménou je obsahová URL v tom jazyce.
- **Doména bez přiřazení** (typicky hlavní/vývojová doména): funguje to jako doteď — `/xx/...` prefix pro
  nedefault jazyk, žádný prefix = default jazyk. Prefix se ale řeší JEN pro jazyky, které vlastní doménu
  nemají — pro jazyk, co už doménu má, `CustomRouter` vrátí `Front:Redirect` (301 na kanonickou doménu z
  `App\Service\DomainService::getDomainForLanguage()`, stejná cesta), takže staré prefixové odkazy
  (např. z Google) přestanou fungovat duplicitně vedle nové domény, ale zůstanou funkční jako redirect.
- Generování odchozích URL (`CustomRouter::constructUrl()`) totéž zrcadlí: pro jazyk s doménou vygeneruje
  absolutní URL na tu doménu (bez `/xx/` prefixu), jinak jako doteď prefix na aktuální doméně.
- Jeden jazyk se dvěma+ doménami: `firecms_domains.default` určuje, která je kanonická (použije se pro
  generované odkazy a pro redirect ze staré prefixové URL) — nastavuje se přes "Set as default" v gridu,
  je to per-jazyk (na rozdíl od `firecms_languages.default`, což je globální jeden řádek napříč celou
  tabulkou).
- Bez jakékoliv konfigurace v `firecms_domains` (typický stav dnešních projektů) je chování 1:1 stejné
  jako předtím — funkce je čistě přídavná/opt-in.

Admin přístup k Doménám je gated stejně hrubě jako Languages/Users/Roles/Tags/Urls — jen přes resource
`Settings` v menu (`@layout.latte`), žádný vlastní `#[Secured]`/`#[Resource]` atribut na presenteru.
