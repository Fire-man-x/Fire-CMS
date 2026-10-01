<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use App\Model;

class DefaultPresenter extends BasePresenter
{

	/** @inject */
	public Model\Database\Users $usersModel;

	/** @inject */
	public Model\Database\Articles $articlesModel;

	//todo: udělat nějak přes pluginy
	/** @inject */
	//public Statistics $statisticsModel;


	public function renderDefault(): void
	{
		$this->template->counts = array(
			"users" => $this->usersModel->findAll()->select("COUNT(*) AS count")->fetch()['count'],
			"articles" => $this->articlesModel->findAll()->select("COUNT(*) AS count")->where("historyId", null)->fetch()['count'],
			"statistics" => 0, //$this->statisticsModel->getAvgPerDay()
		);
	}

}
