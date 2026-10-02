# 2026-10-02 — Nasazení projektů přes GitHub Actions

**Co:** Jádro dodává vzor nasazení `.github/workflows/deploy.yml.dist` (SSH + rsync). Každý klientský projekt
si z něj vytvoří vlastní `.github/workflows/deploy.yml`. Dokumentace projektu patří do `theme/docs/` (vstup
`theme/docs/README.md`). Návody v `doc/` jsou převedené z GitLabu na GitHub.

**Proč:** Repozitář jádra je na GitHubu, ale návody v `doc/` popisovaly GitLab CI/CD. Pipeline v repozitáři
navíc nebyla, takže by si ji každý projekt psal znovu. Nasazení a dokumentace projektu do jádra nepatří, merge
jádra by je přepisoval.

**Dotčené soubory/oblasti:**
- `.github/workflows/deploy.yml.dist` — vzor nasazení. Postupně: CI z `ci.yml`, `composer install --no-dev`,
  kontrola cílového adresáře (musí obsahovat `config.local.neon`), `rsync --delete` s výjimkami pro stav
  serveru, smazání `temp/cache/`, `migrations:continue` (ruční spuštění s volbou `reset` = `migrations:reset`),
  znovu smazání `temp/cache/`.
- `.github/workflows/ci.yml` — přidaný `workflow_call`, aby ho `deploy.yml` mohl volat. Push do `master` a pull
  requesty ho spouštějí dál, v jádru i v projektech.
- `doc/github-deploy.md` (dřív `doc/gitlab-deploy.md`) — zapnutí nasazení v projektu, GitHub Environment,
  proměnné a secrets, SSH a `known_hosts`, PHP v SSH, uživatel webového serveru, omezení.
- `doc/update-project.md` (dřív `doc/update-project-with-gitlab-deploy.md`) — bez GitLabu, při vytvoření
  projektu i `deploy.yml` a `theme/docs/`.
- `doc/conventions.md` — sekce „Klientský projekt“.
- `CLAUDE.md`, `Readme.md`, `docs/README.md`, `docs/Architecture/overview.md`,
  `docs/AI-Context/quick-reference.md` — odkazy na nové návody, `deploy.yml` a `theme/docs/`.
- `docs/AI-Context/gotchas.md` — nasazení s `rsync --delete`. Opravená cesta ke cache struktury DB
  (`temp/cache/_Nette.Database*`, ne `temp/_Nette.Database*`).

**Rozhodnutí a kompromisy:**
- CI se z nasazení volá jako reusable workflow (`uses: ./.github/workflows/ci.yml`), ne přes `workflow_run`.
  `workflow_run` by spouštěl nasazení i po CI z pull requestu forku s větví `master` (se secrets cílového
  repozitáře) a fungoval by jen pro větve, na kterých běží `ci.yml`. Cena: při nasazování z `master` běží CI po
  pushi dvakrát.
- Závislosti se instalují na GitHubu a nahrávají přes rsync. Server tak nepotřebuje Composer ani přístup
  k repozitáři, potřebuje jen `bash` a `rsync`.
- Reset databáze je jen volba ručního spuštění, ne trvalá proměnná jako `DEPLOY_RESET` na GitLabu. Zapomenutá
  proměnná by smazala databázi při každém nasazení.
- Klíč serveru se ověřuje proti `DEPLOY_KNOWN_HOSTS`, žádné `StrictHostKeyChecking=no`.
- Vzor se do projektů kopíruje, takže se jeho úpravy v jádru do `deploy.yml` projektů samy nepropíšou. Při větším
  počtu projektů zvážit reusable deploy workflow v jádru, který by `deploy.yml` projektu jen volal se svým
  nastavením.

**Ověření:** `actionlint` bez chyb. Kroky vzoru jsem spustil lokálně proti simulovanému serveru (rsync přes
falešné `ssh`). Zachovaly se `config.local.neon`, nahrané soubory, náhledy, logy, sessions a `.untranslated`.
Smazaný kód zmizel, `temp/cache/` se vyčistila a kontrola zastavila nasazení do adresáře bez `config.local.neon`.
Skutečné nasazení na server ověřené není.

**Návaznost:**
- Update `docs/Architecture/*.md`? ano — `overview.md` (odkaz na `doc/update-project.md`).
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ano — `gotchas.md`, nasazení s `rsync --delete`.
- DB migrace potřeba? ne.
