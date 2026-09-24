<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Exceptions\RecordNotFoundException;
use App\Model\Languages;
use Nette\SmartObject;

/**
 * Class Languages
 */
class LanguageService
{
	use SmartObject;

	/**
	 * Languages model
	 */
	private Languages $model;

	/**
	 * Default language
	 */
	private string $defaultLanguage;

	/**
	 * List of languages
	 */
	private array $languages;

	/**
	 * List of active languages
	 */
	private array $activeLanguages;


	/**
	 * Languages
	 */
	public function __construct(Languages $model)
	{
		$this->model = $model;
	}


	/**
	 * Languages
	 */
	public function getLanguages(): array
	{
		if (!isset($this->languages)) {
			$this->languages = $this->model->findAll()
				->select($this->model->getColumnId())
				->select("shortcut")
				->order("default DESC")
				->order($this->model->getColumnId())
				->fetchPairs($this->model->getColumnId(), "shortcut");
		}

		return $this->languages;
	}


	/**
	 * Language by id
	 * @throws \InvalidArgumentException
	 */
	public function getLanguage(string $language): string
	{
		if (!$this->existLanguage($language)){
			throw new \InvalidArgumentException("Language with id '$language' doesn't exist.");
		}

		$languages = $this->getLanguages();

		return $languages[$language];
	}


	/**
	 * Default language
	 */
	public function getDefaultLanguage(): string
	{
		if (!isset($this->defaultLanguage)) {
			$defaultLanguage = $this->model->findAll()
				->select($this->model->getColumnId())
				->where("active", true)
				->where("default", true)->fetch();
			if(!$defaultLanguage){
				throw new RecordNotFoundException();
			}
			$this->defaultLanguage = $defaultLanguage->{$this->model->getColumnId()};
		}

		return $this->defaultLanguage;
	}


	/**
	 * Active languages
	 * @return array<string,array<string,string>>
	 */
	public function getActiveLanguages(): array
	{
		if (!isset($this->activeLanguages)) {
			$this->activeLanguages = $this->model->findAll()
				->where("active", true)
				->order("default DESC")
				->order($this->model->getColumnId())
				->fetchAssoc($this->model->getColumnId());
		}

		return $this->activeLanguages;
	}


	/**
	 * Test if language exist
	 */
	public function existLanguage(string $language): bool
	{
		return array_key_exists($language, $this->getLanguages());
	}


	/**
	 * Admin languages
	 */
	public function getAdminLanguages(): array
	{
		return [
			'cs' => 'cs',
			'en' => 'en'
		];
	}

	/**
	 * Admin languages
	 */
	public function getDefaultAdminLanguage(): string
	{
		return 'cs';
	}

}
