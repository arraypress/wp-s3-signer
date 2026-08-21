<?php
declare( strict_types=1 );

namespace ArrayPress\S3Signer\Tests;

use ArrayPress\S3Signer\BucketName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Bucket name rules.
 */
final class BucketNameTest extends TestCase {

	#[DataProvider( 'valid_names' )]
	public function test_accepts_legal_names( string $bucket ): void {
		$this->assertTrue( BucketName::is_valid( $bucket ), $bucket );
	}

	public static function valid_names(): array {
		return [
			'simple'       => [ 'downloads' ],
			'hyphenated'   => [ 'my-store-downloads' ],
			'dotted'       => [ 'my.store.downloads' ],
			'digits'       => [ 'bucket123' ],
			'minimum'      => [ 'abc' ],
			'maximum'      => [ str_repeat( 'a', 63 ) ],
		];
	}

	#[DataProvider( 'invalid_names' )]
	public function test_rejects_illegal_names( string $bucket, string $why ): void {
		$this->assertFalse( BucketName::is_valid( $bucket ), $why );
	}

	public static function invalid_names(): array {
		return [
			'too short'          => [ 'ab', 'under three characters' ],
			'too long'           => [ str_repeat( 'a', 64 ), 'over sixty-three characters' ],
			'uppercase'          => [ 'My-Bucket', 'S3 rejects uppercase outright' ],
			'leading hyphen'     => [ '-bucket', 'must start with a letter or digit' ],
			'trailing hyphen'    => [ 'bucket-', 'must end with a letter or digit' ],
			'leading dot'        => [ '.bucket', 'must start with a letter or digit' ],
			'adjacent dots'      => [ 'my..bucket', 'adjacent dots are rejected by S3' ],
			'ip address'         => [ '192.168.1.1', 'ambiguous with an endpoint' ],
			'underscore'         => [ 'my_bucket', 'underscores are not permitted' ],
			'space'              => [ 'my bucket', 'spaces are not permitted' ],
			'slash'              => [ 'my/bucket', 'path separators are not permitted' ],
			'empty'              => [ '', 'nothing to validate' ],
		];
	}

	/**
	 * A name can be legal and still unusable virtual-hosted. Conflating the two
	 * questions is what let two validators in this stack disagree.
	 */
	public function test_dotted_names_are_valid_but_not_dns_compatible(): void {
		$this->assertTrue( BucketName::is_valid( 'my.bucket' ) );
		$this->assertFalse( BucketName::is_dns_compatible( 'my.bucket' ) );
	}

	public function test_dns_compatibility_implies_validity(): void {
		foreach ( [ 'downloads', 'my-store', 'abc' ] as $bucket ) {
			$this->assertTrue( BucketName::is_dns_compatible( $bucket ), $bucket );
			$this->assertTrue( BucketName::is_valid( $bucket ), $bucket );
		}
	}

	public function test_addressing_style_delegates_to_the_same_rule(): void {
		foreach ( [ 'downloads', 'my.bucket', 'My-Bucket', 'ab' ] as $bucket ) {
			$this->assertSame(
				BucketName::is_dns_compatible( $bucket ),
				\ArrayPress\S3Signer\AddressingStyle::is_dns_compatible( $bucket ),
				$bucket . ' must not have two rules'
			);
		}
	}
}
