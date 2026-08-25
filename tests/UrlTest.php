<?php
/**
 * URL parsing tests.
 *
 * @package ArrayPress\S3Signer
 */

declare( strict_types=1 );

namespace ArrayPress\S3Signer\Tests;

use ArrayPress\S3Signer\Support\Url;
use PHPUnit\Framework\TestCase;

/**
 * Reducing whatever somebody pasted to the host part of it.
 *
 * Every shape below is one a real configuration screen produces, and the one
 * thing none of them may do is reach a `Host` header as written — a header of
 * `https://host/` fails with an opaque signature error rather than a clear
 * one.
 */
final class UrlTest extends TestCase {

	/**
	 * The host comes out of it.
	 *
	 * @dataProvider urlProvider
	 *
	 * @param string $given    What somebody pasted.
	 * @param string $expected The host.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'urlProvider' )]
	public function test_the_host_comes_out( string $given, string $expected ): void {
		$this->assertSame( $expected, Url::host( $given ) );
	}

	/**
	 * The shapes a configuration screen produces.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function urlProvider(): array {
		return [
			'a bare host'        => [ 's3.example.com', 's3.example.com' ],
			'https'              => [ 'https://s3.example.com', 's3.example.com' ],
			'a trailing slash'   => [ 'https://s3.example.com/', 's3.example.com' ],
			'a path'             => [ 'https://s3.example.com/bucket/key', 's3.example.com' ],
			'a query'            => [ 'https://s3.example.com?x=1', 's3.example.com' ],
			'a fragment'         => [ 'https://s3.example.com#x', 's3.example.com' ],
			'upper case'         => [ 'HTTPS://S3.EXAMPLE.COM', 's3.example.com' ],
			'padded'             => [ '  s3.example.com  ', 's3.example.com' ],
			'another scheme'     => [ 's3://s3.example.com', 's3.example.com' ],
			'nothing'            => [ '', '' ],
			'only a scheme'      => [ 'https://', '' ],
		];
	}

	/**
	 * A port survives, because a self-hosted endpoint has one.
	 *
	 * parse_url() is not used for exactly this: it reads `localhost:9000` as
	 * scheme `localhost` with path `9000`, which is the shape a MinIO
	 * endpoint arrives in.
	 */
	public function test_a_port_survives(): void {
		$this->assertSame( 'localhost:9000', Url::host( 'localhost:9000' ) );
		$this->assertSame( 'localhost:9000', Url::host( 'http://localhost:9000/' ) );
		$this->assertSame( 'minio.internal:9000', Url::host( 'https://minio.internal:9000/bucket' ) );
	}

	/**
	 * Credentials are cut off, and before the path is.
	 *
	 * This is new rather than a fix: the previous version left them on, and
	 * `key:secret@s3.example.com` then failed the "is this a host" check and
	 * threw. Safe, but the error said "must be a bare host" about a string
	 * that looked like one, which is a long afternoon.
	 */
	public function test_credentials_are_cut_off(): void {
		$this->assertSame( 's3.example.com', Url::host( 'https://key:secret@s3.example.com/bucket' ) );
		$this->assertSame( 's3.example.com', Url::host( 'key@s3.example.com' ) );
	}

	/**
	 * The root dot of a fully-qualified name goes.
	 *
	 * `example.com.` and `example.com` are the same host and sign
	 * differently.
	 */
	public function test_the_root_dot_goes(): void {
		$this->assertSame( 's3.example.com', Url::host( 's3.example.com.' ) );
	}
}
