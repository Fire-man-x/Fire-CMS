<?php
declare(strict_types=1);

namespace App\Components\FileManager\Files;

/**
 * Basic implementation of the file information
 */
class HashFileEntity extends FileEntity implements HashFile
{
	use BaseHashEntityTrait;
}
