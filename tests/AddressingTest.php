<?php
/**
 * Addressing style, provider factories, and input hardening.
 *
 * @package   ArrayPress\S3Signer
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\S3Signer\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ArrayPress\S3Signer\Enums\AddressingStyle;
use ArrayPress\S3Signer\Enums\Method;
use ArrayPress\S3Signer\Enums\Provider;
use ArrayPress\S3Signer\Signer;

final class AddressingTest extends TestCase {

	private const ACCESS_KEY = 'AKIAIOSFODNN7EXAMPLE';
	private const SECRET_KEY = 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY';
	private const TIMESTAMP  = 1700000000;

	/* ─── Addressing style ──────────────────────────────────────────── */

	public function test_path_style_puts_the_bucket_in_the_path(): void {
		$signer = new Signer( self::ACCESS_KEY, self::SECRET_KEY, 'storage.example.com', 'auto', self::TIMESTAMP, AddressingStyle::Path );

		$this->assertStringStartsWith(
			'https://storage.example.com/my-bucket/files/a.zip?',
			$signer->presign_get( 'my-bucket', 'files/a.zip', 60 )
		);
	}

	public function test_virtual_hosted_style_puts_the_bucket_in_the_host(): void {
		$signer = new Signer( self::ACCESS_KEY, self::SECRET_KEY, 's3.eu-west-2.amazonaws.com', 'eu-west-2', self::TIMESTAMP, AddressingStyle::VirtualHosted );

		$this->assertStringStartsWith(
			'https://my-bucket.s3.eu-west-2.amazonaws.com/files/a.zip?',
			$signer->presign_get( 'my-bucket', 'files/a.zip', 60 )
		);
	}

	public function test_virtual_hosted_style_omits_the_bucket_from_the_path(): void {
		$signer = new Signer( self::ACCESS_KEY, self::SECRET_KEY, 's3.example.com', 'auto', self::TIMESTAMP, AddressingStyle::VirtualHosted );
		$url    = $signer->presign_get( 'my-bucket', 'files/a.zip', 60 );

		// The bucket must appear exactly once — in the host.
		$this->assertSame( 1, substr_count( $url, 'my-bucket' ) );
	}

	public function test_addressing_style_changes_the_signature(): void {
		$path    = new Signer( self::ACCESS_KEY, self::SECRET_KEY, 's3.example.com', 'auto', self::TIMESTAMP, AddressingStyle::Path );
		$virtual = new Signer( self::ACCESS_KEY, self::SECRET_KEY, 's3.example.com', 'auto', self::TIMESTAMP, AddressingStyle::VirtualHosted );

		$this->assertNotSame(
			$path->presign_get( 'my-bucket', 'k', 60 ),
			$virtual->presign_get( 'my-bucket', 'k', 60 )
		);
	}

	public function test_virtual_hosted_host_header_is_the_signed_one(): void {
		$signer  = new Signer( self::ACCESS_KEY, self::SECRET_KEY, 's3.example.com', 'auto', self::TIMESTAMP, AddressingStyle::VirtualHosted );
		$request = $signer->sign_delete_object( 'my-bucket', 'k.zip' );

		$this->assertSame( 'my-bucket.s3.example.com', $request->headers['host'] );
		$this->assertSame( 'https://my-bucket.s3.example.com/k.zip', $request->url );
	}

	public function test_virtual_hosted_bucket_listing_uses_a_root_path(): void {
		$signer  = new Signer( self::ACCESS_KEY, self::SECRET_KEY, 's3.example.com', 'auto', self::TIMESTAMP, AddressingStyle::VirtualHosted );
		$request = $signer->sign_list_objects( 'my-bucket', 10 );

		$this->assertStringStartsWith( 'https://my-bucket.s3.example.com/?', $request->url );
	}

	#[DataProvider( 'undnsable_buckets' )]
	public function test_virtual_hosted_rejects_buckets_that_cannot_be_dns_labels( string $bucket ): void {
		$signer = new Signer( self::ACCESS_KEY, self::SECRET_KEY, 's3.example.com', 'auto', self::TIMESTAMP, AddressingStyle::VirtualHosted );

		$this->expectException( \InvalidArgumentException::class );
		$signer->presign_get( $bucket, 'k', 60 );
	}

	/** @return array<string, array{0: string}> */
	public static function undnsable_buckets(): array {
		return array(
			'uppercase'      => array( 'MyBucket' ),
			'underscore'     => array( 'my_bucket' ),
			'dotted'         => array( 'my.bucket' ),
			'too short'      => array( 'ab' ),
			'leading hyphen' => array( '-bucket' ),
			'host injection' => array( 'evil.attacker.com' ),
		);
	}

	public function test_path_style_tolerates_buckets_that_are_not_dns_labels(): void {
		$signer = new Signer( self::ACCESS_KEY, self::SECRET_KEY, 's3.example.com', 'auto', self::TIMESTAMP, AddressingStyle::Path );

		$this->assertStringContainsString( 'my.bucket', $signer->presign_get( 'my.bucket', 'k', 60 ) );
	}

	/* ─── Through the provider enum ─────────────────────────────────── */

	/**
	 * Every provider is reached through the enum, which is the only place
	 * that knows an endpoint, a default region or an addressing style.
	 *
	 * Signer used to carry five static factories holding a smaller second
	 * copy of that table — five of the eleven providers, with nothing
	 * keeping the two in step.
	 *
	 * @dataProvider providerProvider
	 *
	 * @param Provider $provider   Which provider.
	 * @param string   $region     Its region, where it has one.
	 * @param string   $account_id Its account id, where it needs one.
	 * @param string   $endpoint   Its endpoint, where it needs one.
	 * @param string   $expected   The URL the presigned GET should start with.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'providerProvider' )]
	public function test_a_provider_signs_against_its_own_endpoint(
		Provider $provider,
		string $region,
		string $account_id,
		string $endpoint,
		string $expected
	): void {
		$url = $provider
			->signer( self::ACCESS_KEY, self::SECRET_KEY, $region, $account_id, $endpoint, self::TIMESTAMP )
			->presign_get( 'my-bucket', 'k.zip', 60 );

		$this->assertStringStartsWith( $expected, $url );
	}

	/**
	 * One row per provider that can be reached without configuration.
	 *
	 * @return array<string, array{0: Provider, 1: string, 2: string, 3: string, 4: string}>
	 */
	public static function providerProvider(): array {
		return [
			'r2'           => [ Provider::R2, '', 'abc123', '', 'https://abc123.r2.cloudflarestorage.com/my-bucket/k.zip?' ],
			'aws'          => [ Provider::Aws, 'eu-west-2', '', '', 'https://my-bucket.s3.eu-west-2.amazonaws.com/k.zip?' ],
			'backblaze'    => [ Provider::Backblaze, 'us-west-004', '', '', 'https://my-bucket.s3.us-west-004.backblazeb2.com/k.zip?' ],
			'digitalocean' => [ Provider::DigitalOcean, 'ams3', '', '', 'https://my-bucket.ams3.digitaloceanspaces.com/k.zip?' ],
			'minio'        => [ Provider::MinIO, 'us-east-1', '', 'localhost:9000', 'https://localhost:9000/my-bucket/k.zip?' ],
		];
	}

	/**
	 * R2 signs against the account endpoint, in the auto region.
	 */
	public function test_r2_uses_the_account_endpoint_and_the_auto_region(): void {
		$url = Provider::R2
			->signer( self::ACCESS_KEY, self::SECRET_KEY, '', 'abc123', '', self::TIMESTAMP )
			->presign_get( 'my-bucket', 'files/a.zip', 60 );

		$this->assertStringStartsWith( 'https://abc123.r2.cloudflarestorage.com/my-bucket/files/a.zip?', $url );
		$this->assertStringContainsString( '%2Fauto%2Fs3%2Faws4_request', $url );
	}

	/**
	 * And matches a signer configured by hand, which is what it saves you.
	 */
	public function test_a_provider_matches_a_signer_configured_by_hand(): void {
		$manual = new Signer( self::ACCESS_KEY, self::SECRET_KEY, 'abc123.r2.cloudflarestorage.com', 'auto', self::TIMESTAMP );

		$this->assertSame(
			$manual->presign_get( 'my-bucket', 'files/sample.zip', 60 ),
			Provider::R2
				->signer( self::ACCESS_KEY, self::SECRET_KEY, '', 'abc123', '', self::TIMESTAMP )
				->presign_get( 'my-bucket', 'files/sample.zip', 60 )
		);
	}

	/**
	 * Signer holds no provider knowledge of its own.
	 *
	 * A second table is a second thing to keep in step, and the one that is
	 * not the enum is the one that goes stale — it covered five providers of
	 * eleven.
	 */
	public function test_the_signer_carries_no_provider_table(): void {
		// The filter is an OR, not an AND, so the static ones are picked out
		// afterwards rather than asked for.
		$methods = array_filter(
			( new \ReflectionClass( Signer::class ) )->getMethods( \ReflectionMethod::IS_PUBLIC ),
			static fn( \ReflectionMethod $method ): bool => $method->isStatic()
		);

		// content_disposition() is a convenience over ContentDisposition, not
		// a provider. Anything else static and public here is a factory
		// carrying a second copy of what Provider knows.
		$this->assertSame(
			[ 'content_disposition' ],
			array_values( array_map( static fn( \ReflectionMethod $method ): string => $method->getName(), $methods ) ),
			'Signer has picked up a static factory again; that knowledge belongs to Provider.'
		);
	}

	/* ─── Endpoint normalisation ────────────────────────────────────── */

	#[DataProvider( 'messy_endpoints' )]
	public function test_endpoints_are_normalised( string $input ): void {
		$signer = new Signer( self::ACCESS_KEY, self::SECRET_KEY, $input, 'auto', self::TIMESTAMP );

		$this->assertStringStartsWith( 'https://s3.example.com/b/k?', $signer->presign_get( 'b', 'k', 60 ) );
	}

	/** @return array<string, array{0: string}> */
	public static function messy_endpoints(): array {
		return array(
			'bare'            => array( 's3.example.com' ),
			'https scheme'    => array( 'https://s3.example.com' ),
			'http scheme'     => array( 'http://s3.example.com' ),
			'trailing slash'  => array( 'https://s3.example.com/' ),
			'with path'       => array( 'https://s3.example.com/some/path' ),
			'uppercase'       => array( 'S3.EXAMPLE.COM' ),
			'trailing dot'    => array( 's3.example.com.' ),
			'padded'          => array( '  s3.example.com  ' ),
		);
	}

	#[DataProvider( 'bad_endpoints' )]
	public function test_unusable_endpoints_are_rejected( string $input ): void {
		$this->expectException( \InvalidArgumentException::class );
		new Signer( self::ACCESS_KEY, self::SECRET_KEY, $input );
	}

	/** @return array<string, array{0: string}> */
	public static function bad_endpoints(): array {
		return array(
			'empty'     => array( '' ),
			'spaces'    => array( '   ' ),
			'newline'   => array( "s3.example.com\r\nX-Evil: 1" ),
			'space mid' => array( 's3.example .com' ),
			'scheme only' => array( 'https://' ),
		);
	}

	/* ─── Credential and region validation ──────────────────────────── */

	public function test_empty_credentials_are_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );
		new Signer( '', self::SECRET_KEY, 's3.example.com' );
	}

	public function test_empty_secret_is_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );
		new Signer( self::ACCESS_KEY, '  ', 's3.example.com' );
	}

	public function test_a_region_that_would_corrupt_the_scope_is_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );
		new Signer( self::ACCESS_KEY, self::SECRET_KEY, 's3.example.com', 'eu/west/2' );
	}

	/* ─── Header hardening ──────────────────────────────────────────── */

	public function test_crlf_in_a_header_value_is_rejected(): void {
		$signer = new Signer( self::ACCESS_KEY, self::SECRET_KEY, 's3.example.com', 'auto', self::TIMESTAMP );

		$this->expectException( \InvalidArgumentException::class );
		$signer->sign( Method::PUT, 'b', 'k', array(), 'body', array( 'content-type' => "text/plain\r\nX-Evil: yes" ) );
	}

	public function test_null_bytes_in_a_header_value_are_rejected(): void {
		$signer = new Signer( self::ACCESS_KEY, self::SECRET_KEY, 's3.example.com', 'auto', self::TIMESTAMP );

		$this->expectException( \InvalidArgumentException::class );
		$signer->sign( Method::PUT, 'b', 'k', array(), '', array( 'content-type' => "text/plain\x00" ) );
	}

	#[DataProvider( 'bad_header_names' )]
	public function test_invalid_header_names_are_rejected( string $name ): void {
		$signer = new Signer( self::ACCESS_KEY, self::SECRET_KEY, 's3.example.com', 'auto', self::TIMESTAMP );

		$this->expectException( \InvalidArgumentException::class );
		$signer->sign( Method::PUT, 'b', 'k', array(), '', array( $name => 'value' ) );
	}

	/** @return array<string, array{0: string}> */
	public static function bad_header_names(): array {
		return array(
			'colon'   => array( 'content-type:x' ),
			'space'   => array( 'content type' ),
			'newline' => array( "x-evil\r\nx" ),
			'empty'   => array( '' ),
		);
	}

	public function test_internal_whitespace_in_header_values_is_collapsed(): void {
		$signer  = new Signer( self::ACCESS_KEY, self::SECRET_KEY, 's3.example.com', 'auto', self::TIMESTAMP );
		$request = $signer->sign( Method::PUT, 'b', 'k', array(), '', array( 'content-type' => "text/plain;   charset=utf-8" ) );

		$this->assertSame( 'text/plain; charset=utf-8', $request->headers['content-type'] );
	}

	/* ─── Expiry validation ─────────────────────────────────────────── */

	public function test_expiry_beyond_the_sigv4_maximum_is_rejected(): void {
		$signer = new Signer( self::ACCESS_KEY, self::SECRET_KEY, 's3.example.com', 'auto', self::TIMESTAMP );

		$this->expectException( \InvalidArgumentException::class );
		$signer->presign_get( 'b', 'k', Signer::MAX_EXPIRES + 1 );
	}

	public function test_the_sigv4_maximum_itself_is_accepted(): void {
		$signer = new Signer( self::ACCESS_KEY, self::SECRET_KEY, 's3.example.com', 'auto', self::TIMESTAMP );

		$this->assertStringContainsString( 'X-Amz-Expires=604800', $signer->presign_get( 'b', 'k', Signer::MAX_EXPIRES ) );
	}

	public function test_a_non_positive_expiry_is_rejected(): void {
		$signer = new Signer( self::ACCESS_KEY, self::SECRET_KEY, 's3.example.com', 'auto', self::TIMESTAMP );

		$this->expectException( \InvalidArgumentException::class );
		$signer->presign_get( 'b', 'k', 0 );
	}
}
