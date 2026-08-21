<?php
declare( strict_types=1 );

namespace ArrayPress\S3Signer\Tests;

use ArrayPress\S3Signer\AddressingStyle;
use ArrayPress\S3Signer\Method;
use ArrayPress\S3Signer\Provider;
use ArrayPress\S3Signer\Transport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The providers added for parity with arraypress/wp-s3-browser, plus the
 * WordPress transport.
 */
final class WordPressProvidersTest extends TestCase {

	private const ACCESS_KEY = 'AKIAIOSFODNN7EXAMPLE';
	private const SECRET_KEY = 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY';

	public function test_vultr_endpoint_and_addressing(): void {
		$this->assertSame( 'ewr1.vultrobjects.com', Provider::Vultr->endpoint() );
		$this->assertSame( 'ams1.vultrobjects.com', Provider::Vultr->endpoint( 'ams1' ) );
		$this->assertSame( AddressingStyle::Path, Provider::Vultr->addressing() );
	}

	public function test_mega_s4_endpoint_and_addressing(): void {
		$this->assertSame( 's3.eu-central-1.s4.mega.io', Provider::MegaS4->endpoint() );
		$this->assertSame( 's3.ca-central-1.s4.mega.io', Provider::MegaS4->endpoint( 'ca-central-1' ) );
		$this->assertSame( AddressingStyle::Path, Provider::MegaS4->addressing() );
	}

	/**
	 * Path-style providers keep the bucket in the path and out of the host;
	 * signing the wrong one is an immediate SignatureDoesNotMatch.
	 */
	#[DataProvider( 'path_style_providers' )]
	public function test_path_style_providers_sign_the_bucket_into_the_path( Provider $provider ): void {
		$signer = $provider->signer(
			self::ACCESS_KEY,
			self::SECRET_KEY,
			account_id: $provider->needs_account_id() ? 'acct123' : '',
			timestamp: 1700000000
		);
		$url    = $signer->presign( Method::GET, 'my-bucket', 'file.zip' );

		$this->assertStringContainsString( '/my-bucket/file.zip', $url );
		$this->assertStringNotContainsString( '://my-bucket.', $url );
	}

	public static function path_style_providers(): array {
		return [
			'vultr' => [ Provider::Vultr ],
			'mega'  => [ Provider::MegaS4 ],
			'r2'    => [ Provider::R2 ],
		];
	}

	public function test_every_case_has_a_label_and_a_default_region(): void {
		foreach ( Provider::cases() as $case ) {
			$this->assertNotSame( '', $case->label(), $case->value . ' needs a label' );
			$this->assertNotSame( '', $case->default_region(), $case->value . ' needs a default region' );
		}
	}

	/**
	 * Every provider that builds its own endpoint must actually do so — a
	 * missing match arm would otherwise only surface at runtime.
	 */
	public function test_every_case_resolves_an_endpoint(): void {
		foreach ( Provider::cases() as $case ) {
			$endpoint = $case->endpoint(
				'',
				$case->needs_account_id() ? 'acct123' : '',
				$case->needs_endpoint() ? 'minio.example:9000' : ''
			);

			$this->assertNotSame( '', $endpoint, $case->value );
			$this->assertStringNotContainsString( '://', $endpoint, $case->value . ' must be a bare host' );
		}
	}

	public function test_transport_success_range(): void {
		$this->assertTrue( Transport::is_success( 200 ) );
		$this->assertTrue( Transport::is_success( 204 ) );
		$this->assertFalse( Transport::is_success( 301 ) );
		$this->assertFalse( Transport::is_success( 403 ) );
		$this->assertFalse( Transport::is_success( 500 ) );
	}
}
