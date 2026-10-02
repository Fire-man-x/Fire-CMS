<?php
declare(strict_types=1);

/**
 * Health check Renderu (healthCheckPath: /healthz v render.yaml, Alias v apache-vhost.conf).
 *
 * Ověří, že se aplikace sestaví s config.local.neon a dosáhne na databázi. Dokud nová verze neprojde, Render na ni
 * nepřepne provoz a běží předchozí deploy. Detail chyby jde jen do logu (stderr Apache), ven ne.
 */

header('Cache-Control: no-store');
header('Content-Type: text/plain; charset=utf-8');

try {
	/** @var Nette\DI\Container $container */
	$container = require __DIR__ . '/../../app/bootstrap.php';
	$container->getByType(Nette\Database\Connection::class)->query('SELECT 1');
} catch (Throwable $e) {
	http_response_code(503);
	error_log('healthz: ' . $e::class . ': ' . $e->getMessage());
	echo "error\n";
	exit;
}

echo "ok\n";
