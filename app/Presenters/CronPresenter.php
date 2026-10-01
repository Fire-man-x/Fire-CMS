<?php
declare(strict_types=1);

namespace App\Presenters;

use App\Cron\CronRunner;
use App\Cron\CronSettings;
use App\Cron\CronTaskResult;
use Nette\Application\IPresenter;
use Nette\Application\Request;
use Nette\Application\Response;
use Nette\Application\Responses\JsonResponse;
use Nette\Http\IRequest;
use Nette\Http\IResponse;

/**
 * Endpoint pro systémový cron: `GET /cron?token=<cronToken>` (nebo hlavička `X-Cron-Token`) spustí všechny
 * úlohy, které jsou na řadě (CronRunner). Doporučené volání každou minutu, např.
 * `* * * * * curl -fsS "https://<doména>/cron?token=<token>" > /dev/null`.
 *
 * Záměrně holý IPresenter (ne BasePresenter) - bez layoutu, jazyků, session a přihlášení. Odpovídá JSONem:
 * 200 = vše proběhlo, 500 = aspoň jedna úloha selhala (cron/monitoring to pozná podle kódu), 409 = předchozí
 * běh ještě neskončil, 403 = špatný token nebo vypnutý endpoint (prázdný `cronToken`).
 */
final class CronPresenter implements IPresenter
{

	public function __construct(
		private readonly CronRunner $runner,
		private readonly CronSettings $settings,
		private readonly IRequest $httpRequest,
		private readonly IResponse $httpResponse,
	) {
	}


	public function run(Request $request): Response
	{
		$token = $this->httpRequest->getHeader('X-Cron-Token') ?? $this->httpRequest->getQuery('token');
		if (!is_string($token) || !$this->settings->isValidToken($token)) {
			$this->httpResponse->setCode(IResponse::S403_Forbidden);
			return new JsonResponse(['error' => 'Forbidden']);
		}

		// úlohy doběhnou, i když cron (curl) spojení mezitím ukončí
		ignore_user_abort(true);
		set_time_limit(300);

		$results = $this->runner->run();
		if ($results === null) {
			$this->httpResponse->setCode(IResponse::S409_Conflict);
			return new JsonResponse(['error' => 'Previous cron run is still in progress']);
		}

		$failed = array_filter($results, fn(CronTaskResult $result): bool => $result->status === CronTaskResult::StatusError);
		if ($failed !== []) {
			$this->httpResponse->setCode(IResponse::S500_InternalServerError);
		}

		return new JsonResponse([
			'time' => date(DATE_ATOM),
			'tasks' => array_map(fn(CronTaskResult $result): array => $result->toArray(), $results),
		]);
	}

}
