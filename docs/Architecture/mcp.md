# MCP server (`app/Mcp/`)

Jádro má endpoint `/mcp` s MCP serverem ([Model Context Protocol](https://modelcontextprotocol.io), transport
Streamable HTTP). Přes něj volají AI asistenti nástroje webu: Claude (vlastní konektor), MCP connector Claude API,
MCP Inspector a další MCP klienti. Jádro dodává jen server, **nástroje přidávají pluginy**. Bez jediného nástroje
endpoint vrací 404, projekty bez takového pluginu o MCP serveru nevědí.

Postaveno na oficiálním PHP SDK [`mcp/sdk`](https://github.com/modelcontextprotocol/php-sdk) (`^0.9`).

## Jak přidat nástroje (plugin)

1. Služba implementující `App\Mcp\McpToolProvider`. Nástroje jsou její veřejné metody s atributem
   `#[McpTool]`, `getInstructions()` vrací návod pro model (spojí se do `instructions` serveru) nebo NULL.
2. Zaregistrovat ji v `config.plugin.neon` pluginu. `App\Mcp\McpServer` dostává všechny takové služby přes
   `typed(App\Mcp\McpToolProvider)`, stejně jako `CronRunner` cron úlohy, nikde jinde se nevyjmenovávají.
3. Volitelně název serveru, který uvidí klient: `parameters: mcp: serverName: …` (výchozí `Fire CMS`
   v `app/config/config.neon`; neon pluginu se načítá po jádru, takže ho přepíše).

```php
final class ArticleTools implements McpToolProvider
{
	public function getInstructions(): ?string
	{
		return 'Search the articles of the website with search_articles.';
	}

	/**
	 * Vyhledávání článků (komentář pro vývojáře česky)
	 *
	 * @return array<string, mixed>
	 */
	#[McpTool(
		name: 'search_articles',
		description: 'Search published articles by a phrase.',
		annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true, openWorldHint: false),
	)]
	public function searchArticles(
		#[Schema(description: 'Phrase to search for.')]
		string $query,
		#[Schema(description: 'Maximum number of articles.', minimum: 1, maximum: 20)]
		int $limit = 10,
	): array {
		// ...
		return ['articles' => $articles];
	}
}
```

```neon
services:
	- App\Plugins\Articles\Mcp\ArticleTools
```

Pravidla:

- **Schéma vstupu** se generuje z parametrů metody: typ, výchozí hodnota (= nepovinný), nullable, backed enum
  (= výčet hodnot), popis a omezení z `#[Mcp\Capability\Attribute\Schema]` u parametru. Názvy argumentů jsou
  názvy parametrů PHP.
- **Popisy nástrojů a parametrů anglicky** - čte je jazykový model. Popis z `#[Schema]` má přednost před `@param`
  v docblocku, docblock tak může zůstat česky pro vývojáře.
- **Chyba pro model** (neplatné datum, neexistující záznam) = `Mcp\Exception\ToolCallException` se srozumitelnou
  zprávou: klient dostane výsledek s `isError: true` a model může vstup opravit. Jiná výjimka skončí obecnou
  chybou „Error while executing tool“ a zapíše se do logu Tracy. Argumenty mimo schéma (špatný typ, hodnota mimo
  výčet) SDK odmítne samo ještě před voláním metody.
- **Návratová hodnota**: asociativní pole se pošle jako JSON text a zároveň jako `structuredContent`. Prázdné pole
  se v JSONu pošle jako `[]`, ne objekt - prázdnou mapu (např. ceny) vracejte jako NULL.
- **Jen veřejná data.** Endpoint je bez přihlášení, nástroj nesmí vrátit nic, co není veřejně na webu. Nástroj
  nemá nic zakládat ani měnit; akce (rezervace, objednávka) se dokončí odkazem na web s běžným přihlášením.
- **Odkazy** přes `Nette\Application\LinkGenerator` - absolutní URL z domény požadavku.
- **Chybnou definici nástroje SDK jen zaloguje** a nástroj v `tools/list` chybí, server běží dál. Po přidání
  nástroje proto ověřte `tools/list` (test nebo MCP Inspector).

## Jak to funguje

- `App\Router\McpRouter` (`mcp`, priorita 80) → `App\Presenters\McpPresenter`. Je to holý `IPresenter` jako
  `CronPresenter`: bez layoutu, jazyků, session a přihlášení. Nette požadavek převede na PSR-7 (`guzzlehttp/psr7`),
  zavolá `McpServer::handle()` a PSR-7 odpověď pošle přes `App\Mcp\PsrHttpResponse`. Vypíná Tracy bar, jinak by
  ho Tracy v debug režimu připojila k odpovědím bez `Content-Type` (202 na notifikaci).
- `App\Mcp\McpServer` sestaví server SDK pro každý požadavek a registruje nástroje ručně přes `Builder::addTool()`
  s instancí služby z DI. Discovery SDK (`setDiscovery()`) se nepoužívá: prochází soubory a třídy vytváří samo,
  bez závislostí z kontejneru.
- **Obě éry protokolu na jednom endpointu** (`StreamableHttpTransport` požadavek rozliší sám):
  - handshake (`initialize`, revize do 2025-11-25): MCP session v hlavičce `Mcp-Session-Id`. Každý HTTP
    požadavek obslouží jiný PHP proces, session jsou proto v souborech v `temp/mcp-sessions/`
    (`FileSessionStore`, platnost 1 h);
  - bezstavová revize 2026-07-28: bez session, verze v `params._meta`, hlavičky `Mcp-Method`/`Mcp-Name`.
- **Middleware**: CORS pro libovolný origin bez cookies (aby šlo server zkoušet z prohlížeče). Ochrana proti
  DNS rebindingu z výchozích middlewarů SDK je vypnutá: chrání servery na localhostu, které by jinak volal
  podvržený web v prohlížeči; veřejný web má Host hlídaný webserverem a vrací jen veřejná data.
- **Log**: `App\Mcp\McpLogger` (služba `mcp.logger`, ne autowirovaná) posílá do Tracy jen varování a chyby.
  SDK loguje každý požadavek na úrovni debug/info, přes obecný PSR adaptér by rostly `log/debug.log` a `info.log`.

## Vyzkoušení

- Test jádra: `tests/Mcp/McpServerTest.phpt` (handshake, `tools/list`, volání, chyba pro model, bezstavová revize).
- MCP Inspector: `npx @modelcontextprotocol/inspector`, transport Streamable HTTP, URL `https://<doména>/mcp`.
- curl (handshake):
  ```
  curl -i -X POST https://<doména>/mcp -H 'Content-Type: application/json' \
    -H 'Accept: application/json, text/event-stream' \
    -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"curl","version":"1"}}}'
  ```
  Další požadavky s hlavičkami `Mcp-Session-Id` (z odpovědi) a `Mcp-Protocol-Version: 2025-11-25`.
- Claude: vlastní konektor s URL `https://<doména>/mcp`. Claude API: MCP connector (`mcp_servers` + nástroj
  `mcp_toolset`, beta `mcp-client-2025-11-20`). Server musí být dostupný z internetu přes HTTPS, volají ho servery
  Anthropicu, ne prohlížeč - lokální vývoj jen přes tunel (ngrok, cloudflared).

## Závislost `mcp/sdk`

SDK je do verze 1.0 experimentální, minor verze může rozbít API - proto `^0.9` a upgrade jen vědomě s během
`tests/Mcp`. Composer plugin `php-http/discovery` (závislost SDK) je v `composer.json` vypnutý
(`allow-plugins`), PSR-17 továrny dostává transport explicitně (`GuzzleHttp\Psr7\HttpFactory`).
