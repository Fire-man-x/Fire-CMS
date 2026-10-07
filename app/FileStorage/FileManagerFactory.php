<?php
declare(strict_types=1);

namespace App\FileStorage;

use App\FileStorage\Storages\IStorage;

interface FileManagerFactory
{
	public function create(IStorage $storage): FileManager;
}
