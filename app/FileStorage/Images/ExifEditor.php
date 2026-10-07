<?php
declare(strict_types=1);

namespace App\FileStorage\Images;

/**
 * Úpravy EXIF (TIFF struktura v segmentu APP1 JPEG) na místě, bez přestavby celého bloku: offsety uvnitř
 * EXIF jsou od začátku TIFF hlavičky, takže se nic jiného neposouvá.
 *
 * Při poškozené struktuře metody vyhodí \UnexpectedValueException - volající pak EXIF raději zahodí.
 */
final class ExifEditor
{
	public const string Header = "Exif\x00\x00";

	private const int TagOrientation = 0x0112;
	private const int TagExifIfd = 0x8769;
	private const int TagGpsIfd = 0x8825;
	private const int TagPixelXDimension = 0xA002;
	private const int TagPixelYDimension = 0xA003;

	private const int TypeShort = 3;
	private const int TypeLong = 4;

	/** Velikost jedné hodnoty podle typu TIFF položky v bajtech */
	private const array TypeSizes = [1 => 1, 2 => 1, 3 => 2, 4 => 4, 5 => 8, 6 => 1, 7 => 1, 8 => 2, 9 => 4, 10 => 8, 11 => 4, 12 => 8];


	private function __construct(
		private string $tiff,
		private readonly bool $littleEndian,
	)
	{
	}


	/**
	 * @param string $payload obsah segmentu APP1 (bez markeru a délky), začíná "Exif\0\0"
	 * @throws \UnexpectedValueException
	 */
	public static function fromSegment(string $payload): self
	{
		if (!str_starts_with($payload, self::Header)) {
			throw new \UnexpectedValueException('EXIF: chybí hlavička Exif.');
		}

		$tiff = substr($payload, strlen(self::Header));
		$byteOrder = substr($tiff, 0, 2);
		if ($byteOrder !== 'II' && $byteOrder !== 'MM') {
			throw new \UnexpectedValueException('EXIF: neznámé pořadí bajtů.');
		}

		$editor = new self($tiff, $byteOrder === 'II');
		if ($editor->readShort(2) !== 42) {
			throw new \UnexpectedValueException('EXIF: neplatná TIFF hlavička.');
		}

		$editor->entries($editor->ifd0()); // ověří, že IFD0 je celé v datech

		return $editor;
	}


	public function toSegment(): string
	{
		return self::Header . $this->tiff;
	}


	/**
	 * @throws \UnexpectedValueException
	 */
	public function setOrientation(int $orientation): void
	{
		$entry = $this->findEntry($this->ifd0(), self::TagOrientation);
		if ($entry !== null && $this->readShort($entry + 2) === self::TypeShort) {
			$this->writeShort($entry + 8, $orientation);
		}
	}


	/**
	 * Aktualizuje rozměry obrázku v Exif IFD (PixelXDimension / PixelYDimension), pokud tam jsou.
	 *
	 * @throws \UnexpectedValueException
	 */
	public function setPixelDimensions(int $width, int $height): void
	{
		$exifIfd = $this->findEntry($this->ifd0(), self::TagExifIfd);
		if ($exifIfd === null) {
			return;
		}

		$ifd = $this->readLong($exifIfd + 8);
		foreach ([self::TagPixelXDimension => $width, self::TagPixelYDimension => $height] as $tag => $value) {
			$entry = $this->findEntry($ifd, $tag);
			if ($entry === null) {
				continue;
			}

			$type = $this->readShort($entry + 2);
			if ($type === self::TypeShort && $value <= 0xFFFF) {
				$this->writeShort($entry + 8, $value);
			} elseif ($type === self::TypeLong) {
				$this->writeLong($entry + 8, $value);
			}
		}
	}


	/**
	 * Zahodí odkaz na vložený náhled (IFD1). Ten zůstal v původní orientaci a rozměrech, takže by po narovnání
	 * a zmenšení neodpovídal - prohlížeče souborů by ukazovaly otočený náhled.
	 *
	 * @throws \UnexpectedValueException
	 */
	public function removeThumbnail(): void
	{
		$this->writeLong($this->nextIfdPosition($this->ifd0()), 0);
	}


	/**
	 * @throws \UnexpectedValueException
	 */
	public function hasGps(): bool
	{
		return $this->findEntry($this->ifd0(), self::TagGpsIfd) !== null;
	}


