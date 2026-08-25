<?php
/**
 * Header canonicalisation for SigV4.
 *
 * @package   ArrayPress\S3Signer
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.2.0
 */

declare( strict_types=1 );

namespace ArrayPress\S3Signer\Support;

use InvalidArgumentException;

/**
 * Class Headers
 *
 * SigV4 signs a *canonical* form of the headers, not the headers as written:
 * names lower-cased, values with internal whitespace runs collapsed, sorted
 * by name, one per line. Get any part of that wrong and the provider answers
 * `SignatureDoesNotMatch`, which says nothing about which header it was.
 *
 * So the shaping lives here rather than inline in the middle of building a
 * request, and the two things that are refused rather than fixed are refused
 * for the same reason: a header this library quietly repaired would be signed
 * in one form and sent in another.
 *
 * @since 1.2.0
 */
final class Headers {

	/**
	 * Put a caller's headers into the form SigV4 signs.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, string> $headers Headers as the caller wrote them.
	 *
	 * @return array<string, string> Lower-cased names, collapsed values, sorted.
	 *
	 * @throws InvalidArgumentException On a name outside RFC 7230's token set,
	 *                                 or a value containing CR, LF or null.
	 */
	public static function canonicalize( array $headers ): array {
		$canonical = array();

		foreach ( $headers as $name => $value ) {
			$name  = strtolower( trim( (string) $name ) );
			$value = (string) $value;

			if ( ! Validate::header_name( $name ) ) {
				throw new InvalidArgumentException( 'Invalid HTTP header name: ' . $name );
			}

			if ( ! Validate::header_value( $value ) ) {
				throw new InvalidArgumentException( 'Header value for "' . $name . '" contains line breaks.' );
			}

			$canonical[ $name ] = self::collapse( $value );
		}

		ksort( $canonical );

		return $canonical;
	}

	/**
	 * The canonical headers block: `name:value` per line, trailing newline.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, string> $headers Already canonicalised and sorted.
	 *
	 * @return string
	 */
	public static function block( array $headers ): string {
		$block = '';

		foreach ( $headers as $name => $value ) {
			$block .= $name . ':' . $value . "\n";
		}

		return $block;
	}

	/**
	 * The `SignedHeaders` list: names, semicolon-separated, in order.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, string> $headers Already canonicalised and sorted.
	 *
	 * @return string
	 */
	public static function names( array $headers ): string {
		return implode( ';', array_keys( $headers ) );
	}

	/**
	 * Collapse internal whitespace, as SigV4 canonicalisation requires.
	 *
	 * @since 1.2.0
	 *
	 * @param string $value Header value.
	 *
	 * @return string
	 */
	private static function collapse( string $value ): string {
		return trim( (string) preg_replace( '/\s+/', ' ', $value ) );
	}
}
