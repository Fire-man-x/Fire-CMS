<?php
declare(strict_types=1);

namespace App\FileStorage\DI;

use App\FileStorage\Console\MigrateFilesCommand;
use App\FileStorage\DirectThumbnailRoutes;
use App\FileStorage\Exceptions\InvalidThumbnailException;
use App\FileStorage\FileManager;
use App\FileStorage\Flysystem\FilesystemFactory;
use App\FileStorage\Naming\HashNamingScheme;
use App\FileStorage\Naming\NamingScheme;
use App\FileStorage\Storages\FlysystemStorage;
use App\FileStorage\Storages\StorageRegistry;
use App\FileStorage\Thumbnails\AllowedThumbnails;
use League\Flysystem\Filesystem;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\Statement;
use Nette\DI\InvalidConfigurationException;
use Nette\Schema\Elements\Structure;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Nette\Schema\Schema;
use Nette\Utils\Validators;
use Symfony\Component\Console\Command\Command;

/**
 * Úložiště souborů: pojmenovaná úložiště (Flysystem - lokální adresář nebo S3 bucket) se schématem názvů
 * a povolenými náhledy obrázků, správce souborů a Latte makra. Konfigurace viz docs/Architecture/file-storage.md.
 *
 * Úložiště jsou položky přímo pod `fileStorage:` (všechno kromě voleb v getConfigSchema()), např.
 * `fileStorage: files: adapter: local`. Každé má služby (bez autowiringu, kromě úložiště `defaultStorage`):
 * - `fileStorage.storage.<název>` - FlysystemStorage (upload, odkazy, náhledy), plugin si ho předá
 *   do vlastní služby: `- MojeSluzba(@fileStorage.storage.galerie)`,
 * - `fileStorage.filesystem.<název>` - samotný League\Flysystem\Filesystem,
 * - `fileStorage.naming.<název>` a `fileStorage.allowedThumbnails.<název>`.
 */
class Extension extends CompilerExtension
{
	/** Úložiště správce souborů, pokud konfigurace neurčí jinak */
	public const string DefaultStorage = 'files';

	/** Název úložiště je součástí URL generátoru náhledů a názvů služeb */
	private const string StorageNamePattern = '~^[a-zA-Z0-9_-]+$~D';

	/** Volby úložiště, které nepatří Flysystemu (FilesystemFactory) */
	private const array StorageOptions = ['naming', 'thumbnails', 'keepMetadata', 'stripGps', 'directThumbnails'];


	public function getConfigSchema(): Structure
	{
		$deprecated = 'fileStorage: %s už se nepoužívá - úložiště se nastavuje jako fileStorage: <název>:, viz docs/Architecture/file-storage.md.';

		// pevné volby; každá další položka je úložiště (otherItems), jejich názvy proto nesmí být názvy voleb
		return Expect::structure([
			// úložiště správce souborů (FileManager, autowiring FlysystemStorage)
			'defaultStorage' => Expect::string(self::DefaultStorage),
			// nepovolený náhled v šabloně vyhodí výjimku; null = jen v debug režimu
			'strictThumbnails' => Expect::bool()->nullable(),
			// výchozí pro úložiště, která to nenastaví sama:
			// ponechat originálu metadata JPEG (EXIF, XMP, IPTC) i po zmenšení a narovnání; náhledy je nemají
			'keepMetadata' => Expect::bool(true),
			// odstranit z metadat originálu GPS polohu, kde byla fotka pořízena (výchozí: ponechat)
			'stripGps' => Expect::bool(false),
			'macros' => Expect::listOf('string')->default([
				'App\FileStorage\Macro\ImageMacro',
			]),
			// dřívější umístění voleb - viz loadConfiguration()
			'thumbnails' => Expect::mixed(),
			'storages' => Expect::mixed(),
			'storageClass' => Expect::mixed()->deprecated(sprintf($deprecated, 'storageClass')),
			'imageEntity' => Expect::mixed()->deprecated(sprintf($deprecated, 'imageEntity')),
			'basePath' => Expect::mixed()->deprecated(sprintf($deprecated, 'basePath')),
			'storageDir' => Expect::mixed()->deprecated(sprintf($deprecated, 'storageDir')),
			'cacheDir' => Expect::mixed()->deprecated(sprintf($deprecated, 'cacheDir')),
		])->otherItems(self::getStorageSchema());
	}


