<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters\SectionAwareTrait;

use Nette\Application\Attributes\Persistent;

/**
 * Presenter administrace, který pracuje v jedné sekci (App\Model\Sections) - články, kategorie článků.
 * Sekce se bere z editované položky (má přednost), jinak z parametru `section`, jinak první sekce.
 * Vyžaduje App\AdminModule\Presenters\BasePresenter (vlastnost `sectionsModel`).
 */
trait SectionAwareTrait
{
	#[Persistent]
	public ?int $section = null;

	private int $sectionId;


	/**
	 * @param int|null $itemSectionId sekce editované položky (null = nová položka)
	 */
	protected function resolveSection(?int $itemSectionId = null): void
	{
		$sectionId = $itemSectionId ?? $this->sectionsModel->resolveId($this->section);
		if ($sectionId === null) {
			$this->error($this->section !== null ? "Section '$this->section' doesn't exist." : 'No section exists - create one first.');
		}

		$this->sectionId = $sectionId;
		$this->section = $sectionId;

		$sectionTitle = $this->sectionsModel->getList()[$sectionId] ?? '#' . $sectionId;
		$this->template->currentSectionId = $sectionId;
		$this->template->currentSectionTitle = $sectionTitle;
	}


	protected function getSectionId(): int
	{
		return $this->sectionId;
	}
}
