<?php
/**
 * Header canonicalisation tests.
 *
 * @package ArrayPress\S3Signer
 */

declare( strict_types=1 );

namespace ArrayPress\S3Signer\Tests;

use ArrayPress\S3Signer\Support\Headers;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * SigV4 signs a canonical form of the headers, not the headers as written.
 *
 * Every rule below is one the provider applies on its side too, so getting
 * one wrong produces a `SignatureDoesNotMatch` that names no header and gives
 * no reason. That is why the shaping is one class with tests rather than a
 * loop in the middle of building a request.
 */
final class HeadersTest extends TestCase {

	/**
	 * Names are lower-cased and sorted.
	 */
	public function test_names_are_lower_cased_and_sorted(): void {
		$this->assertSame(
			[ 'content-type', 'x-amz-date', 'x-amz-meta-note' ],
			array_keys( Headers::canonicalize( [ 'X-Amz-Meta-Note' => 'a', 'Content-Type' => 'b', 'x-amz-date' => 'c' ] ) )
		);
	}

	/**
	 * Values have their whitespace collapsed and trimmed.
	 */
	public function test_values_are_collapsed(): void {
		$this->assertSame(
			[ 'content-type' => 'text/plain; charset=utf-8' ],
			Headers::canonicalize( [ 'Content-Type' => "  text/plain;   charset=utf-8\t" ] )
		);
	}

	/**
	 * A name outside RFC 7230's token set is refused.
	 *
	 * @dataProvider badNameProvider
	 *
	 * @param string $name The name to try.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'badNameProvider' )]
	public function test_a_bad_name_is_refused( string $name ): void {
		$this->expectException( InvalidArgumentException::class );

		Headers::canonicalize( [ $name => 'value' ] );
	}

	/**
	 * One row per way a name can be wrong.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function badNameProvider(): array {
		return [
			'a colon'    => [ 'x-amz:meta' ],
			'a space'    => [ 'x amz meta' ],
			'a newline'  => [ "x-amz\nHost" ],
			'empty'      => [ '' ],
			'a bracket'  => [ 'x-amz(meta)' ],
		];
	}

	/**
	 * A value carrying a line break is refused rather than repaired.
	 *
	 * Stripping it would sign one thing and send another, and a signature
	 * over a value the server never receives fails in a way nobody can read.
	 *
	 * @dataProvider badValueProvider
	 *
	 * @param string $value The value to try.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'badValueProvider' )]
	public function test_a_value_with_a_line_break_is_refused( string $value ): void {
		$this->expectException( InvalidArgumentException::class );

		Headers::canonicalize( [ 'x-amz-meta-note' => $value ] );
	}

	/**
	 * The three characters that split a request.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function badValueProvider(): array {
		return [
			'a carriage return' => [ "one\rHost: evil.test" ],
			'a newline'         => [ "one\nHost: evil.test" ],
			'a null byte'       => [ "one\0two" ],
		];
	}

	/**
	 * The canonical block is one line per header, with a trailing newline.
	 */
	public function test_the_block_is_one_line_per_header(): void {
		$headers = Headers::canonicalize( [ 'Host' => 'example.test', 'X-Amz-Date' => '20260825T000000Z' ] );

		$this->assertSame( "host:example.test\nx-amz-date:20260825T000000Z\n", Headers::block( $headers ) );
	}

	/**
	 * The signed-headers list is the names, in the same order.
	 */
	public function test_the_signed_list_matches_the_block(): void {
		$headers = Headers::canonicalize( [ 'Host' => 'example.test', 'Content-Type' => 'text/plain' ] );

		$this->assertSame( 'content-type;host', Headers::names( $headers ) );
	}

	/**
	 * Nothing at all is nothing at all.
	 */
	public function test_no_headers_is_no_block(): void {
		$this->assertSame( [], Headers::canonicalize( [] ) );
		$this->assertSame( '', Headers::block( [] ) );
		$this->assertSame( '', Headers::names( [] ) );
	}
}
