<?php
declare(strict_types=1);

namespace App\Cron;

/**
 * Nastavení cron endpointu. Token se nastavuje parametrem `cronToken` v config.local.neon (každé prostředí
 * vlastní, nikdy ne ve verzovaném config.neon). Prázdný token = endpoint je vypnutý.
 */
final class CronSettings
{

	public function __construct(
		private readonly string $token,
	) {
	}


	public function isEnabled(): bool
	{
		return $this->token !== '';
	}


	public function isValidToken(string $token): bool
	{
		return $this->isEnabled() && hash_equals($this->token, $token);
	}

}
