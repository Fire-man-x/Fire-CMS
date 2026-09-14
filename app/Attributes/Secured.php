<?php
declare(strict_types=1);

namespace App\Attributes;

/**
 * Marks a presenter class or an action/handle/render method as access-checked.
 * Combine with #[Resource] and #[Privilege] to define the required permission.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class Secured
{
}
