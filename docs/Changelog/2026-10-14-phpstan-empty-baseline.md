# 2026-10-14 — PHPStan level 5 bez baseline

**Co:** `composer stan` (level 5) prochází nad celým `app/` s prázdnou `app/config/phpstan-baseline.neon`.
Poslední chyba (`new static` v uzlech maker obrázků) je opravená, třídy uzlů jsou `final`.

**Proč:** Baseline se postupně čistila opravami kódu, zbývaly v ní jen čtyři záznamy `new.static`.
Brána teď znamená „bez chyb“, ne jen „bez nových chyb nad rámec baseline“.

**Dotčené soubory/oblasti:**
- `app/FileStorage/Macro/Nodes/SrcNode.php`, `BgNode.php`, `CropNode.php`, `ImageNode.php` — `final class`, takže
  `new static` v `create()` je bezpečné. Nikde se z nich nedědí, makra `n:src`, `n:bg`, `{image}`/`n:image` a
  `{crop}`/`n:crop` se kompilují beze změny.
- `app/config/phpstan-baseline.neon` — `ignoreErrors: []`.
- `CLAUDE.md`, `docs/AI-Context/quick-reference.md` — brána bez baseline, nová chyba se opravuje, ne přidává do baseline.
- `docs/AI-Context/gotchas.md` — odstraněna sekce o akceptovaném false positive `addFileUpload()`/`getHash()`,
  PHPStan ho už nehlásí (`addFileUpload()` deklaruje `@method` na `AdminForm`).

**Návaznost:**
- Update `docs/Architecture/*.md`? ne
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ano, `gotchas.md` (viz výše)
- DB migrace potřeba? ne
