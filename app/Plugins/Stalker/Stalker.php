<?php
declare(strict_types=1);

namespace App\Plugins\Stalker;

use App\Plugins\Stalker\Model\Stalkers;
use Nette\Http\Request;
use Nette\Security\User;
use Nette\SmartObject;
use Nette\Utils\ArrayHash;

/**
 * Stalker
 *
 * Trace all paths
 */
class Stalker
{
	use SmartObject;


	/**
	 * Constructs the file manager from the given arguments.
	 */
	public function __construct(/*private Request $request, private Stalkers $model*/)
	{
	}


	/**
	 * Trace url
	 */
	public function traceUrl(User $user): void
	{
		return;
		if($user->getId()){
			$this->model->insert(ArrayHash::from(array(
				"created_by" => $user->getId(),
				"ip" => $this->request->getRemoteAddress(),
				"url" => $this->request->getUrl()->getRelativeUrl(),
				"data"=> $this->request->isMethod('POST') ? serialize($this->request->getPost()) : null
			)));
		}
	}

}
