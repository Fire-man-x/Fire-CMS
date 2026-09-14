<?php
declare(strict_types=1);

namespace App\Components\FileManager;

use App\Components\FileManager\Storages\IStorage;

interface FileManagerFactory
{
	public function create(IStorage $storage): FileManager;
}
