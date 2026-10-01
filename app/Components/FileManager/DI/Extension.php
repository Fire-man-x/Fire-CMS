<?php
declare(strict_types=1);

namespace App\Components\FileManager\DI;

use App\Components\FileManager\Console\MigrateFilesCommand;
use App\Components\FileManager\FileManager;
use App\Components\FileManager\Flysystem\FilesystemFactory;
use App\Components\FileManager\Storages\FlysystemStorage;
use App\Components\FileManager\Thumbnails\AllowedThumbnails;
use League\Flysystem\Filesystem;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\Statement;
use Nette\DI\InvalidConfigurationException;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Nette\Schema\Schema;
use Nette\Utils\Validators;
use Symfony\Component\Console\Command\Command;

/**
 * Správce souborů: pojmenovaná úložiště (Flysystem - lokální adresář nebo S3 bucket), FlysystemStorage
 * správce souborů, povolené náhledy obrázků a Latte makra. Konfigurace viz docs/Architecture/file-storage.md.
 *
 * Každé úložiště z `fileManager: storages:` je služba `fileManager.filesystem.<název>`
 * (League\Flysystem\Filesystem, bez autowiringu), takže si ho plugin může předat do vlastní služby:
 * `- MojeSluzba(@fileManager.filesystem.galerie)`.
 */
class Extension extends CompilerExtension
{
	/** Úložiště správce souborů, pokud konfigurace neurčí jinak */
	public const string DefaultStorage = 'files';


	public function getConfigSchema(): Schema
	{
		$deprecated = 'fileManager: %s už se nepoužívá - úložiště se nastavuje přes fileManager: storages:, viz docs/Architecture/file-storage.md.';

		return Expect::structure([
			// úložiště, do kterého ukládá správce souborů (FlysystemStorage)
			'defaultStorage' => Expect::string(self::DefaultStorage),
			'storages' => Expect::arrayOf(self::getStorageSchema(), Expect::string()),
			// povolené náhledy obrázků - viz App\Components\FileManager\Thumbnails\AllowedThumbnails
			'thumbnails' => Expect::structure([
				'resize' => Expect::listOf('string|int'),
				'crop' => Expect::listOf('string|int'),
			]),
			// nepovolený náhled v šabloně vyhodí výjimku; null = jen v debug režimu
			'strictThumbnails' => Expect::bool()->nullable(),
			// ponechat originálu metadata JPEG (EXIF, XMP, IPTC) i po zmenšení a narovnání; náhledy je nemají
			'keepMetadata' => Expect::bool(true),
			// odstranit z metadat originálu GPS polohu, kde byla fotka pořízena (výchozí: ponechat)
			'stripGps' => Expect::bool(false),
			'macros' => Expect::listOf('string')->default([
				'App\Components\FileManager\Macro\ImageMacro',
			]),
			'storageClass' => Expect::mixed()->deprecated(sprintf($deprecated, 'storageClass')),
			'imageEntity' => Expect::mixed()->deprecated(sprintf($deprecated, 'imageEntity')),
			'basePath' => Expect::mixed()->deprecated(sprintf($deprecated, 'basePath')),
			'storageDir' => Expect::mixed()->deprecated(sprintf($deprecated, 'storageDir')),
			'cacheDir' => Expect::mixed()->deprecated(sprintf($deprecated, 'cacheDir')),
		]);
	}


