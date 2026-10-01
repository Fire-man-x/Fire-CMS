<?php
declare(strict_types=1);

namespace App\FrontModule\Presenters;

use Nette\Application\UI\Presenter;
use Nette\Http\IResponse;

/**
 * Vydá 301 redirect na absolutní URL - používá App\Router\CustomRouter, když jazyk s vlastní
 * doménou (viz App\Service\DomainService) dostane request na starou prefixovou URL (/xx/...).
 */
final class RedirectPresenter extends Presenter
{

	public function actionDefault(string $url): void
	{
		$this->redirectUrl($url, IResponse::S301_MovedPermanently);
	}

}
