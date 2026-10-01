<?php
declare(strict_types=1);

namespace App\Presenters;

use Nette;
use Nette\Application\Request;
use Nette\Application\Response;
use Nette\Application\Responses;
use Tracy\ILogger;


class ErrorPresenter implements Nette\Application\IPresenter
{
	use Nette\SmartObject;

	private ILogger $logger;


	public function __construct(ILogger $logger)
	{
		$this->logger = $logger;
	}


	public function run(Request $request): Response
	{
		$exception = $request->getParameter('exception');

		if ($exception instanceof Nette\Application\BadRequestException) {
			list($module, , $sep) = Nette\Application\Helpers::splitName($request->getPresenterName());

			$code = $exception->getCode();
			$this->logger->log("HTTP code $code: {$exception->getMessage()} in {$exception->getFile()}:{$exception->getLine()}", 'access');
			return new Responses\ForwardResponse($request->setPresenterName($module . $sep . 'Error4xx'));
		}

		$this->logger->log($exception, ILogger::EXCEPTION);
		return new Responses\CallbackResponse(function () {
			require __DIR__ . '/templates/Error/500.phtml';
		});
	}

}
