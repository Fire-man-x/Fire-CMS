<?php
declare(strict_types=1);

namespace App\Components\Menu\Model;

use Nette\Database\Table\ActiveRow;

/**
 * Položka menu (`firecms_menuItems`) s popiskem v jednom jazyce a hloubkou ve stromu
 */
final readonly class MenuItem
{
	public function __construct(
		public int $id,
		public int $menuId,
		public ?int $parentId,
		public int $position,
		public bool $active,
		public string $linkType,
		public string $target,
		public bool $newWindow,
		public ?string $label,
		public int $level = 0,
		public ?string $defaultLabel = null,
	) {
	}


	/**
	 * Z řádku Menus::getItemsWithLabel() (sloupce `label`/`defaultLabel` jsou poddotazy, můžou chybět = null)
	 */
	public static function fromRow(ActiveRow $row): self
	{
		$label = $row->offsetExists('label') ? $row->label : null;
		$defaultLabel = $row->offsetExists('defaultLabel') ? $row->defaultLabel : null;

		return new self(
			self::toInt($row->id),
			self::toInt($row->menuId),
			$row->parentId === null ? null : self::toInt($row->parentId),
			self::toInt($row->position),
			(bool) $row->active,
			self::toString($row->linkType),
			self::toString($row->target),
			(bool) $row->newWindow,
			is_string($label) && $label !== '' ? $label : null,
			0,
			is_string($defaultLabel) && $defaultLabel !== '' ? $defaultLabel : null,
		);
	}


	public function withLevel(int $level): self
	{
		return new self($this->id, $this->menuId, $this->parentId, $this->position, $this->active,
			$this->linkType, $this->target, $this->newWindow, $this->label, $level, $this->defaultLabel);
	}


	public function getLinkType(): ?MenuLinkType
	{
		return MenuLinkType::tryFrom($this->linkType);
	}


	private static function toInt(mixed $value): int
	{
		if (is_int($value)) {
			return $value;
		}
		if (is_string($value) && preg_match('#^-?\d+$#D', $value)) {
			return (int) $value;
		}
		throw new \UnexpectedValueException('Expected integer column value, got ' . get_debug_type($value) . '.');
	}


	private static function toString(mixed $value): string
	{
		if (is_string($value)) {
			return $value;
		}
		throw new \UnexpectedValueException('Expected string column value, got ' . get_debug_type($value) . '.');
	}
}
