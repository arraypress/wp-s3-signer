<?php
/**
 * Endpoint, host and canonical URI construction.
 *
 * @package   ArrayPress\S3Signer
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.2.0
 */

declare( strict_types=1 );

namespace ArrayPress\S3Signer\Support;

use ArrayPress\S3Signer\Enums\AddressingStyle;
use InvalidArgumentException;

/**
 * Class Endpoint
 *
 * Where a request goes, and what its path looks like once the bucket has been
 * put wherever the addressing style says it belongs.
 *
 * These three answers have to agree with each other exactly: the `Host`
 * header and the canonical URI are both signed, so putting the bucket in the
 * hostname and *also* in the path produces a signature for a request nobody
 * made. Keeping them in one class is what makes that visible.
 *
 * @since 1.2.0
 */
final class Endpoint {

	/**
	 * Reduce a configured endpoint to a bare host.
	 *
	 * Callers reasonably paste `https://host/` out of a dashboard. Signing
	 * that verbatim produces a `Host` header of `https://host/`, which fails
	 * with an opaque signature error rather than a clear one.
	 *
	 * @since 1.2.0
	 *
	 * @param string $endpoint Raw endpoint.
	 *
	 * @return string Bare host, lower-cased, optionally with a port.
	 *
	 * @throws InvalidArgumentException When nothing usable remains.
	 */
	public static function normalize( string $endpoint ): string {
		$host = Url::host( $endpoint );

		if ( '' === $host || ! Validate::host( $host ) ) {
			throw new InvalidArgumentException( 'S3 endpoint must be a bare host, e.g. "s3.eu-west-2.amazonaws.com".' );
		}

		return $host;
	}

	/**
	 * The `Host` header for a request against a bucket.
	 *
	 * Under virtual-hosted addressing the bucket becomes a DNS label, so the
	 * name is checked here — an incompatible one would otherwise be signed
	 * into a hostname that cannot resolve, or whose wildcard certificate will
	 * not match.
	 *
	 * @since 1.2.0
	 *
	 * @param string          $endpoint   Normalised host.
	 * @param string          $bucket     Bucket name.
	 * @param AddressingStyle $addressing Where the bucket goes.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException When the bucket cannot be a DNS label.
	 */
	public static function host( string $endpoint, string $bucket, AddressingStyle $addressing ): string {
		if ( AddressingStyle::Path === $addressing ) {
			return $endpoint;
		}

		if ( ! Validate::dns_label( $bucket ) ) {
			throw new InvalidArgumentException(
				'Bucket "' . $bucket . '" cannot be used with virtual-hosted addressing: '
				. 'it must be 3-63 characters of lowercase letters, digits and hyphens, with no dots.'
			);
		}

		return $bucket . '.' . $endpoint;
	}

	/**
	 * The canonical resource path for a bucket and optional key.
	 *
	 * Under virtual-hosted addressing the bucket lives in the hostname, so it
	 * must not appear in the path as well.
	 *
	 * @since 1.2.0
	 *
	 * @param string          $bucket     Bucket name.
	 * @param string          $key        Object key, or '' for the bucket itself.
	 * @param AddressingStyle $addressing Where the bucket goes.
	 *
	 * @return string
	 */
	public static function canonical_uri( string $bucket, string $key, AddressingStyle $addressing ): string {
		if ( AddressingStyle::VirtualHosted === $addressing ) {
			return '' === $key ? '/' : '/' . self::encode_key( $key );
		}

		$uri = '/' . rawurlencode( $bucket );

		if ( '' !== $key ) {
			$uri .= '/' . self::encode_key( $key );
		}

		return $uri;
	}

	/**
	 * Percent-encode an object key, keeping its slashes.
	 *
	 * Everything is encoded and then `/` is put back, so a multi-segment key
	 * keeps its hierarchy while every other character is escaped.
	 *
	 * @since 1.2.0
	 *
	 * @param string $key Raw object key. A leading slash is dropped.
	 *
	 * @return string
	 */
	public static function encode_key( string $key ): string {
		return str_replace( '%2F', '/', rawurlencode( ltrim( $key, '/' ) ) );
	}

	/**
	 * The canonical query string: sorted by name, RFC 3986 encoded.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, string> $query Unencoded parameters.
	 *
	 * @return string
	 */
	public static function canonical_query( array $query ): string {
		ksort( $query );

		return http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
	}
}
