<?php
/**
 * URL parsing.
 *
 * @package   ArrayPress\S3Signer
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.2.0
 */

declare( strict_types=1 );

namespace ArrayPress\S3Signer\Support;

/**
 * Class Url
 *
 * One method, and it earns the file: reducing whatever somebody pasted to the
 * host part of it is a step that has nothing to do with S3, and leaving it
 * inline meant {@see Endpoint::normalize()} opened with four unexplained
 * lines before it got to the question it was actually asking.
 *
 * `parse_url()` is not used. It refuses a bare host with a port —
 * `localhost:9000` parses as scheme `localhost`, path `9000` — which is
 * exactly the case a self-hosted MinIO endpoint arrives as.
 *
 * @since 1.2.0
 */
final class Url {

	/**
	 * The host part of whatever was given.
	 *
	 * Tolerant on purpose. Callers paste `https://host/` out of a dashboard,
	 * or type a bare host, or leave a trailing slash, or leave the root dot
	 * on a fully-qualified name. All four mean the same host, and the one
	 * thing they must not do is reach a `Host` header as written.
	 *
	 * No validation: whether the result is a *usable* host is a separate
	 * question, and one the caller is better placed to ask — see
	 * {@see Validate::host()}.
	 *
	 * @since 1.2.0
	 *
	 * @param string $url A URL, or a host, or something in between.
	 *
	 * @return string The host, lower-cased, with any port kept. '' when
	 *                there is nothing host-shaped in it.
	 */
	public static function host( string $url ): string {
		$host = trim( $url );

		// A scheme, if there is one. Deliberately any scheme rather than
		// http and https alone: `s3://` and `r2://` both turn up in
		// configuration copied from other tools.
		$host = (string) preg_replace( '#^[a-z][a-z0-9+.\-]*://#i', '', $host );

		// Credentials, before the path is cut. Left on, `key:secret@host`
		// fails the "is this a host" check further up the call and throws
		// "must be a bare host" about a string that looks like one.
		if ( str_contains( $host, '@' ) ) {
			$host = substr( $host, (int) strrpos( $host, '@' ) + 1 );
		}

		// Everything from the first slash, question mark or hash on.
		$host = (string) strtok( $host, '/?#' );

		// The root dot of a fully-qualified name. `example.com.` and
		// `example.com` are the same host and sign differently.
		return strtolower( rtrim( $host, '.' ) );
	}
}
