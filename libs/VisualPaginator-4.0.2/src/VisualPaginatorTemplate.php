<?php declare(strict_types = 1);

namespace AlesWita\VisualPaginator;

use Nette\Bridges\ApplicationLatte\Template;
use Nette\Utils\Paginator;

/**
 * Typovaná šablona paginatoru - Nette ji pro VisualPaginator najde samo podle jména
 * (Control::formatTemplateClass()), vlastní šablona ji deklaruje přes {templateType}.
 */
final class VisualPaginatorTemplate extends Template
{

	/** @var list<int> */
	public array $steps;

	public bool $itemsPerPage;

	public Paginator $paginator;

	public bool $ajax;

}
