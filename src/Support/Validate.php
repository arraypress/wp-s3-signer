<?php
/**
 * Bucket name rules.
 *
 * @package   ArrayPress\S3Signer
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.1.0
 */

declare( strict_types=1 );

namespace ArrayPress\S3Signer\Support;

use ArrayPress\S3Signer\Enums\Provider;

/**
 * Class Validate
 *
 * Two different questions get asked about a bucket name, and conflating them
 * produces validators that quietly disagree:
 *
 *   - {@see bucket()} — is this a legal S3 bucket name at all? Applies
 *     whatever the addressing style.
 *   - {@see dns_label()} — can it additionally be used as a DNS label,
 *     which virtual-hosted addressing requires? Stricter: no dots, because a
 *     dotted bucket breaks wildcard TLS certificate matching.
 *
 * A name can be perfectly valid and still unusable virtual-hosted. `my.bucket`
 * is the common case.
 *
 * @since 1.1.0
 */
final class Validate {

	/**
	 * Whether a name is a legal S3 bucket name.
	 *
	 * Lowercase is not a stylistic preference — S3 rejects uppercase outright,
	 * so a validator that accepts it passes names the provider will not.
	 *
	 * Provider-specific reserved prefixes and suffixes (AWS's `xn--`,
	 * `-s3alias` and similar) are deliberately not enforced here: they do not
	 * apply to R2, MinIO or the other supported backends, and the provider
	 * rejects them clearly enough on its own.
	 *
	 * @since 1.1.0
	 *
	 * @param string $bucket Bucket name.
	 *
	 * @return bool
	 */
	public static function bucket( string $bucket ): bool {
		$length = strlen( $bucket );

		if ( $length < 3 || $length > 63 ) {
			return false;
		}

		// Lowercase letters, digits, dots and hyphens; must start and end with
		// a letter or digit.
		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9.\-]*[a-z0-9]$/', $bucket ) ) {
			return false;
		}

		// Adjacent dots are rejected by S3.
		if ( str_contains( $bucket, '..' ) ) {
			return false;
		}

		// A name shaped like an IPv4 address is ambiguous with an endpoint.
		if ( 1 === preg_match( '/^\d{1,3}(\.\d{1,3}){3}$/', $bucket ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether a name can additionally serve as a DNS label.
	 *
	 * Virtual-hosted addressing puts the bucket in the hostname, so the name
	 * must be DNS-legal. Dots are excluded: a dotted bucket does not match a
	 * wildcard certificate, which is why AWS has long refused to create dotted
	 * buckets for virtual-hosted use.
	 *
	 * @since 1.1.0
	 *
	 * @param string $bucket Bucket name.
	 *
	 * @return bool
	 */
	public static function dns_label( string $bucket ): bool {
		return self::bucket( $bucket ) && ! str_contains( $bucket, '.' );
	}

	/**
	 * Whether a region name is one that can go in a credential scope.
	 *
	 * The region is interpolated into the scope, and the scope is signed. A
	 * stray slash there produces a scope the provider does not recognise and
	 * a `SignatureDoesNotMatch` that says nothing about why — which is worth
	 * refusing up front rather than debugging at three in the morning.
	 *
	 * @since 1.2.0
	 *
	 * @param string $region Region name.
	 *
	 * @return bool
	 */
	public static function region( string $region ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9\-]+$/', $region );
	}

	/**
	 * Whether a host is one that can be signed against.
	 *
	 * A bare host, optionally with a port. Callers reasonably paste
	 * `https://host/` out of a dashboard, and signing that verbatim produces
	 * a `Host` header of `https://host/`.
	 *
	 * @since 1.2.0
	 *
	 * @param string $host Host, without a scheme.
	 *
	 * @return bool
	 */
	public static function host( string $host ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host );
	}

	/**
	 * Whether a string is a legal HTTP header name.
	 *
	 * RFC 7230's token set. A name outside it corrupts the canonical
	 * request, and one containing a colon or a newline is a header-injection
	 * attempt rather than a typo.
	 *
	 * @since 1.2.0
	 *
	 * @param string $name Header name, already lower-cased.
	 *
	 * @return bool
	 */
	public static function header_name( string $name ): bool {
		return 1 === preg_match( '/^[a-z0-9!#$%&\'*+\-.^_`|~]+$/', $name );
	}

	/**
	 * Whether a header value can be sent as it is.
	 *
	 * CR, LF and null all split a request when the headers reach a client
	 * that writes them out. Refused rather than stripped, so a mangled value
	 * is never signed and sent — a signature over a value the server never
	 * receives fails in a way nobody can read.
	 *
	 * @since 1.2.0
	 *
	 * @param string $value Header value.
	 *
	 * @return bool
	 */
	public static function header_value( string $value ): bool {
		return 1 !== preg_match( '/[\r\n\x00]/', $value );
	}

	/**
	 * Whether a credential is present.
	 *
	 * @since 1.2.0
	 *
	 * @param string $credential Access key or secret.
	 *
	 * @return bool
	 */
	public static function credential( string $credential ): bool {
		return '' !== trim( $credential );
	}
}
