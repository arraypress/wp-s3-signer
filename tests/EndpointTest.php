<?php
/**
 * Endpoint and canonical URI tests.
 *
 * @package ArrayPress\S3Signer
 */

declare( strict_types=1 );

namespace ArrayPress\S3Signer\Tests;

use ArrayPress\S3Signer\Enums\AddressingStyle;
use ArrayPress\S3Signer\Support\Endpoint;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Where a request goes, and what its path is once the bucket has been put
 * wherever the addressing style says it belongs.
 *
 * The Host header and the canonical URI are both signed, so putting the
 * bucket in the hostname *and* in the path produces a signature for a request
 * nobody made — which is why the two answers live in one class.
 */
final class EndpointTest extends TestCase {

	/**
	 * A pasted endpoint is reduced to a bare host.
	 *
	 * @dataProvider endpointProvider
	 *
	 * @param string $given    What somebody pasted.
	 * @param string $expected The host it becomes.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'endpointProvider' )]
	public function test_an_endpoint_is_reduced_to_a_host( string $given, string $expected ): void {
		$this->assertSame( $expected, Endpoint::normalize( $given ) );
	}

	/**
	 * The shapes a dashboard hands out.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function endpointProvider(): array {
		return [
			'bare'             => [ 's3.eu-west-2.amazonaws.com', 's3.eu-west-2.amazonaws.com' ],
			'with a scheme'    => [ 'https://s3.eu-west-2.amazonaws.com', 's3.eu-west-2.amazonaws.com' ],
			'with a slash'     => [ 'https://s3.eu-west-2.amazonaws.com/', 's3.eu-west-2.amazonaws.com' ],
			'with a path'      => [ 'https://s3.eu-west-2.amazonaws.com/bucket', 's3.eu-west-2.amazonaws.com' ],
			'upper case'       => [ 'S3.EU-WEST-2.AMAZONAWS.COM', 's3.eu-west-2.amazonaws.com' ],
			'with a port'      => [ 'http://localhost:9000', 'localhost:9000' ],
			'padded'           => [ '  s3.example.com  ', 's3.example.com' ],
			'a trailing dot'   => [ 's3.example.com.', 's3.example.com' ],
		];
	}

	/**
	 * Something that is not a host is refused.
	 *
	 * @dataProvider badEndpointProvider
	 *
	 * @param string $given What somebody pasted.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'badEndpointProvider' )]
	public function test_a_bad_endpoint_is_refused( string $given ): void {
		$this->expectException( InvalidArgumentException::class );

		Endpoint::normalize( $given );
	}

	/**
	 * Ways of not being a host.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function badEndpointProvider(): array {
		return [
			'empty'         => [ '' ],
			'only a scheme' => [ 'https://' ],
			'a space'       => [ 's3 example.com' ],
			'an underscore' => [ 's3_example.com' ],
		];
	}

	/**
	 * Path-style keeps the endpoint and puts the bucket first in the path.
	 */
	public function test_path_style_puts_the_bucket_in_the_path(): void {
		$this->assertSame( 's3.example.com', Endpoint::host( 's3.example.com', 'my-bucket', AddressingStyle::Path ) );
		$this->assertSame( '/my-bucket/a/b.zip', Endpoint::canonical_uri( 'my-bucket', 'a/b.zip', AddressingStyle::Path ) );
	}

	/**
	 * Virtual-hosted puts it in the hostname and leaves it out of the path.
	 *
	 * Both, or the signature is for a request nobody made.
	 */
	public function test_virtual_hosted_puts_the_bucket_in_the_host_only(): void {
		$this->assertSame(
			'my-bucket.s3.example.com',
			Endpoint::host( 's3.example.com', 'my-bucket', AddressingStyle::VirtualHosted )
		);

		$uri = Endpoint::canonical_uri( 'my-bucket', 'a/b.zip', AddressingStyle::VirtualHosted );

		$this->assertSame( '/a/b.zip', $uri );
		$this->assertStringNotContainsString( 'my-bucket', $uri );
	}

	/**
	 * A bucket that cannot be a DNS label is refused virtual-hosted.
	 *
	 * A dotted bucket does not match a wildcard certificate.
	 */
	public function test_a_dotted_bucket_is_refused_virtual_hosted(): void {
		$this->expectException( InvalidArgumentException::class );

		Endpoint::host( 's3.example.com', 'my.bucket', AddressingStyle::VirtualHosted );
	}

	/**
	 * And is fine path-style, where it never reaches the hostname.
	 */
	public function test_a_dotted_bucket_is_fine_path_style(): void {
		$this->assertSame( 's3.example.com', Endpoint::host( 's3.example.com', 'my.bucket', AddressingStyle::Path ) );
		$this->assertSame( '/my.bucket/k', Endpoint::canonical_uri( 'my.bucket', 'k', AddressingStyle::Path ) );
	}

	/**
	 * A key is percent-encoded but keeps its slashes.
	 */
	public function test_a_key_keeps_its_slashes(): void {
		$this->assertSame( 'a/b/c.zip', Endpoint::encode_key( 'a/b/c.zip' ) );
		$this->assertSame( 'a/my%20file.zip', Endpoint::encode_key( 'a/my file.zip' ) );
		$this->assertSame( 'a/b.zip', Endpoint::encode_key( '/a/b.zip' ) );
		$this->assertSame( 'caf%C3%A9.zip', Endpoint::encode_key( 'café.zip' ) );
	}

	/**
	 * No key addresses the bucket itself.
	 */
	public function test_no_key_addresses_the_bucket(): void {
		$this->assertSame( '/my-bucket', Endpoint::canonical_uri( 'my-bucket', '', AddressingStyle::Path ) );
		$this->assertSame( '/', Endpoint::canonical_uri( 'my-bucket', '', AddressingStyle::VirtualHosted ) );
	}

	/**
	 * The query is sorted by name and RFC 3986 encoded.
	 *
	 * Sorted because the provider sorts it too, and encoded that way because
	 * `+` for a space is RFC 1738 and produces a different signature.
	 */
	public function test_the_query_is_sorted_and_encoded(): void {
		$this->assertSame(
			'a=one%20two&b=x%2Fy&c=3',
			Endpoint::canonical_query( [ 'c' => '3', 'a' => 'one two', 'b' => 'x/y' ] )
		);
	}
}
