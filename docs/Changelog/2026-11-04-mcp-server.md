# 2026-11-04 — MCP server `/mcp` s nástroji z pluginů

**Co:** Jádro má endpoint `/mcp` s MCP serverem (oficiální PHP SDK `mcp/sdk`, Streamable HTTP). Nástroje přidávají
pluginy službou `App\Mcp\McpToolProvider` s metodami `#[McpTool]`; bez nástrojů endpoint vrací 404.

**Proč:** Aby šly funkce webu (vyhledávání, předvyplnění rezervace apod.) používat z AI asistentů (Claude, MCP
connector Claude API, další MCP klienti) a aby na tom mohly stavět všechny projekty, ne jen jeden plugin.

**Dotčené soubory/oblasti:**
- `app/Mcp/McpToolProvider.php` — rozhraní poskytovatele nástrojů
- `app/Mcp/McpServer.php` — sestavení serveru SDK s nástroji z DI, obsluha jednoho PSR-7 požadavku
- `app/Mcp/PsrHttpResponse.php` — odeslání PSR-7 odpovědi přes Nette (i SSE)
- `app/Mcp/McpLogger.php` — PSR-3 logger do Tracy jen pro varování a chyby
- `app/Presenters/McpPresenter.php`, `app/Router/McpRouter.php` — endpoint `/mcp`
- `app/config/config.neon` — parametry `mcp.serverName`/`mcp.serverVersion`, služby, priorita routy 80
- `composer.json`/`composer.lock` — `mcp/sdk: ^0.9`, `allow-plugins: php-http/discovery: false`
- `tests/Mcp/McpServerTest.phpt` — handshake, `tools/list`, volání, chyba pro model, bezstavová revize

**Rozhodnutí a kompromisy:**
- Součást jádra jako cron (endpoint + služby z pluginů přes `typed()`), ne systémový plugin v `app/Plugins/`:
  projekt bez nástrojů nic nezapíná ani nevidí a plugin MCP rozšíří jen registrací služby.
- Nástroje se registrují ručně z instancí v DI, ne discovery SDK (to prochází soubory a vytváří třídy bez DI,
  navíc potřebuje `symfony/finder`).
- Obě éry protokolu (handshake s MCP session v `temp/mcp-sessions/` i bezstavová 2026-07-28) na jednom endpointu -
  dnešní klienti používají převážně handshake.
- Ochrana proti DNS rebindingu ze SDK vypnutá (chrání servery na localhostu), CORS pro libovolný origin bez cookies.
  Endpoint je bez přihlášení a nástroje smí vracet jen veřejná data.
- `composer require` povýšil i PHPStan (2.2.16 → 2.3.1, volné omezení `*`) - vráceno na zamčené verze, změna
  locku obsahuje jen nové balíčky.

**Návaznost:**
- Update `docs/Architecture/*.md`? ano - nový `mcp.md`, řádek v tabulce `routing.md`, odkaz v `plugins.md`
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ne (pravidla pro nástroje jsou v `mcp.md`)
- DB migrace potřeba? ne
