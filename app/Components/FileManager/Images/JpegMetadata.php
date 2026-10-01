<?php
declare(strict_types=1);

namespace App\Components\FileManager\Images;

/**
 * Metadata JPEG - EXIF, XMP, IPTC a barevný profil ICC - a jejich přenos do obrázku přeuloženého přes GD,
 * které metadata nezapisuje (zmenšení nebo narovnání originálu při uploadu, otočení v administraci).
 *
 * Neměnný objekt, úpravy vrací novou instanci. Jiné segmenty (MPF, JUMBF/C2PA…) se nepřenášejí - po
 * přeuložení by odkazovaly na data, která v souboru už nejsou.
 */
final class JpegMetadata
{
	private const int App0 = 0xE0;
	private const int App1 = 0xE1;
	private const int App2 = 0xE2;
	private const int App13 = 0xED;

	private const string XmpHeader = "http://ns.adobe.com/xap/1.0/\x00";
	private const string XmpExtensionHeader = "http://ns.adobe.com/xmp/extension/\x00";
	private const string IccHeader = "ICC_PROFILE\x00";
	private const string IptcHeader = "Photoshop 3.0\x00";

	/** Největší obsah segmentu (délka je 16bitová a počítá i sebe) */
	private const int MaxPayload = 0xFFFF - 2;


	/**
	 * @param list<array{int, string}> $segments [marker, obsah segmentu bez markeru a délky] v původním pořadí
	 */
	private function __construct(private readonly array $segments)
	{
	}


	public static function fromJpeg(string $jpeg): self
	{
		$segments = [];
		foreach (self::parse($jpeg) as [$marker, $payload]) {
			if (self::kind($marker, $payload) !== null) {
				$segments[] = [$marker, $payload];
			}
		}

		return new self($segments);
	}


	public function isEmpty(): bool
	{
		return $this->segments === [];
	}


	public function equals(self $other): bool
	{
		return $this->segments === $other->segments;
	}


	public function hasGps(): bool
	{
		foreach ($this->segments as [$marker, $payload]) {
			$kind = self::kind($marker, $payload);
			if ($kind === 'xmp' && str_contains($payload, 'exif:GPS')) {
				return true;
			}

			if ($kind === 'exif') {
				try {
					if (ExifEditor::fromSegment($payload)->hasGps()) {
						return true;
					}
				} catch (\UnexpectedValueException) {
					return true; // nečitelné EXIF - radši počítat s tím, že polohu obsahuje
				}
			}
		}

		return false;
	}


	/**
	 * Bez GPS polohy v EXIF i XMP. Nečitelné EXIF se zahodí celé.
	 */
	public function withoutGps(): self
	{
		return $this->map(static function (string $kind, string $payload): string {
			if ($kind === 'exif') {
				$exif = ExifEditor::fromSegment($payload);
				$exif->removeGps();
				return $exif->toSegment();
			}

			if ($kind === 'xmp') {
				$payload = preg_replace('~\s+exif:GPS\w+\s*=\s*(["\']).*?\1~s', '', $payload) ?? $payload;
				$payload = preg_replace('~<exif:GPS\w+\b[^>]*/>~', '', $payload) ?? $payload;
				return preg_replace('~<exif:GPS(\w+)\b[^>]*>.*?</exif:GPS\1>~s', '', $payload) ?? $payload;
			}

			return $payload;
		});
	}


	/**
	 * Jen barevný profil ICC - bez něj by se barvy fotek v širším gamutu (např. Display P3 z iPhonu) zobrazily jinak.
	 */
	public function onlyColorProfile(): self
	{
		return $this->map(static fn(string $kind, string $payload): ?string => $kind === 'icc' ? $payload : null);
	}


	/**
	 * Metadata pro přeuložený obrázek: orientace 1 (obrázek je už fyzicky narovnaný - jinak by ho prohlížeč
	 * otočil podruhé), nové rozměry a bez vloženého náhledu EXIF. Nečitelné EXIF se zahodí celé.
	 */
	public function forImage(int $width, int $height): self
	{
		return $this->map(static function (string $kind, string $payload) use ($width, $height): string {
			if ($kind === 'exif') {
				$exif = ExifEditor::fromSegment($payload);
				$exif->setOrientation(ExifOrientation::Normal);
				$exif->setPixelDimensions($width, $height);
				$exif->removeThumbnail();
				return $exif->toSegment();
			}

			if ($kind === 'xmp') {
				$payload = self::setXmpValue($payload, 'tiff:Orientation', (string) ExifOrientation::Normal);
				foreach (['exif:PixelXDimension' => $width, 'tiff:ImageWidth' => $width, 'exif:PixelYDimension' => $height, 'tiff:ImageLength' => $height] as $property => $value) {
					$payload = self::setXmpValue($payload, $property, (string) $value);
				}
			}

			return $payload;
		});
	}


