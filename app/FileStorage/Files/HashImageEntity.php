<?php
declare(strict_types=1);

namespace App\FileStorage\Files;


/**
 * Basic implementation of the image meta information as Doctrine entity.
 */
class HashImageEntity extends ImageEntity implements HashFile
{
	use BaseHashEntityTrait;
}
