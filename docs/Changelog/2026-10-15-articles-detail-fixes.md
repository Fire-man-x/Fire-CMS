# 2026-10-15 — Detail článku: kategorie, pole URL, přepínač aktivity

**Co:** Tři opravy administrace článků nalezené při testu oprávnění autora.

**Proč:**
1. Přepínač Aktivní/Neaktivní v gridu byl i u článků, které uživatel nesmí upravit. Ruční kontrola v
   `ArticlesPresenter::handleActivate()` (grid ji volá přímo z `onChange`, atribut `#[Secured]` se tam nevyhodnotí)
   vyhodila `ForbiddenRequestException`, kterou nic nezachytilo - chybová stránka místo hlášky.
2. Po přidání obrázku ze správce souborů se vymazalo pole URL: `main.js` zapisoval do `.validate-url-output`
   `payload.url`, které handlery obrázků neposílají. Uložení pak URL vygenerovalo z titulku.
3. Kategorie článku se v detailu nezobrazovaly, `{control categoriesGrid}` bylo zakomentované. Datagrid vykresluje
   vlastní `<form>` a snippet ležel uvnitř formuláře článku (vnořený formulář).
4. „Přidat“ v okně výběru kategorií mělo `data-bs-toggle="modal" data-bs-target="#modal"` - po přidání kategorie se
   otevřelo okno pro výběr obrázků.

**Dotčené soubory/oblasti:**
- `app/AdminModule/Presenters/ArticlesPresenter.php` — status sloupec gridu `setRenderCondition()` (u článků bez práva
  `edit` jen text stavu), `handleActivate()` při chybějícím právu flash zpráva a přesměrování (stejný text jako
  `checkRequirements()`), `createComponentCategoriesGrid()` bez neexistujícího sloupce `default` (tlačítko Smazat),
  „Přidat“ v `categorySelectionGrid` okno výběru zavře (`data-bs-dismiss`).
- `app/AdminModule/templates/Articles/detail.latte` — obrázky a kategorie (`{control categoriesGrid}`) za `{/form}` v
  bloku `#article-relations` pod celým článkem přes celou šířku, skryté pole `data[images]` zůstává ve formuláři. Blok
  se zobrazí jen u uloženého článku (`$id`) - nový článek nemá k čemu obrázky ani kategorie připojit (handlery
  potřebují ID).
- `www/administration/js/main.js` — `selected-images-from-iframe` bez `success`, snippety překreslí nette.ajax;
  sortable obrázků na `.images-sortable` (blok už není v `#sidebar-inside`).

**Ověřeno** v Chromiu (testovací autor s právem `add`, pak smazán i s daty): přepínač aktivity jen u vlastního
článku, ručně poslaný signál gridu pro cizí článek → hláška „Nemáte přístup do sekce 'Články' s přístupovými právy
'upravit'.“, článek beze změny. Detail: grid kategorií pod článkem bez vnořeného formuláře, smazání s potvrzením, přidání z okna výběru
(okno se zavře, okno obrázků se neotevře), přepnutí hlavní kategorie stavem v gridu, přidání a odebrání obrázku
nechá vlastní URL, nový článek bez bloku obrázků a kategorií.

**Návaznost:**
- Update `docs/Architecture/*.md`? ne
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ne
- DB migrace potřeba? ne
