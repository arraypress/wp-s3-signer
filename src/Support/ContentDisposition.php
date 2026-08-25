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
	 * Produce the ASCII-only `filename` fallback.
	 *
	 * Transliterates rather than blanking: Cyrillic, Greek, Han, Kana and
	 * accented Latin all become readable ASCII. Anything with no sensible
	 * Latin representation (emoji, symbols) is dropped, and if that
	 * leaves nothing the extension is preserved onto a generic stem.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Sanitised UTF-8 filename.
	 *
	 * @return string ASCII-safe, quoted-string-safe filename.
	 */
	public static function ascii_fallback( string $name ): string {
		$ascii = self::transliterate( $name );

		// Whatever survived that isn't printable ASCII becomes '_'.
		$ascii = (string) preg_replace( '/[^\x20-\x7E]/', '_', $ascii );

		$ascii = str_replace( self::UNQUOTABLE, '', $ascii );

		// Long underscore runs are what a blanked-out script looks like.
		$ascii = (string) preg_replace( '/_{2,}/', '_', $ascii );
		$ascii = (string) preg_replace( '/\s{2,}/', ' ', $ascii );

		// Trim separators but keep any dot, so an all-transliterated-away
		// stem (emoji, symbols) is still recognisable as ".ext" below.
		$ascii = trim( $ascii, ' _' );

		// Nothing readable left in the stem — keep the extension, and
		// give it a generic name rather than returning a bare "wav".
		if ( '' === trim( Filename::stem( $ascii ), ' _.' ) ) {
			$extension = Filename::extension( '' === $ascii ? $name : $ascii );

			return '' === $extension ? self::FALLBACK : self::FALLBACK . '.' . $extension;
		}

		return trim( $ascii, ' _.' );
	}

	/**
	 * Best-effort script-to-Latin conversion.
	 *
	 * Prefers ext-intl's transliterator, which understands Han, Kana,
	 * Cyrillic, Greek, Arabic and more. Without intl, iconv handles
	 * accented Latin and drops the rest.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name UTF-8 string.
	 *
	 * @return string
	 */
	private static function transliterate( string $name ): string {
		// Building a Transliterator costs roughly ten times as much as
		// the signature it is helping to produce, so it is built once per
		// process. `false` is the "tried, unavailable" sentinel, to avoid
		// paying for the lookup again on every call.
		static $transliterator = null;

		if ( null === $transliterator ) {
			$transliterator = class_exists( \Transliterator::class )
				? ( \Transliterator::create( 'Any-Latin; Latin-ASCII' ) ?? false )
				: false;
		}

		if ( false !== $transliterator ) {
			$result = $transliterator->transliterate( $name );

			if ( false !== $result ) {
				return $result;
			}
		}

		$previous = setlocale( LC_CTYPE, '0' );
		setlocale( LC_CTYPE, 'C.UTF-8', 'en_US.UTF-8', 'C' );

		// iconv() emits a notice for any character it cannot transliterate,
		// which is the ordinary case for a filename in a script this locale
		// does not cover. The false return is checked below.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$result = @iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $name );

		if ( false !== $previous ) {
			setlocale( LC_CTYPE, $previous );
		}

		if ( false === $result ) {
			return $name;
		}

		// iconv's TRANSLIT emits things like "?" and "'a" for characters
		// it can only approximate; strip the noise it leaves behind.
		return (string) preg_replace( '/[?]+/', '', $result );
	}
}
