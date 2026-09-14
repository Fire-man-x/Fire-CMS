<?php
declare(strict_types=1);

namespace App\Plugins\Statistics;

use Nette\Http\Request;
use Nette\Http\Session;
use Nette\SmartObject;
use Nette\Utils\ArrayHash;

/**
 * Statistics
 *
 * Trace all paths
 */
class Statistics
{
	use SmartObject;

	/**
	 * Constructs the file manager from the given arguments.
	 */
	public function __construct(/*private Request $request, private Model\Statistics $model, private Session $session*/)
	{
	}


	/**
	 * Trace url
	 */
	public function addHit(): void
	{
		//todo: opet povolit na produkci
		return;

		$agent = $this->request->getHeader('User-Agent');
		if($agent)
		{
			$this->model->insert(ArrayHash::from(array(
				"session" => $this->session->getId(),
				"ip" => $this->request->getRemoteAddress(),
				"agent" => $agent
			)));
		}
	}

}
