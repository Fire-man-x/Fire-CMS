<?php
declare(strict_types=1);

namespace App\FileStorage\Flysystem;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;
use Nette\InvalidArgumentException;
use Nette\StaticClass;

/**
 * Vytváří Flysystem úložiště (lokální adresář nebo S3 bucket) z konfigurace `fileStorage: <název>:`,
 * viz App\FileStorage\DI\Extension.
 *
 * @phpstan-type StorageConfig array{
 *     adapter: string,
 *     root: ?string,
 *     publicUrl: ?string,
 *     bucket: ?string,
 *     region: string,
 *     prefix: string,
 *     endpoint: ?string,
 *     pathStyleEndpoint: bool,
 *     key: ?string,
 *     secret: ?string,
 *     acl: string,
 *     checksums: string,
 * }
 */
final class FilesystemFactory
{
	use StaticClass;

	public const string AdapterLocal = 'local';
	public const string AdapterS3 = 's3';

	/**
	 * Práva lokálních souborů a adresářů - stejná jako dřív (FileUpload::move() 0666, Directory 0777), aby do
	 * adresářů mohl zapisovat web server i uživatel, pod kterým běží konzole (bin/console files:migrate).
	 */
	private const array LocalPermissions = [
		'file' => ['public' => 0666, 'private' => 0600],
		'dir' => ['public' => 0777, 'private' => 0700],
	];


	/**
	 * @param StorageConfig $config
	 */
	public static function create(array $config): Filesystem
	{
		$adapter = match ($config['adapter']) {
			self::AdapterLocal => self::createLocalAdapter($config),
			self::AdapterS3 => self::createS3Adapter($config),
			default => throw new InvalidArgumentException(sprintf("Neznámý adaptér úložiště '%s', podporované jsou 'local' a 's3'.", $config['adapter'])),
		};

		$options = [
			Config::OPTION_VISIBILITY => Visibility::PUBLIC,
			Config::OPTION_DIRECTORY_VISIBILITY => Visibility::PUBLIC,
		];

		return new Filesystem(
			$adapter,
			$options,
			publicUrlGenerator: $config['publicUrl'] !== null ? new EncodedPublicUrlGenerator($config['publicUrl']) : null,
		);
	}


	/**
	 * @param StorageConfig $config
	 */
	private static function createLocalAdapter(array $config): FilesystemAdapter
	{
		if ($config['root'] === null || $config['root'] === '') {
			throw new InvalidArgumentException("Lokální úložiště potřebuje 'root' (adresář se soubory).");
		}

		return new LocalFilesystemAdapter(
			$config['root'],
			PortableVisibilityConverter::fromArray(self::LocalPermissions, Visibility::PUBLIC),
			lazyRootCreation: true,
		);
	}


	/**
	 * @param StorageConfig $config
	 */
	private static function createS3Adapter(array $config): FilesystemAdapter
	{
		if ($config['bucket'] === null || $config['bucket'] === '') {
			throw new InvalidArgumentException("S3 úložiště potřebuje 'bucket'.");
		}

		$clientConfig = [
			'version' => 'latest',
			'region' => $config['region'],
			'use_path_style_endpoint' => $config['pathStyleEndpoint'],
			// 'when_required' pro úložiště kompatibilní s S3, která nové kontrolní součty AWS SDK neznají
			'request_checksum_calculation' => $config['checksums'],
			'response_checksum_validation' => $config['checksums'],
		];

		if ($config['endpoint'] !== null) {
			$clientConfig['endpoint'] = $config['endpoint'];
		}

		// bez klíče použije AWS SDK výchozí zdroje (proměnné prostředí AWS_*, IAM role serveru)
		if ($config['key'] !== null) {
			$clientConfig['credentials'] = ['key' => $config['key'], 'secret' => (string) $config['secret']];
		}

		return new AwsS3V3Adapter(
			new S3Client($clientConfig),
			$config['bucket'],
			$config['prefix'],
			new AclVisibilityConverter($config['acl']),
		);
	}
}
