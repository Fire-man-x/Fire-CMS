<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

/**
 * Presenter administrace s návrhem hezké URL z názvu: pole formuláře s třídou `validate-url` posílá
 * text AJAXem na signál `validateUrl` (www/administration/js/main.js), odpověď `payload.url` se vloží do
 * pole s třídou `validate-url-output`. Implementace je v UrlValidationTrait.
 */
interface UrlValidation
{
	/**
	 * Pošle v payloadu `url` volnou webalizovanou URL pro editovaný záznam
	 */
	public function handleValidateUrl(string $text): void;

	/**
	 * Typ URL obsahu (firecms_urls.type, např. Pages::URL_TYPE)
	 */
	public function getUrlType(): string;
}