	public function loadConfiguration(): void
	{
		/** @var \stdClass&object{defaultStorage: string, storages: array<string, array<string, mixed>>, thumbnails: \stdClass&object{resize: list<string|int>, crop: list<string|int>}, strictThumbnails: ?bool, keepMetadata: bool, stripGps: bool, macros: list<string>} $config */
		$config = $this->getConfig();
		$builder = $this->getContainerBuilder();

		$storages = $config->storages;
		if (!isset($storages[self::DefaultStorage])) {
			$wwwDir = $builder->parameters['wwwDir'] ?? null;
			if (!is_string($wwwDir)) {
				throw new InvalidConfigurationException("fileManager: výchozí úložiště '" . self::DefaultStorage . "' potřebuje parametr %wwwDir%, nebo ho nastavte v fileManager: storages:.");
			}

			// dřívější výchozí umístění (fileManager.storageDir %wwwDir%/files, URL /files/)
			$default = (new Processor())->process(self::getStorageSchema(), [
				'root' => $wwwDir . '/files',
				'publicUrl' => '/files/',
			]);
			$storages[self::DefaultStorage] = is_array($default) ? $default : [];
		}

		foreach ($storages as $name => $storage) {
			$this->validateStorage($name, $storage);
			$builder->addDefinition($this->prefix('filesystem.' . $name))
				->setType(Filesystem::class)
				->setFactory([FilesystemFactory::class, 'create'], [$storage])
				->setAutowired(false);
		}

		if (!isset($storages[$config->defaultStorage])) {
			throw new InvalidConfigurationException(sprintf("fileManager: defaultStorage '%s' není mezi fileManager: storages:.", $config->defaultStorage));
		}

		$builder->addDefinition($this->prefix('allowedThumbnails'))
			->setFactory(AllowedThumbnails::class, [$config->thumbnails->resize, $config->thumbnails->crop]);

		$builder->addDefinition($this->prefix('storage'))
			->setFactory(FlysystemStorage::class, [
				'filesystem' => '@' . $this->prefix('filesystem.' . $config->defaultStorage),
				'allowedThumbnails' => '@' . $this->prefix('allowedThumbnails'),
				'strictThumbnails' => $config->strictThumbnails ?? (bool) ($builder->parameters['debugMode'] ?? false),
				'keepMetadata' => $config->keepMetadata,
				'stripGps' => $config->stripGps,
				'name' => $config->defaultStorage,
			]);

		$builder->addDefinition($this->prefix('fileManager'))
			->setFactory(FileManager::class, ['@' . $this->prefix('storage')]);

		if (class_exists(Command::class)) {
			$wwwDir = $builder->parameters['wwwDir'] ?? null;
			$builder->addDefinition($this->prefix('migrateCommand'))
				->setFactory(MigrateFilesCommand::class, [
					'defaultSource' => is_string($wwwDir) ? $wwwDir . '/files' : null,
				]);
		}

		$this->addMacros($config->macros);
	}


	/**
	 * Konfigurace jednoho úložiště. Lokální: adapter, root, publicUrl. S3: adapter, bucket, region, prefix,
	 * publicUrl (URL CDN nebo bucketu včetně prefixu), přístupové údaje a volitelně endpoint (úložiště
	 * kompatibilní s S3, např. Garage).
	 */
	private static function getStorageSchema(): Schema
	{
		return Expect::structure([
			'adapter' => Expect::anyOf(FilesystemFactory::AdapterLocal, FilesystemFactory::AdapterS3)->default(FilesystemFactory::AdapterLocal),
			'root' => Expect::string()->nullable(),
			'publicUrl' => Expect::string()->nullable(),
			'bucket' => Expect::string()->nullable(),
			'region' => Expect::string('eu-central-1'),
			'prefix' => Expect::string(''),
			'endpoint' => Expect::string()->nullable(),
			'pathStyleEndpoint' => Expect::bool(false),
			'key' => Expect::string()->nullable(),
			'secret' => Expect::string()->nullable(),
			// ACL posílané s každým zápisem do S3, viz App\Components\FileManager\Flysystem\AclVisibilityConverter
			'acl' => Expect::string('bucket-owner-full-control'),
			'checksums' => Expect::anyOf('when_supported', 'when_required')->default('when_supported'),
		])->castTo('array');
	}


	/**
	 * @param array<mixed> $storage
	 */
	private function validateStorage(string $name, array $storage): void
	{
		$adapter = is_string($storage['adapter'] ?? null) ? $storage['adapter'] : '';
		$required = $adapter === FilesystemFactory::AdapterS3 ? 'bucket' : 'root';
		if (!is_string($storage[$required] ?? null) || $storage[$required] === '') {
			throw new InvalidConfigurationException(sprintf("fileManager: storages: %s: chybí '%s' (adapter %s).", $name, $required, $adapter));
		}
	}


	/**
	 * Adds Latte extensions to the latte factory definition.
	 *
	 * @param list<string> $macros
	 */
	private function addMacros(array $macros): void
	{
		$builder = $this->getContainerBuilder();
		$factory = $builder->getDefinition('nette.latteFactory');
		if (!$factory instanceof \Nette\DI\Definitions\FactoryDefinition) {
			return;
		}

		foreach ($macros as $macro) {
			Validators::assert($macro, 'string');
			$factory->getResultDefinition()->addSetup('addExtension', [new Statement($macro)]);
		}
	}
}
