# Fire CMS — Knowledge Base

> Dokumentace projektu Fire CMS. Slouží jako znalostní báze pro vývojáře i AI asistenty.

Pozor na rozlišení: `docs/` (tento adresář) je znalostní báze architektury. `doc/` (bez "s", o úroveň výš)
obsahuje provozní návody — merge/rebase jádra do klientských projektů, nastavení GitLab CI/CD nasazení a
konvence balíčků. Viz `CLAUDE.md` v kořeni repozitáře pro odkazy na oba.

## Navigace

### Architektura
- [Přehled architektury](Architecture/overview.md) — bootstrap, vrstvení konfigurace, vysokoúrovňový pohled
- [Vrstva Model](Architecture/orm.md) — `BaseModel` nad `Nette\Database\Explorer`, žádné ORM
- [Komponenty](Architecture/components.md) — `app/Components/*`, `createComponent()`, plugin komponenty
- [Presentery](Architecture/presenters.md) — hierarchie BasePresenter, ACL atributy, grid+modal CRUD vzor
- [Modules vs. Plugins](Architecture/plugins.md) — dva mechanismy rozšíření, aktuální seznam pluginů
- [Routing](Architecture/routing.md) — tři routery, DB-podložená hezká URL, priority
- [Konfigurace](Architecture/configuration.md) — NEON vrstvení, DI extensions

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
Dokumentace je ve standardním Markdownu — funguje na GitLabu i lokálně.

### Pro AI asistenty
Načti soubor `AI-Context/quick-reference.md` — obsahuje kompaktní přehled celého systému optimalizovaný na
minimální počet tokenů. Podrobnější informace najdeš v `Architecture/`.

### Evidence změn
Po každé významné změně vytvoř nový soubor v `Changelog/` podle [šablony](Changelog/_template.md).
