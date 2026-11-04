<?php
declare(strict_types=1);

namespace App\Mcp;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Tracy\ILogger;

/**
 * PSR-3 logger pro MCP SDK, který do Tracy (log/) posílá jen varování a chyby.
 *
 * SDK loguje na úrovni debug/info každý požadavek („Executing tool“, „Running server...“) - přes obecný
 * Tracy\Bridges\Psr\TracyToPsrLoggerAdapter by tím rostly soubory log/debug.log a log/info.log s každým voláním.
 * Výjimka z kontextu (`exception`) se loguje celá, Tracy k ní uloží i BlueScreen.
 */
final class McpLogger extends AbstractLogger
{

	private const array Levels = [
		LogLevel::EMERGENCY => ILogger::CRITICAL,
		LogLevel::ALERT => ILogger::CRITICAL,
		LogLevel::CRITICAL => ILogger::CRITICAL,
		LogLevel::ERROR => ILogger::ERROR,
		LogLevel::WARNING => ILogger::WARNING,
	];


	public function __construct(
		private readonly ILogger $logger,
	) {
	}


	/**
	 * @param mixed $level
	 * @param array<mixed> $context
	 */
	public function log($level, string|\Stringable $message, array $context = []): void
	{
		$tracyLevel = is_string($level) ? (self::Levels[$level] ?? null) : null;
		if ($tracyLevel === null) {
			return;
		}

		$exception = $context['exception'] ?? null;
		$this->logger->log(
			$exception instanceof \Throwable ? $exception : 'MCP: ' . $message,
			$tracyLevel,
		);
	}

}
