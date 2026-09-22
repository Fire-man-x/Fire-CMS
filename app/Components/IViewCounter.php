<?php
declare(strict_types=1);

namespace App\Components;


interface IViewCounter{
	public function addViewCount(int $itemId, string $language): void;
}