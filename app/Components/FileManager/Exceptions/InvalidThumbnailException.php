<?php
declare(strict_types=1);

namespace App\Components\FileManager\Exceptions;

/**
 * Neplatný nebo nepovolený náhled obrázku (viz `fileManager: thumbnails:` v neonu).
 */
class InvalidThumbnailException extends \InvalidArgumentException
{
}