	/**
	 * Odstraní GPS polohu: vynuluje GPS IFD i jeho hodnoty (aby souřadnice nezůstaly v bajtech souboru)
	 * a vyřadí odkaz na něj z IFD0.
	 *
	 * @throws \UnexpectedValueException
	 */
	public function removeGps(): void
	{
		$ifd0 = $this->ifd0();
		$pointer = $this->findEntry($ifd0, self::TagGpsIfd);
		if ($pointer === null) {
			return;
		}

		$gpsIfd = $this->readLong($pointer + 8);
		$entries = $this->entries($gpsIfd);
		foreach ($entries as $entry) {
			$size = self::TypeSizes[$this->readShort($entry + 2)] ?? 1;
			$length = $size * $this->readLong($entry + 4);
			if ($length > 4) {
				$this->zero($this->readLong($entry + 8), $length);
			}
		}

		$this->zero($gpsIfd, 2 + 12 * count($entries) + 4);

		// vyřazení položky z IFD0: následující položky se posunou o 12 bajtů, za ně ukazatel na další IFD
		$count = $this->readShort($ifd0);
		$index = intdiv($pointer - $ifd0 - 2, 12);
		$next = substr($this->tiff, $this->nextIfdPosition($ifd0), 4);
		$following = substr($this->tiff, $pointer + 12, ($count - $index - 1) * 12);
		$this->tiff = substr_replace($this->tiff, $following . $next . str_repeat("\x00", 12), $pointer, strlen($following) + 16);
		$this->writeShort($ifd0, $count - 1);
	}


	/**
	 * @throws \UnexpectedValueException
	 */
	private function ifd0(): int
	{
		return $this->readLong(4);
	}


	/**
	 * @return list<int> offsety položek IFD
	 * @throws \UnexpectedValueException
	 */
	private function entries(int $ifd): array
	{
		$count = $this->readShort($ifd);
		$this->check($ifd + 2, 12 * $count + 4);

		$entries = [];
		for ($i = 0; $i < $count; $i++) {
			$entries[] = $ifd + 2 + 12 * $i;
		}

		return $entries;
	}


	/**
	 * @throws \UnexpectedValueException
	 */
	private function findEntry(int $ifd, int $tag): ?int
	{
		foreach ($this->entries($ifd) as $entry) {
			if ($this->readShort($entry) === $tag) {
				return $entry;
			}
		}

		return null;
	}


	/**
	 * @throws \UnexpectedValueException
	 */
	private function nextIfdPosition(int $ifd): int
	{
		return $ifd + 2 + 12 * $this->readShort($ifd);
	}


	/**
	 * @throws \UnexpectedValueException
	 */
	private function readShort(int $offset): int
	{
		$this->check($offset, 2);
		$bytes = substr($this->tiff, $offset, 2);

		return $this->littleEndian ? ord($bytes[0]) | ord($bytes[1]) << 8 : ord($bytes[0]) << 8 | ord($bytes[1]);
	}


	/**
	 * @throws \UnexpectedValueException
	 */
	private function readLong(int $offset): int
	{
		$this->check($offset, 4);
		$value = unpack($this->littleEndian ? 'V' : 'N', substr($this->tiff, $offset, 4));

		return is_array($value) && is_int($value[1] ?? null) ? $value[1] : throw new \UnexpectedValueException('EXIF: nelze přečíst hodnotu.');
	}


	/**
	 * @throws \UnexpectedValueException
	 */
	private function writeShort(int $offset, int $value): void
	{
		$this->check($offset, 2);
		$this->tiff = substr_replace($this->tiff, pack($this->littleEndian ? 'v' : 'n', $value), $offset, 2);
	}


	/**
	 * @throws \UnexpectedValueException
	 */
	private function writeLong(int $offset, int $value): void
	{
		$this->check($offset, 4);
		$this->tiff = substr_replace($this->tiff, pack($this->littleEndian ? 'V' : 'N', $value), $offset, 4);
	}


	/**
	 * @throws \UnexpectedValueException
	 */
	private function zero(int $offset, int $length): void
	{
		$this->check($offset, $length);
		$this->tiff = substr_replace($this->tiff, str_repeat("\x00", $length), $offset, $length);
	}


	/**
	 * @throws \UnexpectedValueException
	 */
	private function check(int $offset, int $length): void
	{
		// do TIFF hlavičky (prvních 8 bajtů) smí jen čtení magického čísla a offsetu IFD0
		$intoHeader = $offset < 8 && $offset !== 2 && $offset !== 4;
		if ($intoHeader || $length < 0 || $offset + $length > strlen($this->tiff)) {
			throw new \UnexpectedValueException('EXIF: odkaz mimo data.');
		}
	}
}
