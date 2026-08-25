<?php
/**
 * Content-Disposition construction for internationalised filenames.
 *
 * @package   ArrayPress\S3Signer
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\S3Signer\Support;

/**
 * Class ContentDisposition
 *
 * Builds the `Content-Disposition` header that names a downloaded file,
 * correctly for filenames in any script.
 *
 * RFC 6266 wants two forms in the same header. `filename*` carries the
 * real UTF-8 name and is what every current browser uses. `filename` is
 * the ASCII-only fallback for old clients, and it is where naive
 * implementations fall down — replacing every non-ASCII byte with `_`
 * turns `naïve café.mp3` into `na_ve caf_.mp3` and `日本語.zip` into
 * `___.zip`. Here the fallback is transliterated instead, so those
 * become `naive cafe.mp3` and `ri ben yu.zip`.
 *
 * Three problems this also solves, all of which produce real bugs:
 *
 *   - **Unicode form.** macOS gives you decomposed (NFD) filenames, so
 *     `café` arrives as `e` plus a combining acute. Percent-encoded as-is
 *     it renders wrongly on Windows. Everything is normalised to NFC.
 *   - **Bidi spoofing.** A right-to-left override lets `evil<U+202E>gpj.exe`
 *     display as `evilexe.jpg`. Bidi and zero-width controls are stripped.
 *   - **Path injection.** `../../etc/passwd` is reduced to `passwd`.
 *
 * Transliteration uses ext-intl when available and falls back to iconv,
 * so the library keeps working without intl — just with blunter results
 * for non-Latin scripts.
 *
 * Typical use:
 *
 *   ContentDisposition::attachment( 'Naïve Café — Master.wav' );
 *   // attachment; filename="Naive Cafe - Master.wav";
 *   //   filename*=UTF-8''Na%C3%AFve%20Caf%C3%A9%20%E2%80%94%20Master.wav
 *
 * @since 1.0.0
 */
final readonly class ContentDisposition {

	/**
	 * Name used when nothing usable survives sanitisation.
	 */
	private const FALLBACK = 'download';

	/**
	 * Maximum filename length in bytes.
	 *
	 * Most filesystems cap a single path component at 255 bytes. Staying
	 * meaningfully under that leaves room for a browser's " (1)"
	 * de-duplication suffix.
	 */
	private const MAX_BYTES = 200;

	/**
	 * Control characters: C0, and DEL.
	 *
	 * A filename carrying one of these is either corrupt or is trying to
	 * terminate a header early.
	 */
	private const CONTROL = '/[\x00-\x1F\x7F]/u';

	/**
	 * Characters that are in the name but not on the screen.
	 *
	 * Bidirectional overrides, zero-width joiners and spaces, and the byte
	 * order mark. These are the ingredients of a display-spoofing filename:
	 * `report\u{202E}fdp.exe` shows as `reportexe.pdf` and is not one.
	 */
	private const INVISIBLE = '/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u';

	/**
	 * Whitespace, including the non-breaking space that `\s` misses.
	 */
	private const WHITESPACE = '/[\s\x{00A0}]+/u';

	/**
	 * What a header's quoted-string form cannot carry unescaped.
	 */
	private const UNQUOTABLE = array( '"', '\\' );

	/**
	 * Build a complete `Content-Disposition: attachment` value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $filename Desired filename, any script.
	 *
	 * @return string Header value, both RFC 6266 forms included.
	 */
	public static function attachment( string $filename ): string {
		$name  = self::sanitize( $filename );
		$ascii = self::ascii_fallback( $name );

		return 'attachment; filename="' . $ascii . '"'
			. '; filename*=UTF-8\'\'' . rawurlencode( $name );
	}

	/**
	 * Reduce an arbitrary filename to a safe UTF-8 basename.
	 *
	 * Strips directory components, normalises to NFC, removes control,
	 * bidi and zero-width characters, collapses whitespace, and clamps
	 * the length without destroying the extension.
	 *
	 * @since 1.0.0
	 *
	 * @param string $filename Raw filename.
	 *
	 * @return string Never empty — falls back to `download`.
	 */
	public static function sanitize( string $filename ): string {
		$name = Filename::basename( $filename );

		// Anything that isn't valid UTF-8 can't be normalised or encoded
		// meaningfully, so salvage the ASCII from it and move on.
		if ( ! mb_check_encoding( $name, 'UTF-8' ) ) {
			$name = (string) mb_convert_encoding( $name, 'UTF-8', 'UTF-8' );
		}

		// NFC: macOS filenames arrive decomposed and would otherwise
		// percent-encode into something Windows renders incorrectly.
		if ( class_exists( \Normalizer::class ) ) {
			$normalized = \Normalizer::normalize( $name, \Normalizer::FORM_C );

			if ( false !== $normalized && null !== $normalized ) {
				$name = $normalized;
			}
		}

		$name = (string) preg_replace( self::CONTROL, '', $name );
		$name = (string) preg_replace( self::INVISIBLE, '', $name );
		$name = (string) preg_replace( self::WHITESPACE, ' ', $name );
		$name = trim( $name, " \t\n\r\0\x0B." );

		if ( '' === $name ) {
			return self::FALLBACK;
		}

		return Filename::clamp( $name, self::MAX_BYTES );
	}

	/**
	 * The ASCII-only `filename` fallback a quoted-string can carry.
	 *
	 * Two steps, and only the second is this class's business.
	 *
	 * Reducing a name to readable ASCII is {@see Filename::to_ascii()} —
	 * Cyrillic, Greek, Han, Kana and accented Latin all become something a
	 * person can read, and anything with no Latin representation is dropped.
	 * That is true of a filename wherever it is going.
	 *
	 * What is left here is what a *header* imposes: a quoted-string cannot
	 * carry an unescaped quote or backslash, and it cannot be empty — a
	 * `filename=""` is worse than no fallback at all, so a name with nothing
	 * readable left in its stem becomes `download`, keeping the extension so
	 * the file still opens in something.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Sanitised UTF-8 filename.
	 *
	 * @return string ASCII-safe, quoted-string-safe, never empty.
	 */
	public static function ascii_fallback( string $name ): string {
		$ascii = str_replace( self::UNQUOTABLE, '', Filename::to_ascii( $name ) );

		if ( '' === trim( Filename::stem( $ascii ), ' _.' ) ) {
			$extension = Filename::extension( '' === $ascii ? $name : $ascii );

			return '' === $extension ? self::FALLBACK : self::FALLBACK . '.' . $extension;
		}

		return trim( $ascii, ' _.' );
	}
}
