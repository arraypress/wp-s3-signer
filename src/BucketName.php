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

namespace ArrayPress\S3Signer;

/**
 * Class BucketName
 *
 * Two different questions get asked about a bucket name, and conflating them
 * produces validators that quietly disagree:
 *
 *   - {@see is_valid()} — is this a legal S3 bucket name at all? Applies
 *     whatever the addressing style.
 *   - {@see is_dns_compatible()} — can it additionally be used as a DNS label,
 *     which virtual-hosted addressing requires? Stricter: no dots, because a
 *     dotted bucket breaks wildcard TLS certificate matching.
 *
 * A name can be perfectly valid and still unusable virtual-hosted. `my.bucket`
 * is the common case.
 *
 * @since 1.1.0
 */
final class BucketName {

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
	public static function is_valid( string $bucket ): bool {
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
	public static function is_dns_compatible( string $bucket ): bool {
		return self::is_valid( $bucket ) && ! str_contains( $bucket, '.' );
	}
}
