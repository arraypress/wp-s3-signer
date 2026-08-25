<?php
/**
 * Filename operations.
 *
 * @package   ArrayPress\S3Signer
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.2.0
 */

declare( strict_types=1 );

namespace ArrayPress\S3Signer\Support;

/**
 * Class Filename
 *
 * Things that are true of a filename whatever it is for. Nothing here knows
 * about Content-Disposition, headers or S3 — that is the test for whether a
 * function belongs here rather than in {@see ContentDisposition}, which deals
 * with what a *header* can carry.
 *
 * Kept inside this library rather than taken from a filename package: the
 * signer has no dependencies at all, which is most of why it is a signer
 * rather than an SDK, and one truncation function is not worth changing that.
 *
 * @since 1.2.0
 */
final class Filename {

	/**
	 * The most characters of an extension worth keeping.
	 *
	 * Long enough for the real ones and short enough that a "file.zzzzz…"
	 * cannot eat a whole budget.
	 */
	private const MAX_EXTENSION = 10;

	/**
	 * The last segment of a path, whichever separator was used.
	 *
	 * Windows separators are converted first, because `basename()` only
	 * understands the host's — so on Linux a name of
	 * `..\\..\\windows\\system32\\evil.exe` comes back whole.
	 *
	 * @since 1.2.0
	 *
	 * @param string $path A filename, or something with one on the end.
	 *
	 * @return string
	 */
	public static function basename( string $path ): string {
		return basename( str_replace( '\\', '/', $path ) );
	}

	/**
	 * A filename's extension, without its dot.
	 *
	 * Reduced to letters and digits and clamped, so an "extension" that is
	 * really the rest of an attack cannot be carried through.
	 *
	 * @since 1.2.0
	 *
	 * @param string $name A filename.
	 *
	 * @return string Lower-cased, or '' when there is none worth keeping.
	 */
	public static function extension( string $name ): string {
		$extension = (string) preg_replace( '/[^A-Za-z0-9]/', '', pathinfo( $name, PATHINFO_EXTENSION ) );

		return strtolower( substr( $extension, 0, self::MAX_EXTENSION ) );
	}

	/**
	 * A filename's stem: everything before the extension.
	 *
	 * @since 1.2.0
	 *
	 * @param string $name A filename.
	 *
	 * @return string
	 */
	public static function stem( string $name ): string {
		return (string) pathinfo( $name, PATHINFO_FILENAME );
	}

	/**
	 * Shorten a filename to a byte budget without losing its extension.
	 *
	 * Bytes rather than characters, because the limits this exists for —
	 * header lengths, filesystem name limits — are counted in bytes and a
	 * name of two hundred Han characters is six hundred of them.
	 *
	 * The cut is multibyte-safe, so a shortened name never ends in half a
	 * character; and the extension is put back afterwards, because a file
	 * that arrives as `report-of-the-annual` opens in nothing.
	 *
	 * @since 1.2.0
	 *
	 * @param string $name  A filename.
	 * @param int    $bytes The budget.
	 *
	 * @return string
	 */
	public static function clamp( string $name, int $bytes ): string {
		if ( strlen( $name ) <= $bytes ) {
			return $name;
		}

		$extension = self::extension( $name );
		$suffix    = '' === $extension ? '' : '.' . $extension;
		$stem      = '' === $suffix ? $name : substr( $name, 0, strlen( $name ) - strlen( $suffix ) );

		// At least one character of stem, however small the budget: a name
		// that is only an extension is a hidden file on most systems.
		$stem = mb_strcut( $stem, 0, max( 1, $bytes - strlen( $suffix ) ), 'UTF-8' );

		return rtrim( $stem, ' .' ) . $suffix;
	}
}
