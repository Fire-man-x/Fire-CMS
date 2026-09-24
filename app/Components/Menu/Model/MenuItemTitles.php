<?php
declare(strict_types=1);

namespace App\Components\Menu\Model;

use App\Model\Articles;
use App\Model\Categories;
use App\Model\Pages;
use App\Model\Sections;

/**
 * Názvy položek menu pro administraci (grid položek, výběr nadřazené položky ve formuláři). Pořadí:
 * popisek v jazyce → popisek ve výchozím jazyce → název cíle (kategorie/článek/stránka/sekce) v jazyce →
 * samotný `target` (URL, route, id smazaného obsahu).
 */
class MenuItemTitles
{
	public function __construct(
		private readonly Categories $categoriesModel,
		private readonly Articles $articlesModel,
		private readonly Pages $pagesModel,
		private readonly Sections $sectionsModel,
	) {
	}


	/**
	 * @param list<MenuItem> $items
	 * @return array<int, string> id položky => název
	 */
	public function getTitles(array $items, string $language): array
	{
		$contentTitles = $this->getContentTitles($items, $language);

		$titles = [];
		foreach ($items as $item) {
			$titles[$item->id] = $item->label
				?? $item->defaultLabel
				?? $contentTitles[$item->linkType][(int) $item->target]
				?? $item->target;
		}

		return $titles;
	}


	/**
	 * Názvy obsahu, na který položky odkazují (bez popisků položek)
	 * @param list<MenuItem> $items
	 * @return array<string, array<int, string>> linkType => [id cíle => název]
	 */
	public function getContentTitles(array $items, string $language): array
	{
		$models = [
			MenuLinkType::Category->value => $this->categoriesModel,
			MenuLinkType::Article->value => $this->articlesModel,
			MenuLinkType::Page->value => $this->pagesModel,
			MenuLinkType::Section->value => $this->sectionsModel,
		];

		$ids = [];
		foreach ($items as $item) {
			if (isset($models[$item->linkType]) && ctype_digit($item->target)) {
				$ids[$item->linkType][] = (int) $item->target;
			}
		}

		$titles = [];
		foreach ($ids as $linkType => $targetIds) {
			$model = $models[$linkType];
			$selection = $model->findAll()->select('id')->where('id', $targetIds);
			$model->selectTitle($selection, '`' . $model->getTableName() . '`.`id`', $language);
			foreach ($selection as $row) {
				if (is_string($row->title) && is_numeric($row->id)) {
					$titles[$linkType][(int) $row->id] = $row->title;
				}
			}
		}

		return $titles;
	}
}
