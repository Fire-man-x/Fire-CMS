<?php
declare(strict_types=1);

namespace App\Router;

use App\Service\DomainService;
use App\Service\LanguageService;
use App\Modules\UrlModule\UrlManager;
use Nette;
use Nette\Http;
use Nette\Routing\Router;
use Nette\SmartObject;


class CustomRouter implements Router
{
	use SmartObject;

	private UrlManager $urlManager;

	private LanguageService $languages;

	/** (urls.type => presenter name) */
	private array $presenters = array(
		'default' => 'Front:Default',
		'user' => 'Users',
		'article' => 'Front:Articles',
		'category' => 'Front:Categories',
	);

	public function __construct(UrlManager $urlManager, LanguageService $languages, private readonly DomainService $domains)
	{
		$this->urlManager = $urlManager;
		$this->languages = $languages;
	}

	/**
	 * Maps HTTP request to a Request object.
	 *
	 */
	public function match(Nette\Http\IRequest $httpRequest): ?array
	{
		$url = $httpRequest->getUrl()->getPathInfo();

		//locale podle domény (viz App\Service\DomainService) - má přednost před URL prefixem
		$locale = $this->domains->getLanguageByDomain($httpRequest->getUrl()->getHost());

		if ($locale === null) {
			//fallback: locale podle URL prefixu /xx/..., stejně jako doteď
			$existLocale = Nette\Utils\Strings::match($url, "~^([a-z]{2})/([a-z0-9-]*)~");
			if ($existLocale) {
				$candidate = (string) $existLocale[1];
				$rest = (string) $existLocale[2];

				//language not exists
				if (!$this->languages->existLanguage($candidate)) {
					return null;
				}

				//jazyk už má vlastní doménu - starý prefixovaný odkaz na ni natrvalo přesměrujeme
				$canonicalDomain = $this->domains->getDomainForLanguage($candidate);
				if ($canonicalDomain !== null) {
					return $this->redirectToDomain($httpRequest, $canonicalDomain, '/' . $rest);
				}

				$locale = $candidate;
				$url = $rest;
			}
		}

		//params
		$params = $httpRequest->getQuery();

		//try find
		try
		{
			$row = $this->urlManager->getUrlInfoByUrl($url, $locale);
		} catch(\InvalidArgumentException $e) {
			//try find redirection
			try
			{
				$redirectionInfo = $this->urlManager->getRedirectionInfoByUrl($url, $locale);
				$redirectionInfo->update(array("last_usage_date" => new Nette\Database\SqlLiteral("NOW()")));
				$row = $this->urlManager->getUrlInfoByUrl($redirectionInfo->new_url, $locale);
			} catch(\InvalidArgumentException $e) {
				//try find redirection by relativeUrl
				try
				{
					$redirectionInfo = $this->urlManager->getRedirectionInfoByUrl($httpRequest->getUrl()->getRelativeUrl(), $locale);
					$redirectionInfo->update(array("last_usage_date" => new Nette\Database\SqlLiteral("NOW()")));
					$row = $this->urlManager->getUrlInfoByUrl($redirectionInfo->new_url, $locale);
					$params = array();
				} catch(\InvalidArgumentException $e) {
					return null;
				}
			}
		}
		//not exist
		if (!$row || !isset($this->presenters[$row->type])){
			return null;
		}

		if($row->type == 'article' || $row->type == 'category') {
			$params['action'] = 'detail';
		}
		else {
			$params['action'] = 'default';
		}

		$params['presenter'] = $this->presenters[$row->type];
		$params['locale'] = $row->language_id;
		$params['id'] = $row->key;

		return $params;
	}


	/**
	 * Params pro Front:Redirect - vrátí 301 na stejnou cestu na cílové doméně jazyka
	 */
	private function redirectToDomain(Nette\Http\IRequest $httpRequest, string $domain, string $path): array
	{
		$target = new Http\Url();
		$target->setScheme($httpRequest->getUrl()->getScheme());
		$target->setHost($domain);
		$target->setPath($path);
		$target->setQuery((array) $httpRequest->getQuery());

		return array(
			'presenter' => 'Front:Redirect',
			'action' => 'default',
			'url' => $target->getAbsoluteUrl(),
		);
	}


	/**
	 * Constructs absolute URL from Request object.
	 *
	 */
	public function constructUrl(array $params, Nette\Http\UrlScript $refUrl): ?string
	{
		if (!in_array($params['presenter'], $this->presenters)) {
			return null;
		}

		$localeId = $params['locale'] ?? $this->languages->getDefaultLanguage();
		$domain = $this->domains->getDomainForLanguage($localeId);
		$locale = $domain === null && $this->languages->getDefaultLanguage() != $localeId ? $localeId . "/" : "";

		$url = new Http\Url($refUrl->getBaseUrl());
		if ($domain !== null) {
			$url->setHost($domain);
		}

		//Homepage
		if($params['presenter'] == "Front:Categories" && isset($params["id"]) && $params["id"] == 1)
		{

			unset($params['action'], $params['id'], $params['locale']);

			$url->setPath($locale);
			$url->setQuery($params);
			return $url->getAbsoluteUrl();
		}

		if(!isset($params['id'])) {
			return null;
		}


		//normal
		$type = array_search($params['presenter'], $this->presenters);
		$urlFromDb = $this->urlManager->getUrlByTypeAndKey($type, $params['id'], $localeId);

		unset($params['action'], $params['id'], $params['locale']); // we don't want to have 'action' and 'id' in query parameters

		$url->setPath($locale.$urlFromDb);
		$url->setQuery($params);
		return $url->getAbsoluteUrl();
	}
}
