<?php
declare(strict_types=1);

namespace App\Components\FileManager\Flysystem;

use League\Flysystem\AwsS3V3\VisibilityConverter;
use League\Flysystem\Visibility;

/**
 * Posílá u každého zápisu do S3 stejné ACL (`acl` v konfiguraci úložiště), bez ohledu na viditelnost Flysystemu.
 *
 * Nové buckety v AWS mají ACL vypnuté (Object Ownership "Bucket owner enforced") a zápis s jiným ACL než
 * `bucket-owner-full-control` odmítnou (AccessControlListNotSupported) - i výchozí `private` Flysystemu.
 * Veřejné čtení se pak řeší politikou bucketu nebo CDN, ne ACL jednotlivých objektů. Úložiště, která
 * veřejnost řídí přes ACL, si nastaví `acl: public-read`.
 */
final class AclVisibilityConverter implements VisibilityConverter
{
	private const string AllUsersGroup = 'http://acs.amazonaws.com/groups/global/AllUsers';


	public function __construct(private readonly string $acl)
	{
	}


	public function visibilityToAcl(string $visibility): string
	{
		return $this->acl;
	}


	/**
	 * @param array<mixed> $grants
	 */
	public function aclToVisibility(array $grants): string
	{
		foreach ($grants as $grant) {
			$grantee = is_array($grant) ? ($grant['Grantee'] ?? null) : null;
			if (is_array($grantee)
				&& ($grantee['URI'] ?? null) === self::AllUsersGroup
				&& ($grant['Permission'] ?? null) === 'READ'
			) {
				return Visibility::PUBLIC;
			}
		}

		return Visibility::PRIVATE;
	}


	public function defaultForDirectories(): string
	{
		return Visibility::PUBLIC;
	}
}
