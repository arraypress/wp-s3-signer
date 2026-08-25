<?php
declare( strict_types=1 );

namespace ArrayPress\S3Signer\Tests;

use ArrayPress\S3Signer\Enums\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The region tables.
 *
 * These exist so an application can offer a dropdown instead of asking an
 * operator to type a hostname. That only helps if every entry is real: a
 * retired region in the list produces an endpoint that does not resolve, and
 * the failure surfaces as a signing error rather than as "that region is
 * gone".
 */
final class ProviderRegionsTest extends TestCase {

	public static function providers_with_regions(): array {
		$cases = [];

		foreach ( Provider::cases() as $provider ) {
			if ( $provider->has_regions() ) {
				$cases[ $provider->value ] = [ $provider ];
			}
		}

		return $cases;
	}

	public static function every_region(): array {
		$cases = [];

		foreach ( Provider::cases() as $provider ) {
			foreach ( $provider->regions() as $code => $label ) {
				$cases[ "{$provider->value}:{$code}" ] = [ $provider, $code, $label ];
			}
		}

		return $cases;
	}

	#[DataProvider( 'every_region' )]
	public function test_region_builds_an_endpoint( Provider $provider, string $code, string $label ): void {
		$endpoint = $provider->endpoint( $code );

		$this->assertNotSame( '', $endpoint );
		$this->assertStringNotContainsString( '{', $endpoint, 'Unsubstituted placeholder' );
		$this->assertStringNotContainsString( '//', $endpoint, 'Endpoint must be a bare host' );
		$this->assertNotSame( '', trim( $label ) );
	}

	#[DataProvider( 'providers_with_regions' )]
	public function test_default_region_is_one_of_the_offered_regions( Provider $provider ): void {
		$this->assertTrue(
			$provider->knows_region( $provider->default_region() ),
			$provider->label() . ' defaults to a region it does not list'
		);
	}

	#[DataProvider( 'providers_with_regions' )]
	public function test_each_region_yields_a_distinct_endpoint( Provider $provider ): void {
		$endpoints = [];

		foreach ( array_keys( $provider->regions() ) as $code ) {
			$endpoints[] = $provider->endpoint( $code );
		}

		$this->assertSameSize(
			$endpoints,
			array_unique( $endpoints ),
			$provider->label() . ' maps two regions onto one endpoint'
		);
	}

	/**
	 * R2 signs everything as `auto` and rejects a real region, so offering a
	 * choice would be offering a way to break the signature.
	 */
	public function test_providers_without_a_region_choice_offer_none(): void {
		foreach ( [ Provider::R2, Provider::MinIO, Provider::Other ] as $provider ) {
			$this->assertSame( [], $provider->regions() );
			$this->assertFalse( $provider->has_regions() );
		}
	}

	public function test_unknown_region_is_reported_as_unknown(): void {
		$this->assertFalse( Provider::Aws->knows_region( 'zz-fake-9' ) );
		$this->assertTrue( Provider::Aws->knows_region( 'eu-central-2' ) );
	}

	/**
	 * A newer region than this list knows must still work. The table is a
	 * convenience for a form, not a gate on signing.
	 */
	public function test_an_unlisted_region_still_builds_an_endpoint(): void {
		$this->assertSame( 's3.zz-new-1.amazonaws.com', Provider::Aws->endpoint( 'zz-new-1' ) );
	}

	/**
	 * us-east-1 predates the regional naming and keeps the bare host.
	 */
	public function test_aws_us_east_1_uses_the_legacy_host(): void {
		$this->assertSame( 's3.amazonaws.com', Provider::Aws->endpoint( 'us-east-1' ) );
		$this->assertSame( 's3.eu-west-1.amazonaws.com', Provider::Aws->endpoint( 'eu-west-1' ) );
	}

	/**
	 * The tables are data, so they get the checks data needs.
	 *
	 * A duplicated code silently loses a region -- the later entry overwrites
	 * the earlier one and the dropdown is simply one shorter than it should
	 * be. A duplicated label gives two options a reader cannot tell apart.
	 *
	 * @param Provider $provider The provider.
	 */
	#[DataProvider( 'providers_with_regions' )]
	public function test_a_region_table_is_well_formed( Provider $provider ): void {
		$regions = $provider->regions();

		foreach ( $regions as $code => $label ) {
			$this->assertIsString( $code );
			$this->assertNotSame( '', trim( (string) $label ), sprintf( '%s: %s has no label.', $provider->value, $code ) );

			// Codes go into a hostname, so they are limited to what a DNS
			// label allows.
			$this->assertMatchesRegularExpression(
				'/^[a-z0-9][a-z0-9-]*$/',
				(string) $code,
				sprintf( '%s: "%s" cannot appear in a hostname.', $provider->value, $code )
			);
		}

		$this->assertSame(
			array(),
			array_diff_assoc( $regions, array_unique( $regions ) ),
			sprintf( '%s lists two regions under the same label.', $provider->value )
		);
	}

	/**
	 * Every provider that needs no explicit endpoint can build one.
	 *
	 * The check that catches a case added to the enum and nowhere else: the
	 * match in endpoint() would throw, and only for that one provider.
	 */
	public function test_every_provider_can_build_an_endpoint(): void {
		foreach ( Provider::cases() as $provider ) {
			if ( $provider->needs_endpoint() ) {
				continue;
			}

			$host = $provider->endpoint( '', account_id: 'abc123' );

			$this->assertNotSame( '', $host, sprintf( '%s built an empty host.', $provider->value ) );
			$this->assertStringNotContainsString( ' ', $host );
			$this->assertStringNotContainsString( '//', $host );
		}
	}

	public function test_options_covers_every_case(): void {
		$this->assertCount( count( Provider::cases() ), Provider::options() );
	}
}
