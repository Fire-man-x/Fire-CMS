<?php
declare(strict_types=1);

namespace App\Plugins\Sliders\Model;

use App\Model\BaseModel;
use App\Service\LanguageService;
use Nette\Database\Explorer;
use Nette\Utils\ArrayHash;

/**
 * SliderItems Model
 */
class SliderItems extends BaseModel
{
	public function __construct(Explorer $database)
	{
		parent::__construct($database);

		$this->setTableName('slider_items');
		$this->setColumnId('slider_id');
	}


	/**
	 * Inserts new
	 */
	public function insert(ArrayHash $data): int
	{
		$data->position = $this->getNextPosition($data->language_id);
		return parent::insert($data);
	}


	/**
	 * Delete
	 * @param array $id
	 */
	public function delete($id): void
	{
		if(!is_array($id)){
			throw new \Nette\InvalidArgumentException("For delete you must specify more parameters");
		}
		$this->getAll()
			->where($id)
			->delete();
	}


	/**
	 * Get next position
	 */
	protected function getNextPosition(string $language): int
	{
		return $this->getAll()->select("IFNULL(MAX(position),0)+1 AS position")->where("language_id", $language)->fetchField();
	}


	/**
	 * Change positions of items
	 * @param int $slider_id Id of slider
	 * @param string $language Id of language
	 * @param array $positions Array with sorted old positions
	 */
	public function changePositions($slider_id, $language, $positions): void
	{
		$maxPos = $this->getNextPosition($language);
		foreach ($positions as $newPosition => $oldPosition){
			$this->getAll()
				->where("slider_id", $slider_id)
				->where("language_id", $language)
				->where("position", $oldPosition)
				->update(array(
					"position"=>$maxPos+$newPosition +1 //because of index from 0
				));
		}

		//return to base position
		$this->getAll()
				->where("slider_id", $slider_id)
				->where("language_id", $language)
				->update(array(
					"position"=> new \Nette\Database\SqlLiteral("position - ?", array($maxPos))
				));
	}

}
