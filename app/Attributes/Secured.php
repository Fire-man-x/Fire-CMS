<?php
declare(strict_types=1);

namespace App\Attributes;

/**
 * Oprávnění ACL potřebné pro presenter nebo jeho action/handle/render metodu, vynucuje
 * App\AdminModule\Presenters\BasePresenter::checkRequirements().
 *
 *	#[Secured('Articles', 'view')]
 *	#[Secured('Articles', 'edit', recordIdParameter: 'id', privilegeWithoutRecord: 'add')]
 *
 * Atribut lze opakovat, každá kombinace zdroj + právo se ověřuje zvlášť a musí projít všechny.
 *
 * recordIdParameter - název parametru požadavku (URL) s ID záznamu. Autor záznamu (App\Security\ResourceOwners)
 *	smí se svým záznamem cokoliv, i bez daného práva v roli.
 * privilegeWithoutRecord - právo, které se ověří, když parametr s ID v požadavku chybí (např. nový záznam => 'add').
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Secured
{
	public function __construct(
		public readonly string $resource,
		public readonly string $privilege,
		public readonly ?string $recordIdParameter = null,
		public readonly ?string $privilegeWithoutRecord = null,
	) {
	}
}
