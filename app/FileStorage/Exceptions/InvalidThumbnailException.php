<?php
declare(strict_types=1);

namespace App\FileStorage\Exceptions;

/**
 * Neplatný nebo nepovolený náhled obrázku (viz `fileStorage: thumbnails:` v neonu).
 */
class InvalidThumbnailException extends \InvalidArgumentException
{
}
