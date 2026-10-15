<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters\UrlValidationTrait;

use App\Model\UrlManager;

/**
 * Implementace App\AdminModule\Presenters\UrlValidation. Presenter dodá getUrlType() a vlastnost `?int $id`
 * s ID editovaného záznamu (null = nový záznam), vlastní URL záznamu se při návrhu nepočítá jako obsazená.
 */
trait UrlValidationTrait
{
	private UrlManager $urlValidationManager;


	public function injectUrlValidation(UrlManager $urlManager): void
	{
		$this->urlValidationManager = $urlManager;
	}


	public function handleValidateUrl(string $text): void
	{
		if (trim($text) === '') {
			$this->terminate();
		}

		$this->payload->url = $this->urlValidationManager->validateUrl($text, $this->getUrlType(), $this->id, $this->id);
		$this->sendPayload();
	}
}
