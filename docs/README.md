# Fire CMS — Knowledge Base

> Dokumentace projektu Fire CMS. Slouží jako znalostní báze pro vývojáře i AI asistenty.

Pozor na rozlišení: `docs/` (tento adresář) je znalostní báze architektury. `doc/` (bez "s", o úroveň výš)
obsahuje provozní návody — merge/rebase jádra do klientských projektů, nastavení GitHub nasazení (`doc/github-deploy.md`) a
konvence balíčků. Viz `CLAUDE.md` v kořeni repozitáře pro odkazy na oba.

Tato dokumentace popisuje jen jádro a `app/Plugins/`. Dokumentace konkrétního klientského projektu patří do
`theme/docs/` s hlavním souborem `theme/docs/README.md` a vytváří se jen tehdy, když je potřeba. Nasazení
projektu si každý projekt popíše vlastním `.github/workflows/deploy.yml` podle vzoru
`.github/workflows/deploy.yml.dist` (`doc/github-deploy.md`). Jádro `theme/docs/` ani `deploy.yml` neobsahuje.

## Navigace

### Architektura
- [Přehled architektury](Architecture/overview.md) — bootstrap, vrstvení konfigurace, vysokoúrovňový pohled
- [Vrstva Model](Architecture/orm.md) — `BaseModel` nad `Nette\Database\Explorer`, žádné ORM
- [Komponenty](Architecture/components.md) — `app/Components/*`, `createComponent()`, plugin komponenty
- [Presentery](Architecture/presenters.md) — hierarchie BasePresenter, ACL atributy, grid+modal CRUD vzor
- [Modules vs. Plugins](Architecture/plugins.md) — dva mechanismy rozšíření, aktuální seznam pluginů
- [Routing](Architecture/routing.md) — tři routery, DB-podložená hezká URL, priority
- [Konfigurace](Architecture/configuration.md) — NEON vrstvení, DI extensions
- [Cron](Architecture/cron.md) — endpoint `/cron`, `App\Cron\CronTask`, kdy úloha poběží
- [Sessions](Architecture/sessions.md) — úložiště sessions (soubory, MySQL, PostgreSQL, Redis), `sessionHandler:` v theme.neon
- [Úložiště souborů](Architecture/file-storage.md) — správce souborů na lokálním disku nebo S3 (`fileStorage: storages:`), povolené náhledy obrázků (`fileStorage: thumbnails:`)

### Moduly
- [FrontModule](Modules/front.md) — veřejný frontend
- [AdminModule](Modules/admin.md) — administrace

### AI Kontext
- [Quick Reference](AI-Context/quick-reference.md) — **kompaktní přehled pro AI** (začni zde)
- [Vzory kódu](AI-Context/patterns.md) — časté patterny a jak je používat
- [Gotchas](AI-Context/gotchas.md) — časté chyby a na co si dát pozor

### Changelog
- [Šablona záznamu](Changelog/_template.md) — jak evidovat změny
- [Index změn](Changelog/_index.md) — chronologický přehled

## Jak používat

### Pro vývojáře
Dokumentace je ve standardním Markdownu — funguje na GitHubu i lokálně.

### Pro AI asistenty
Načti soubor `AI-Context/quick-reference.md` — obsahuje kompaktní přehled celého systému optimalizovaný na
minimální počet tokenů. Podrobnější informace najdeš v `Architecture/`.

### Evidence změn
Po každé významné změně vytvoř nový soubor v `Changelog/` podle [šablony](Changelog/_template.md).
