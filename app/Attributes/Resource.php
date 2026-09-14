<?php
declare(strict_types=1);

namespace App\Attributes;

/**
 * Defines the ACL resource(s) required to access the annotated presenter or method.
 * Used together with #[Secured] and #[Privilege].
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Resource
{
	/** @var string[] */
	public array $names;


	public function __construct(string ...$names)
	{
		$this->names = $names;
	}
}
