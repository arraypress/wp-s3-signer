<?php
/**
 * Validation tests for the checks that were inline.
 *
 * @package ArrayPress\S3Signer
 */

declare( strict_types=1 );

namespace ArrayPress\S3Signer\Tests;

use ArrayPress\S3Signer\Support\Validate;
use PHPUnit\Framework\TestCase;

/**
 * Every "is this allowed" question, asked in one place.
 *
 * They used to be regular expressions in the middle of a constructor and a
 * signing loop, each with a comment above it explaining what it was for. The
 * comment is the answer to "what is this checking"; the method name is a
 * better one, and can be tested.
 */
final class ValidateExtrasTest extends TestCase {

	/**
	 * A region is letters, digits and hyphens.
	 *
	 * It is interpolated into the credential scope, and the scope is signed.
	 * A stray slash produces a scope the provider does not recognise and a
	 * SignatureDoesNotMatch that says nothing about why.
	 *
	 * @dataProvider regionProvider
	 *
	 * @param string $region   The region.
	 * @param bool   $expected Whether it is usable.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'regionProvider' )]
	public function test_a_region_is_scope_safe( string $region, bool $expected ): void {
		$this->assertSame( $expected, Validate::region( $region ) );
	}

	/**
	 * Regions real and impossible.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public static function regionProvider(): array {
		return [
			'aws'        => [ 'eu-west-2', true ],
			'auto'       => [ 'auto', true ],
			'backblaze'  => [ 'us-west-004', true ],
			'a slash'    => [ 'eu/west-2', false ],
			'a space'    => [ 'eu west 2', false ],
			'empty'      => [ '', false ],
			'a newline'  => [ "eu-west-2\nx", false ],
		];
	}

	/**
	 * A host is a host, optionally with a port.
	 */
	public function test_a_host_is_a_host(): void {
		$this->assertTrue( Validate::host( 's3.eu-west-2.amazonaws.com' ) );
		$this->assertTrue( Validate::host( 'localhost:9000' ) );
		$this->assertFalse( Validate::host( 'https://s3.example.com' ) );
		$this->assertFalse( Validate::host( 's3.example.com/bucket' ) );
		$this->assertFalse( Validate::host( 's3_example.com' ) );
	}

	/**
	 * A credential has to be there.
	 */
	public function test_a_credential_has_to_be_there(): void {
		$this->assertTrue( Validate::credential( 'AKIAIOSFODNN7EXAMPLE' ) );
		$this->assertFalse( Validate::credential( '' ) );
		$this->assertFalse( Validate::credential( '   ' ) );
	}

	/**
	 * A header name is RFC 7230's token set.
	 */
	public function test_a_header_name_is_a_token(): void {
		$this->assertTrue( Validate::header_name( 'x-amz-meta-note' ) );
		$this->assertTrue( Validate::header_name( 'content-type' ) );
		$this->assertFalse( Validate::header_name( 'x-amz:meta' ) );
		$this->assertFalse( Validate::header_name( 'x amz meta' ) );
		$this->assertFalse( Validate::header_name( '' ) );
	}

	/**
	 * A header value carries no line break.
	 */
	public function test_a_header_value_carries_no_line_break(): void {
		$this->assertTrue( Validate::header_value( 'text/plain; charset=utf-8' ) );
		$this->assertFalse( Validate::header_value( "one\r\nHost: evil.test" ) );
		$this->assertFalse( Validate::header_value( "one\nHost: evil.test" ) );
		$this->assertFalse( Validate::header_value( "one\0two" ) );
	}

	/**
	 * Signer asks no validation question of its own.
	 *
	 * The point of the class: a regular expression in the middle of a
	 * constructor is a question nobody named, and the next one gets written
	 * beside it rather than here.
	 */
	public function test_the_signer_asks_no_questions_of_its_own(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Signer.php' );

		$this->assertSame(
			0,
			preg_match_all( '/preg_match\s*\(/', $source ),
			'Signer is checking something itself; that question belongs to Validate.'
		);
	}
}
