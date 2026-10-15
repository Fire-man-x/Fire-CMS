<?php
declare(strict_types=1);

namespace App\Model;


use App\Model\Database\Redirections;
use App\Model\Database\Urls;
use App\Service\LanguageService;
use Nette\Database\Explorer;
use Nette\InvalidArgumentException;
use Nette\SmartObject;
use Nette\Utils\ArrayHash;
use Nette\Utils\Strings;

/**
 * Class UrlManager
 */
class UrlManager
{
	use SmartObject;


	/**
	 * List of url
	 */
	private array $url;

	/**
	 * UrlManager constructor.
	 */
	public function __construct(
		protected Urls $urlModel,
		protected Redirections $redirectionsModel,
		protected LanguageService $languages,
		protected Explorer $db)
	{
	}


	/**
	 * Url
	 */
	public function getUrl($urlId): array
	{
		if (!isset($this->url)) {
			$this->url = $this->urlModel->getById($urlId)?->toArray();
		}

		return $this->url;
	}


	/**
	 * Url info
	 * @return false|\Nette\Database\Table\ActiveRow
	 * @throws InvalidArgumentException
	 */
	public function getUrlInfoByTypeAndKey(string $type, int $key, ?string $languageId = null)
	{
		if(!isset($languageId))
		{
			$languageId = $this->languages->getDefaultLanguage();
		}

		$urlInfo = $this->urlModel->findAll()
			->where('languageId', $languageId)
			->where('type', $type)
			->where('key', $key)
			->fetch();

		if(!$urlInfo)
		{
			throw new InvalidArgumentException("Url with type '$type' and key '$key' does not exist.");
		}

		return $urlInfo;
	}


	/**
	 * Smaže hezká URL položky ve všech jazycích (např. po smazání stránky), aby slug neblokoval nové položky
	 */
	public function deleteUrls(string $type, int $key): void
	{
		$this->urlModel->findAll()
			->where('type', $type)
			->where('key', $key)
			->delete();
	}


	/**
	 * Exist url?
	 */
	public function existUrlByTypeAndKey(string $type, int $key, ?string $languageId = null): bool
	{
		try
		{
			$this->getUrlInfoByTypeAndKey($type, $key, $languageId);
			return true;
		}
		catch(InvalidArgumentException $e)
		{
			return false;
		}
	}


	/**
	 * Url info
	 * @return false|\Nette\Database\Table\ActiveRow
	 */
	public function getUrlInfoByUrl(string $url, ?string $languageId = null)
	{
		if(!isset($languageId))
		{
			$languageId = $this->languages->getDefaultLanguage();
		}

		$urlInfo = $this->urlModel->findAll()
			->where('languageId', $languageId)
			->where('url', $url)
			->fetch();

		if(!$urlInfo)
		{
			throw new InvalidArgumentException("Url with '$url' does not exist.");
		}

		return $urlInfo;
	}


	/**
	 * Url
	 * @return string|false
	 */
	public function getUrlByTypeAndKey(string $type, int $key, ?string $languageId = null)
	{
		$urlInfo = $this->getUrlInfoByTypeAndKey($type,$key,$languageId);
		return $urlInfo["url"];
	}


	/**
	 * Save URL
	 */
	public function saveUrl(string $type, int $key, string $languageId, string $url)
	{
		$webalizedUrl = $this->validateUrl($url, $type, $key, $key);

		try
		{
			$urlInfo = $this->getUrlInfoByTypeAndKey($type, $key, $languageId);
			$oldUrl = $urlInfo['url'];

			//redirection
			if($oldUrl != $webalizedUrl) {
				//update old redirection if exist
				$this->redirectionsModel->findAll()
					->where("languageId", $languageId)
					->where("oldUrl", $oldUrl)
					->update(array("newUrl" => $webalizedUrl));

				//update all old to new
				$this->redirectionsModel->findAll()
					->where("languageId", $languageId)
					->where("newUrl", $oldUrl)
					->update(array("newUrl" => $webalizedUrl));

				//insert
				$this->redirectionsModel->insert(ArrayHash::from([
					"languageId" => $languageId,
					"oldUrl" => $oldUrl,
					"newUrl" => $webalizedUrl
				]));

				//remove recursive
				$this->redirectionsModel->findAll()
					->where("languageId", $languageId)
					->where("oldUrl", $webalizedUrl)
					->delete();

			}

			//update url
			$this->urlModel->update($urlInfo["id"], array("url" => $webalizedUrl));

		}catch(InvalidArgumentException $e) {
			//insert url
			$this->urlModel->insert(ArrayHash::from(array(
				"type" => $type,
				"key" => $key,
				"languageId" => $languageId,
				"url" => $webalizedUrl
			)));
		}
	}

	/**
	 * Validate URL handler
	 */
	public function validateUrl(string $url, string $type, ?int $key, int $notInKey = null): string
	{
		$exist = true;
		$index = 0;
		while ($exist){
			$webalizedUrl = Strings::webalize($url . ($index == 0 ? "" : " " . $index));
			$sql = $this->urlModel->findAll()
				->where("url", $webalizedUrl);
			if($notInKey){
				$sql->where("key != ?", $notInKey);
			}
			$exist = $sql->fetch();

			$index++;
		}

		return $webalizedUrl;
	}


	/**
	 * Redirection url info
	 * @return false|\Nette\Database\Table\ActiveRow
	 */
	public function getRedirectionInfoByUrl(string $url, ?string $languageId = null)
	{
		if(!isset($languageId))
		{
			$languageId = $this->languages->getDefaultLanguage();
		}

		$urlInfo = $this->redirectionsModel->findAll()
			->where('languageId', $languageId)
			->where('oldUrl', $url)
			->fetch();

		if(!$urlInfo)
		{
			throw new InvalidArgumentException("Url with '$url' does not exist.");
		}

		return $urlInfo;
	}
}
