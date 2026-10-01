<?php
declare(strict_types=1);

namespace App\Session\DatabaseSessionHandler;

/**
 * Minimální klient Redisu (protokol RESP2) přes stream socket - bez PHP rozšíření redis a bez knihovny. Umí jen to,
 * co potřebuje RedisSessionHandler: poslat příkaz a přečíst odpověď. Připojuje se až při prvním příkazu.
 *
 * $host: `127.0.0.1` / `redis.example.com` (TCP), `tls://redis.example.com` (TLS) nebo `/var/run/redis.sock` (unix socket,
 * port se ignoruje).
 */
final class RedisConnection
{
	/** @var resource|null */
	private $stream = null;


	public function __construct(
		private readonly string $host = '127.0.0.1',
		private readonly int $port = 6379,
		private readonly ?string $username = null,
		private readonly ?string $password = null,
		private readonly int $database = 0,
		private readonly float $timeout = 2.0,
	) {
	}


	public function __destruct()
	{
		if ($this->stream !== null) {
			fclose($this->stream);
		}
	}


	/**
	 * Pošle příkaz a vrátí odpověď: řetězec (simple/bulk string), int, NULL (nil) nebo pole (array reply)
	 *
	 * @return string|int|list<mixed>|null
	 */
	public function command(string ...$args): string|int|array|null
	{
		$stream = $this->getStream();

		$payload = '*' . count($args) . "\r\n";
		foreach ($args as $arg) {
			$payload .= '$' . strlen($arg) . "\r\n" . $arg . "\r\n";
		}

		for ($written = 0; $written < strlen($payload); $written += $bytes) {
			$bytes = fwrite($stream, substr($payload, $written));
			if ($bytes === false || $bytes === 0) {
				$this->disconnect();
				throw new \RuntimeException('Writing to Redis failed.');
			}
		}

		return $this->readReply($stream);
	}


	/**
	 * @return resource
	 */
	private function getStream()
	{
		if ($this->stream !== null) {
			return $this->stream;
		}

		$address = match (true) {
			str_starts_with($this->host, '/') => 'unix://' . $this->host,
			str_contains($this->host, '://') => $this->host . ':' . $this->port,
			default => 'tcp://' . $this->host . ':' . $this->port,
		};

		$stream = @stream_socket_client($address, $errorCode, $errorMessage, $this->timeout);
		if ($stream === false) {
			throw new \RuntimeException("Cannot connect to Redis at '$address': $errorMessage ($errorCode).");
		}

		$seconds = (int) $this->timeout;
		stream_set_timeout($stream, $seconds, (int) (($this->timeout - $seconds) * 1_000_000));
		$this->stream = $stream;

		if ($this->password !== null) {
			$this->username !== null
				? $this->command('AUTH', $this->username, $this->password)
				: $this->command('AUTH', $this->password);
		}

		if ($this->database !== 0) {
			$this->command('SELECT', (string) $this->database);
		}

		return $stream;
	}


	/**
	 * @param resource $stream
	 * @return string|int|list<mixed>|null
	 */
	private function readReply($stream): string|int|array|null
	{
		$line = fgets($stream);
		if ($line === false) {
			$this->failRead($stream);
		}

		$type = $line[0];
		$value = substr($line, 1, -2);

		switch ($type) {
			case '+':
				return $value;

			case '-':
				throw new \RuntimeException("Redis error: $value");

			case ':':
				return (int) $value;

			case '$':
				return $value === '-1' ? null : $this->readBulk($stream, (int) $value);

			case '*':
				if ($value === '-1') {
					return null;
				}
				$items = [];
				for ($i = 0; $i < (int) $value; $i++) {
					$items[] = $this->readReply($stream);
				}
				return $items;

			default:
				$this->disconnect();
				throw new \RuntimeException("Unexpected Redis reply '$line'.");
		}
	}


	/**
	 * @param resource $stream
	 */
	private function readBulk($stream, int $length): string
	{
		$data = '';
		while (strlen($data) < $length + 2) { // + ukončovací \r\n
			$chunk = fread($stream, max(1, $length + 2 - strlen($data)));
			if ($chunk === false || $chunk === '') {
				$this->failRead($stream);
			}
			$data .= $chunk;
		}

		return substr($data, 0, $length);
	}


	/**
	 * @param resource $stream
	 */
	private function failRead($stream): never
	{
		$timedOut = stream_get_meta_data($stream)['timed_out'];
		$this->disconnect();

		throw new \RuntimeException($timedOut ? "Redis did not respond within {$this->timeout} s." : 'Reading from Redis failed (connection closed).');
	}


	/**
	 * Po chybě přenosu je stav spojení neznámý (rozečtená odpověď) - další příkaz se připojí znovu
	 */
	private function disconnect(): void
	{
		if ($this->stream !== null) {
			fclose($this->stream);
			$this->stream = null;
		}
	}

}
