# 2026-10-08 — Potvrzovací okno a okno s iframe v administraci (Bootstrap 5)

**Co:** `www/administration/js/main.js` hledal odkazy podle `data-target`, šablony a gridy administrace (jádra
i pluginů) ale po přechodu na Bootstrap 5 používají `data-bs-target`. Oba selektory jsou opravené:
- potvrzovací okno (`data-bs-target="#confirm-modal"`, mazání, schvalování v gridech),
- okno s iframe (`[data-src][data-bs-target]`, výběr obrázků ze správce souborů u článků, stránek, kategorií,
  menu, sliderů, komentářů).

**Proč:** Kliknutí na „Smazat“ v gridu **smazalo položku hned, bez potvrzení**. Odkaz s třídou `ajax` si
nechal skutečnou adresu, `nette.ajax` ho odeslal a zároveň se otevřelo potvrzovací okno, jehož tlačítko OK
vedlo na `#`. Okno pro výběr obrázku zůstávalo prázdné (iframe bez `src`).

**Dotčené soubory/oblasti:**
- `www/administration/js/main.js` — selektory `data-bs-target`; přesun `href` do `data-confirm-url` je
  v `load` rozšíření `confirm` místo `init`, takže se zpracují i odkazy v gridu překresleném AJAXem
  (stránkování, řazení, filtr); už zpracovaný odkaz (`href="#"`) se přeskočí

**Rozhodnutí a kompromisy:**
Starý zápis `data-target` se nepodporuje: Bootstrap 5 ho pro otevření okna nezná a v jádru ani v pluginech už
není. Ověřeno v prohlížeči (headless Chromium) na stránce se stejnými knihovnami jako administrace (jQuery,
jQuery UI, `nette.ajax`, Bootstrap 5.3.8, `main.js`): před opravou se smazání odeslalo hned po kliknutí na
„Smazat“, po opravě až po potvrzení, i po překreslení gridu. `main.js` se načítá s časem změny souboru
(`?v=filemtime`), prohlížeče novou verzi načtou samy.

**Návaznost:**
- Update `docs/Architecture/*.md`? ne
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ano — `gotchas.md` (Bootstrap 5 atributy v JS)
- DB migrace potřeba? ne
