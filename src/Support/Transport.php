<?php
/**
 * Executes a signed request through the WordPress HTTP API.
 *
 * @package   ArrayPress\S3Signer
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\S3Signer\Support;

use ArrayPress\S3Signer\Models\SignedRequest;
use WP_Error;

/**
 * Class Transport
 *
 * The signer itself performs no I/O, which is what makes it testable. This is
 * the one place that does, and it is deliberately thin: it hands a
 * {@see SignedRequest} to `wp_remote_request()` and returns the raw status,
 * headers and body. Interpreting an S3 XML error body, retrying, or mapping
 * onto a response object is the caller's business.
 *
 * Kept in this package rather than the consuming plugin so the signing and
 * sending rules stay adjacent — in particular the requirement not to let
 * anything modify the headers between the two, which would invalidate the
 * signature.
 *
 * @since 1.0.0
 */
final class Transport {

	/**
	 * Send a signed request.
	 *
	 * The signature covers exactly the headers on the request, so they are
	 * passed through untouched. `wp_remote_request()` adds its own
	 * `User-Agent` and `Accept-Encoding`, which is safe: neither is in
	 * SignedHeaders, and S3 only verifies the headers the signature names.
	 *
	 * @since 1.0.0
	 *
	 * @param SignedRequest $request The request to send.
	 * @param array         $args    Extra arguments for wp_remote_request(),
	 *                               e.g. ['timeout' => 30]. `method`, `headers`
	 *                               and `body` are set from the request and
	 *                               cannot be overridden.
	 *
	 * @return array{status:int, headers:array<string,string>, body:string}|WP_Error
	 */
	public static function send( SignedRequest $request, array $args = array() ) {
		// Overriding any of these would either change what was signed or send
		// something other than the signed request, so they win over $args.
		$args = array_merge(
			array(
				'timeout'     => 30,
				'redirection' => 0,
			),
			$args,
			array(
				'method'  => $request->method->value,
				'headers' => $request->headers,
				'body'    => '' === $request->body ? null : $request->body,
			)
		);

		$response = wp_remote_request( $request->url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$headers = wp_remote_retrieve_headers( $response );

		return array(
			'status'  => (int) wp_remote_retrieve_response_code( $response ),
			'headers' => is_object( $headers ) && method_exists( $headers, 'getAll' )
				? $headers->getAll()
				: (array) $headers,
			'body'    => (string) wp_remote_retrieve_body( $response ),
		);
	}

	/**
	 * Whether a status code represents success.
	 *
	 * @since 1.0.0
	 *
	 * @param int $status HTTP status code.
	 *
	 * @return bool
	 */
	public static function is_success( int $status ): bool {
		return $status >= 200 && $status < 300;
	}
}
