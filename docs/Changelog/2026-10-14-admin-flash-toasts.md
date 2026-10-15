# 2026-10-14 — Flash zprávy administrace vpravo dole

**Co:** Flash zprávy v administraci se zobrazují jako zásobník v pravém dolním rohu a po 5 s se samy zavřou.
Najetí myší (nebo fokus) na kteroukoli zprávu odpočet všech zpráv pozastaví, po odjetí pokračuje zbývajícím časem.

**Proč:** Zprávy byly fixně nahoře přes celou šířku stránky, zakrývaly hlavičku a zůstávaly, dokud je uživatel nezavřel.

**Dotčené soubory/oblasti:**
- `app/AdminModule/templates/@layout.latte` — snippet `flashes`: proužek `.flash-timer`, zpráva se už nezavírá
  kliknutím kamkoliv (jen křížkem), aby šel text označit a zkopírovat.
- `www/administration/css/style.css` — `.message` vpravo dole (max. šířka 24rem, na mobilu celá šířka bez okrajů),
  odpočet je CSS animace `flash-timer` (5 s), `.message:hover`/`:focus-within` ji pozastaví.
- `www/administration/js/main.js` — po `animationend` proužku zavře zprávu `bootstrap.Alert` (delegováno na
  `document`, platí i pro snippet překreslený AJAXem).

**Rozhodnutí a kompromisy:**
Odpočet je jen CSS animace: pozastavení a zbývající čas řeší prohlížeč, JS jen zavře zprávu. Pozastavují se
všechny zprávy najednou: zavřená zpráva by posunula ostatní v zásobníku a ta pod kurzorem by z něj vyjela.
V prohlížeči na pozadí (neaktivní karta) animace neběží, zpráva tak počká, až se uživatel na kartu vrátí.
V dev režimu může spodní zprávu částečně překrýt Tracy lišta.

**Návaznost:**
- Update `docs/Architecture/*.md`? ne
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ne
- DB migrace potřeba? ne
