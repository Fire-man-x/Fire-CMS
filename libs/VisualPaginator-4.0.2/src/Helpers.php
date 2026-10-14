<?php declare(strict_types = 1);

namespace AlesWita\VisualPaginator;

use Nette\Utils\Paginator;

final class Helpers
{

	/**
	 * @return list<int>
	 */
	public static function createSteps(Paginator $paginator): array
	{
		if ($paginator->getPageCount() < 2) {
			return [$paginator->getPage()];
		}

		$arr = range(
			max($paginator->getFirstPage(), $paginator->getPage() - 2),
			min((int) $paginator->getLastPage(), $paginator->getPage() + 2),
		);

		$count = 1;
		$quotient = ($paginator->getPageCount() - 1) / $count;

		for ($i = 0; $i <= $count; $i++) {
			$arr[] = (int) round($quotient * $i) + $paginator->getFirstPage();
		}

		sort($arr);

		return array_values(array_unique($arr));
	}

}
