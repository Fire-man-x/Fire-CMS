<?php

declare(strict_types=1);

namespace Tests\Mcp;

use App\Mcp\McpServer;
use App\Mcp\McpToolProvider;
use GuzzleHttp\Psr7\ServerRequest;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Psr\Log\NullLogger;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Testovací poskytovatel: jeden nástroj s parametrem, jeden s chybou pro model a veřejná metoda bez atributu,
 * která se jako nástroj objevit nesmí.
 */
final class GreetingTools implements McpToolProvider
{

	public function getInstructions(): string
	{
		return 'Greets people.';
	}


	/**
	 * @return array{greeting: string}
	 */
	#[McpTool(name: 'greet', description: 'Greet a person.')]
	public function greet(
		#[Schema(description: 'Name of the person.')]
		string $name,
	): array {
		return ['greeting' => "Hello, $name!"];
	}


	#[McpTool(name: 'fail', description: 'Always fails.')]
	public function fail(): string
	{
		throw new ToolCallException('Nothing to do.');
	}


	public function notATool(): string
	{
		return 'hidden';
	}

}


/**
 * App\Mcp\McpServer přes skutečné JSON-RPC požadavky (PSR-7, bez HTTP serveru): handshake s MCP session v souborech
 * a bezstavová revize 2026-07-28.
 */
final class McpServerTest extends TestCase
{

	private const string HandshakeVersion = '2025-11-25';

	private const string StatelessVersion = '2026-07-28';


	public function testWithoutProvidersHasNoTools(): void
	{
		Assert::false($this->createServer([])->hasTools());
		Assert::true($this->createServer([new GreetingTools()])->hasTools());
	}


	public function testHandshakeListsAndCallsTools(): void
	{
		$server = $this->createServer([new GreetingTools()]);

		[$init, $sessionId] = $this->post($server, 'initialize', [
			'protocolVersion' => self::HandshakeVersion,
			'capabilities' => new \stdClass(),
			'clientInfo' => ['name' => 'test', 'version' => '1'],
		]);
		Assert::same('Test server', $init['result']['serverInfo']['name']);
		Assert::same('Greets people.', $init['result']['instructions']);
		Assert::type('string', $sessionId);

		$this->post($server, 'notifications/initialized', [], $sessionId, notification: true);

		[$list] = $this->post($server, 'tools/list', [], $sessionId);
		$tools = array_column($list['result']['tools'], null, 'name');
		Assert::same(['greet', 'fail'], array_keys($tools));
		Assert::same(['name'], $tools['greet']['inputSchema']['required']);
		Assert::same('Name of the person.', $tools['greet']['inputSchema']['properties']['name']['description']);

		[$call] = $this->post($server, 'tools/call', ['name' => 'greet', 'arguments' => ['name' => 'Alice']], $sessionId);
		Assert::same(['greeting' => 'Hello, Alice!'], $call['result']['structuredContent']);

		[$error] = $this->post($server, 'tools/call', ['name' => 'fail', 'arguments' => new \stdClass()], $sessionId);
		Assert::true($error['result']['isError']);
		Assert::same('Nothing to do.', $error['result']['content'][0]['text']);
	}


	public function testStatelessRevisionNeedsNoSession(): void
	{
		$server = $this->createServer([new GreetingTools()]);

		$request = new ServerRequest('POST', 'https://example.test/mcp', [
			'Content-Type' => 'application/json',
			'Accept' => 'application/json, text/event-stream',
			'MCP-Protocol-Version' => self::StatelessVersion,
			'Mcp-Method' => 'tools/call',
			'Mcp-Name' => 'greet',
		], json_encode([
			'jsonrpc' => '2.0',
			'id' => 1,
			'method' => 'tools/call',
			'params' => [
				'name' => 'greet',
				'arguments' => ['name' => 'Bob'],
				'_meta' => [
					'io.modelcontextprotocol/protocolVersion' => self::StatelessVersion,
					'io.modelcontextprotocol/clientInfo' => ['name' => 'test', 'version' => '1'],
					'io.modelcontextprotocol/clientCapabilities' => new \stdClass(),
				],
			],
		], JSON_THROW_ON_ERROR));

		$response = json_decode((string) $server->handle($request)->getBody(), true, 512, JSON_THROW_ON_ERROR);
		Assert::same(['greeting' => 'Hello, Bob!'], $response['result']['structuredContent']);
	}


	/**
	 * @param McpToolProvider[] $providers
	 */
	private function createServer(array $providers): McpServer
	{
		return new McpServer($providers, 'Test server', '1.0.0', TEMP_DIR . '/mcp-sessions', new NullLogger());
	}


	/**
	 * @param array<string, mixed> $params
	 * @return array{0: array<string, mixed>, 1: ?string} dekódovaná odpověď (prázdná u notifikace) a ID MCP session
	 */
	private function post(McpServer $server, string $method, array $params, ?string $sessionId = null, bool $notification = false): array
	{
		static $id = 0;
		$body = ['jsonrpc' => '2.0', 'method' => $method, 'params' => (object) $params];
		if (!$notification) {
			$body['id'] = ++$id;
		}

		$headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream'];
		if ($sessionId !== null) {
			$headers['Mcp-Session-Id'] = $sessionId;
			$headers['Mcp-Protocol-Version'] = self::HandshakeVersion;
		}

		$response = $server->handle(new ServerRequest('POST', 'https://example.test/mcp', $headers, json_encode($body, JSON_THROW_ON_ERROR)));
		$raw = (string) $response->getBody();

		return [
			$raw !== '' ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : [],
			$response->getHeaderLine('Mcp-Session-Id') ?: $sessionId,
		];
	}

}


(new McpServerTest())->run();
