<?php
declare(strict_types=1);

namespace App\Plugins\Sliders\Model;

use App\Model\BaseModel;
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

		$this->setTableName('firecms_plugin_sliderItems');
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
	 */
	public function delete(int|array $id): ?int
	{
		if(!is_array($id)){
			throw new \Nette\InvalidArgumentException("For delete you must specify more parameters");
		}
		return $this->findByIds($id)
			->delete();
	}


	/**
	 * Get next position
	 */
	protected function getNextPosition(string $language): int
	{
		return $this->findAll()->select("IFNULL(MAX(position),0)+1 AS position")->where("language_id", $language)->fetchField();
	}


	/**
	 * Change positions of items
	 * @param array<int|int> $positions Array with sorted old positions
	 */
	public function changePositions(int $slider_id, string $language, array $positions): void
	{
		$maxPos = $this->getNextPosition($language);
		foreach ($positions as $newPosition => $oldPosition){
			$this->findAll()
				->where("slider_id", $slider_id)
				->where("language_id", $language)
				->where("position", $oldPosition)
				->update(array(
					"position"=>$maxPos+$newPosition +1 //because of index from 0
				));
		}

		//return to base position
		$this->findAll()
				->where("slider_id", $slider_id)
				->where("language_id", $language)
				->update(array(
					"position"=> new \Nette\Database\SqlLiteral("position - ?", array($maxPos))
				));
	}

}
