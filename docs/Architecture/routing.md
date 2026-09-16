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

1. Detekuje volitelný `<locale>/` prefix na začátku URL (2 znaky, pak `existLanguage()` check).
2. Zbytek URL hledá v `App\Modules\UrlModule\UrlManager::getUrlInfoByUrl()` — databázová tabulka
   spravovaná přes `UrlModule` (viz `orm.md` obsahový model).
3. Nenajde-li se, zkusí `getRedirectionInfoByUrl()` (přesměrování, s "last_usage_date" auditem).
4. Podle `type` (`default`/`user`/`article`/`category`) namapuje na `Front:Default` / `Users` /
  `Front:Articles` / `Front:Categories`, `action` = `detail` pro article/category, jinak `default`.

Presenter jméno na maskách (`<presenter>`) musí být **malými písmeny, případně s pomlčkami** — Nette
`Route`/`Router` defaultně vyžaduje `[a-z][a-z0-9.-]*` a teprve `path2presenter`/`presenter2path` filtr
převádí na PascalCase (`dynamic-forms` ↔ `DynamicForms`). URL jako `/administrace/DynamicForms/default`
(velké písmeno přímo v cestě) **nikdy nematchne** a spadne jako "no route" — viz `gotchas.md`.

## Lokalizace

Locale se řeší jen uvnitř `CustomRouter`/`FrontRouter` fallback masky (`[<locale>/]...`), ne na úrovni
domény/vhostu — víc-doménové/víc-tenantní routování v tomto repozitáři není.