	/**
	 * Vloží metadata do JPEG místo jeho vlastních (APP1, APP2, APP13) - za JFIF hlavičku, jinak hned za začátek
	 * souboru. Obrazová data zůstávají beze změny.
	 */
	public function applyTo(string $jpeg): string
	{
		$segments = self::parse($jpeg);
		if ($segments === []) {
			return $jpeg;
		}

		$jfif = '';
		$other = '';
		$end = 2;
		foreach ($segments as $index => [$marker, , $start, $length]) {
			$raw = substr($jpeg, $start, $length);
			$end = $start + $length;
			if ($marker === self::App1 || $marker === self::App2 || $marker === self::App13) {
				continue;
			}

			if ($index === 0 && $marker === self::App0) {
				$jfif = $raw;
			} else {
				$other .= $raw;
			}
		}

		$metadata = '';
		foreach ($this->segments as [$marker, $payload]) {
			if (strlen($payload) <= self::MaxPayload) {
				$metadata .= "\xFF" . chr($marker) . pack('n', strlen($payload) + 2) . $payload;
			}
		}

		return "\xFF\xD8" . $jfif . $metadata . $other . substr($jpeg, $end);
	}


	/**
	 * @param callable(string, string): ?string $callback (druh segmentu, obsah) => nový obsah, null = vynechat
	 */
	private function map(callable $callback): self
	{
		$segments = [];
		foreach ($this->segments as [$marker, $payload]) {
			try {
				$payload = $callback(self::kind($marker, $payload) ?? '', $payload);
			} catch (\UnexpectedValueException) {
				$payload = null; // poškozené EXIF - bezpečnější ho zahodit než nechat špatnou orientaci nebo polohu
			}

			if ($payload !== null) {
				$segments[] = [$marker, $payload];
			}
		}

		return new self($segments);
	}


	/**
	 * Druh metadat segmentu, null = segment nepřenášíme.
	 */
	private static function kind(int $marker, string $payload): ?string
	{
		return match (true) {
			$marker === self::App1 && str_starts_with($payload, ExifEditor::Header) => 'exif',
			$marker === self::App1 && str_starts_with($payload, self::XmpHeader) => 'xmp',
			$marker === self::App1 && str_starts_with($payload, self::XmpExtensionHeader) => 'xmp-extension',
			$marker === self::App2 && str_starts_with($payload, self::IccHeader) => 'icc',
			$marker === self::App13 && str_starts_with($payload, self::IptcHeader) => 'iptc',
			default => null,
		};
	}


	private static function setXmpValue(string $xmp, string $property, string $value): string
	{
		$name = preg_quote($property, '~');
		$xmp = preg_replace('~(\b' . $name . '\s*=\s*(["\']))[^"\']*(\2)~', '${1}' . $value . '${3}', $xmp) ?? $xmp;

		return preg_replace('~(<' . $name . '>)[^<]*(</' . $name . '>)~', '${1}' . $value . '${2}', $xmp) ?? $xmp;
	}


	/**
	 * Segmenty hlavičky JPEG až po začátek obrazových dat (SOS).
	 *
	 * @return list<array{int, string, int, int}> [marker, obsah, začátek segmentu, délka segmentu včetně markeru]
	 */
	private static function parse(string $jpeg): array
	{
		if (!str_starts_with($jpeg, "\xFF\xD8")) {
			return [];
		}

		$segments = [];
		$offset = 2;
		$length = strlen($jpeg);
		while ($offset + 4 <= $length && $jpeg[$offset] === "\xFF") {
			$marker = ord($jpeg[$offset + 1]);
			if ($marker === 0xFF) { // výplňový bajt před markerem
				$offset++;
				continue;
			}

			if ($marker === 0xDA || $marker === 0xD9) { // SOS / EOI - dál jsou obrazová data
				break;
			}

			$size = ord($jpeg[$offset + 2]) << 8 | ord($jpeg[$offset + 3]);
			if ($size < 2 || $offset + 2 + $size > $length) {
				break;
			}

			$segments[] = [$marker, substr($jpeg, $offset + 4, $size - 2), $offset, $size + 2];
			$offset += 2 + $size;
		}

		return $segments;
	}
}