	public function loadConfiguration(): void
	{
		/** @var \stdClass&object{defaultStorage: string, strictThumbnails: ?bool, keepMetadata: bool, stripGps: bool, macros: list<string>, thumbnails: mixed, storages: mixed} $config */
		$config = $this->getConfig();
		$builder = $this->getContainerBuilder();

		// tiché ignorování by v produkci vedlo na originály místo náhledů, resp. na výchozí úložiště
		if ($config->thumbnails !== null) {
			throw new InvalidConfigurationException('fileStorage: thumbnails: se přesunulo k úložišti - povolené náhledy patří do fileStorage: <název úložiště>: thumbnails:, viz docs/Architecture/file-storage.md.');
		}

		if ($config->storages !== null) {
			throw new InvalidConfigurationException('fileStorage: storages: už není - úložiště patří přímo pod fileStorage: (fileStorage: files: adapter: ...), viz docs/Architecture/file-storage.md.');
		}

		/** @var array<string, array<string, mixed>> $storages položky mimo pevné volby (otherItems) */
		$storages = array_diff_key((array) $config, $this->getConfigSchema()->getShape());
		if (!isset($storages[self::DefaultStorage])) {
			$wwwDir = $builder->parameters['wwwDir'] ?? null;
			if (!is_string($wwwDir)) {
				throw new InvalidConfigurationException("fileStorage: výchozí úložiště '" . self::DefaultStorage . "' potřebuje parametr %wwwDir%, nebo ho nastavte jako fileStorage: " . self::DefaultStorage . ':.');
			}

			// dřívější výchozí umístění (fileManager.storageDir %wwwDir%/files, URL /files/)
			$default = (new Processor())->process(self::getStorageSchema(), [
				'root' => $wwwDir . '/files',
				'publicUrl' => '/files/',
			]);
			$storages[self::DefaultStorage] = is_array($default) ? $default : [];
		}

		if (!isset($storages[$config->defaultStorage])) {
			throw new InvalidConfigurationException(sprintf("fileStorage: defaultStorage '%s' - takové úložiště pod fileStorage: není.", $config->defaultStorage));
		}

		$strictThumbnails = $config->strictThumbnails ?? (bool) ($builder->parameters['debugMode'] ?? false);
		$registry = [];
		$directThumbnailPrefixes = [];
		foreach ($storages as $name => $storage) {
			$this->validateStorage($name, $storage);
			/** @var array{resize: list<string|int>, crop: list<string|int>, aliases: array<string, string|int>} $thumbnails */
			$thumbnails = $storage['thumbnails'];

			$builder->addDefinition($this->prefix('filesystem.' . $name))
				->setType(Filesystem::class)
				->setFactory([FilesystemFactory::class, 'create'], [array_diff_key($storage, array_flip(self::StorageOptions))])
				->setAutowired(false);

			$naming = $storage['naming'];
			if (!is_string($naming) && !$naming instanceof Statement) {
				throw new InvalidConfigurationException(sprintf('fileStorage: %s: naming musí být třída nebo služba implementující %s.', $name, NamingScheme::class));
			}

			$builder->addDefinition($this->prefix('naming.' . $name))
				->setType(NamingScheme::class)
				->setFactory($naming)
				->setAutowired(false);

			$builder->addDefinition($this->prefix('allowedThumbnails.' . $name))
				->setFactory(AllowedThumbnails::class, [$thumbnails['resize'], $thumbnails['crop'], $thumbnails['aliases']])
				->setAutowired(false);

			$builder->addDefinition($this->prefix('storage.' . $name))
				->setFactory(FlysystemStorage::class, [
					'filesystem' => '@' . $this->prefix('filesystem.' . $name),
					'naming' => '@' . $this->prefix('naming.' . $name),
					'allowedThumbnails' => '@' . $this->prefix('allowedThumbnails.' . $name),
					'strictThumbnails' => $strictThumbnails,
					'keepMetadata' => $storage['keepMetadata'] ?? $config->keepMetadata,
					'stripGps' => $storage['stripGps'] ?? $config->stripGps,
					'name' => $name,
					'directThumbnails' => $storage['directThumbnails'] === true,
				])
				->setAutowired($name === $config->defaultStorage);

			$registry[$name] = new Reference($this->prefix('storage.' . $name));
			if ($storage['directThumbnails'] === true && is_string($storage['publicUrl'])) {
				$directThumbnailPrefixes[$name] = trim($storage['publicUrl'], '/'); // validateStorage(): cesta na tomto webu
			}
		}

		$builder->addDefinition($this->prefix('directThumbnailRoutes'))
			->setFactory(DirectThumbnailRoutes::class, [$directThumbnailPrefixes]);

		$builder->addDefinition($this->prefix('storages'))
			->setFactory(StorageRegistry::class, [$registry]);

		$builder->addDefinition($this->prefix('fileManager'))
			->setFactory(FileManager::class, ['@' . $this->prefix('storage.' . $config->defaultStorage)]);

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
	 * Konfigurace jednoho úložiště.
	 * - kam: lokální adresář (adapter, root, publicUrl) nebo S3 (adapter, bucket, region, prefix, publicUrl - URL
	 *   CDN nebo bucketu včetně prefixu, přístupové údaje a volitelně endpoint úložiště kompatibilního s S3)
	 * - jak se soubory jmenují: naming (třída nebo služba implementující NamingScheme, výchozí HashNamingScheme)
	 * - povolené náhledy obrázků: thumbnails
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
			// ACL posílané s každým zápisem do S3, viz App\FileStorage\Flysystem\AclVisibilityConverter
			'acl' => Expect::string('bucket-owner-full-control'),
			'checksums' => Expect::anyOf('when_supported', 'when_required')->default('when_supported'),
			'naming' => Expect::anyOf(Expect::string(), Expect::type(Statement::class))->default(HashNamingScheme::class),
			// povolené náhledy obrázků úložiště - viz App\FileStorage\Thumbnails\AllowedThumbnails
			'thumbnails' => Expect::structure([
				// {image}/n:src, např. 300x200, 945x, 300x200-f1 (s příznaky Image::resize())
				'resize' => Expect::listOf('string|int'),
				// {crop}/n:crop, např. 130x130
				'crop' => Expect::listOf('string|int'),
				// pojmenované rozměry ze šablon, např. smallest: x192 (náhled musí být povolený v resize/crop)
				'aliases' => Expect::arrayOf('string|int', 'string'),
			])->castTo('array'),
			// null = fileStorage: keepMetadata / stripGps
			'keepMetadata' => Expect::bool()->nullable(),
			'stripGps' => Expect::bool()->nullable(),
			// odkazy přímo na náhledy; chybějící náhled vytvoří aplikace, když na jeho adresu přijde požadavek -
			// jen lokální disk, web server musí požadavky na neexistující soubory pod publicUrl posílat do index.php
			'directThumbnails' => Expect::bool(false),
		])->castTo('array');
	}


	/**
	 * @param array<mixed> $storage
	 */
	private function validateStorage(string $name, array $storage): void
	{
		if (!preg_match(self::StorageNamePattern, $name)) {
			throw new InvalidConfigurationException(sprintf("fileStorage: '%s': název úložiště smí obsahovat jen písmena bez diakritiky, číslice, '_' a '-'.", $name));
		}

		$adapter = is_string($storage['adapter'] ?? null) ? $storage['adapter'] : '';
		$required = $adapter === FilesystemFactory::AdapterS3 ? 'bucket' : 'root';
		if (!is_string($storage[$required] ?? null) || $storage[$required] === '') {
			throw new InvalidConfigurationException(sprintf("fileStorage: %s: chybí '%s' (adapter %s).", $name, $required, $adapter));
		}

		// požadavek na chybějící náhled musí dojít do aplikace (FileRouter) - to umí jen soubory na tomto webu
		$publicUrl = $storage['publicUrl'] ?? null;
		if (($storage['directThumbnails'] ?? false) === true
			&& ($adapter !== FilesystemFactory::AdapterLocal || !is_string($publicUrl) || !str_starts_with($publicUrl, '/'))
		) {
			throw new InvalidConfigurationException(sprintf("fileStorage: %s: directThumbnails potřebuje adapter local a publicUrl jako cestu na tomto webu (např. '/foto/'), jinak požadavek na chybějící náhled do aplikace nedojde.", $name));
		}

		// chybu v seznamu náhledů hlásí už sestavení kontejneru, ne až první vykreslení obrázku
		$thumbnails = is_array($storage['thumbnails'] ?? null) ? $storage['thumbnails'] : [];
		try {
			new AllowedThumbnails(
				self::stringList($thumbnails['resize'] ?? []),
				self::stringList($thumbnails['crop'] ?? []),
				self::stringMap($thumbnails['aliases'] ?? []),
			);
		} catch (InvalidThumbnailException $e) {
			throw new InvalidConfigurationException(sprintf('fileStorage: %s: %s', $name, $e->getMessage()), 0, $e);
		}
	}


	/**
	 * @return list<string>
	 */
	private static function stringList(mixed $values): array
	{
		return is_array($values) ? array_values(array_map(static fn(mixed $value): string => is_scalar($value) ? (string) $value : '', $values)) : [];
	}


	/**
	 * @return array<string, string>
	 */
	private static function stringMap(mixed $values): array
	{
		$map = [];
		foreach (is_array($values) ? $values : [] as $key => $value) {
			$map[(string) $key] = is_scalar($value) ? (string) $value : '';
		}

		return $map;
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
