<?php
/**
 * S3-compatible storage providers.
 *
 * @package   ArrayPress\S3Signer
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\S3Signer;

/**
 * Enum Provider
 *
 * The providers people actually use, and what each needs to build an
 * endpoint. This exists so an application can offer a dropdown rather
 * than asking an operator to type a hostname they will get wrong.
 *
 * Three things vary and all three break silently when guessed:
 *
 *   - **Endpoint shape.** Some interpolate a region, R2 interpolates an
 *     account id, MinIO is whatever the operator hosts.
 *   - **Addressing style.** Virtual-hosted puts the bucket in the
 *     hostname, path style puts it in the path. Get it wrong and every
 *     request 404s against a bucket that plainly exists.
 *   - **Region.** R2 signs everything as `auto`; AWS rejects a signature
 *     computed for the wrong region.
 *
 * @since 1.0.0
 */
enum Provider: string {

	case Aws          = 'aws';
	case R2           = 'r2';
	case Backblaze    = 'backblaze';
	case DigitalOcean = 'digitalocean';
	case Linode       = 'linode';
	case Wasabi       = 'wasabi';
	case Scaleway     = 'scaleway';
	case Vultr        = 'vultr';
	case MegaS4       = 'mega';
	case MinIO        = 'minio';
	case Other        = 'other';

	/**
	 * A human label, for a settings dropdown.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function label(): string {
		return match ( $this ) {
			self::Aws          => 'Amazon S3',
			self::R2           => 'Cloudflare R2',
			self::Backblaze    => 'Backblaze B2',
			self::DigitalOcean => 'DigitalOcean Spaces',
			self::Linode       => 'Linode Object Storage',
			self::Wasabi       => 'Wasabi',
			self::Scaleway     => 'Scaleway Object Storage',
			self::Vultr        => 'Vultr Object Storage',
			self::MegaS4       => 'MEGA S4',
			self::MinIO        => 'MinIO (self-hosted)',
			self::Other        => 'Other S3-compatible',
		};
	}

	/**
	 * Whether this provider identifies the endpoint by account id rather
	 * than region. Only R2 does; the setting is meaningless elsewhere and
	 * a form should hide it.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function needs_account_id(): bool {
		return self::R2 === $this;
	}

	/**
	 * Whether the operator must supply the endpoint themselves.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function needs_endpoint(): bool {
		return self::MinIO === $this || self::Other === $this;
	}

	/**
	 * The region to sign with when the operator gives none.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function default_region(): string {
		return match ( $this ) {
			// R2 signs everything as `auto`; a real region is rejected.
			self::R2           => 'auto',
			self::Aws          => 'us-east-1',
			self::DigitalOcean => 'nyc3',
			self::Linode       => 'us-east-1',
			// B2 assigns a region per account, so any default is a guess.
			// us-west-004 is what current accounts get; regions() exists so a
			// form can make the operator choose rather than rely on this.
			self::Backblaze    => 'us-west-004',
			self::Wasabi       => 'us-east-1',
			self::Scaleway     => 'fr-par',
			self::Vultr        => 'ewr1',
			self::MegaS4       => 'eu-central-1',
			self::MinIO, self::Other => 'us-east-1',
		};
	}

	/**
	 * The regions this provider offers, as `code => label`.
	 *
	 * For a settings dropdown. Empty means there is nothing to choose: R2
	 * signs everything as `auto`, and a self-hosted endpoint has whatever
	 * region its operator configured.
	 *
	 * Every code here was checked by resolving the endpoint it produces, so
	 * the list holds no region that has been retired. It is still a
	 * convenience for building a form rather than a gate on signing --
	 * providers add regions without asking, and a region resolving says
	 * nothing about whether a given account may use it -- so endpoint()
	 * accepts any region rather than refusing one merely newer than this.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, string>
	 */
	public function regions(): array {
		return match ( $this ) {
			self::Aws => [
				'us-east-1'      => 'US East (N. Virginia)',
				'us-east-2'      => 'US East (Ohio)',
				'us-west-1'      => 'US West (N. California)',
				'us-west-2'      => 'US West (Oregon)',
				'ca-central-1'   => 'Canada (Central)',
				'ca-west-1'      => 'Canada West (Calgary)',
				'mx-central-1'   => 'Mexico (Central)',
				'sa-east-1'      => 'South America (Sao Paulo)',
				'eu-west-1'      => 'Europe (Ireland)',
				'eu-west-2'      => 'Europe (London)',
				'eu-west-3'      => 'Europe (Paris)',
				'eu-central-1'   => 'Europe (Frankfurt)',
				'eu-central-2'   => 'Europe (Zurich)',
				'eu-north-1'     => 'Europe (Stockholm)',
				'eu-south-1'     => 'Europe (Milan)',
				'eu-south-2'     => 'Europe (Spain)',
				'af-south-1'     => 'Africa (Cape Town)',
				'il-central-1'   => 'Israel (Tel Aviv)',
				'me-south-1'     => 'Middle East (Bahrain)',
				'me-central-1'   => 'Middle East (UAE)',
				'ap-east-1'      => 'Asia Pacific (Hong Kong)',
				'ap-east-2'      => 'Asia Pacific (Taipei)',
				'ap-south-1'     => 'Asia Pacific (Mumbai)',
				'ap-south-2'     => 'Asia Pacific (Hyderabad)',
				'ap-northeast-1' => 'Asia Pacific (Tokyo)',
				'ap-northeast-2' => 'Asia Pacific (Seoul)',
				'ap-northeast-3' => 'Asia Pacific (Osaka)',
				'ap-southeast-1' => 'Asia Pacific (Singapore)',
				'ap-southeast-2' => 'Asia Pacific (Sydney)',
				'ap-southeast-3' => 'Asia Pacific (Jakarta)',
				'ap-southeast-4' => 'Asia Pacific (Melbourne)',
				'ap-southeast-5' => 'Asia Pacific (Malaysia)',
				'ap-southeast-7' => 'Asia Pacific (Thailand)',
			],
			// B2 assigns a region per account rather than letting one be
			// picked, so this is for showing the operator which one they are
			// on. 003 and 005 do not exist, whatever older lists claim.
			self::Backblaze => [
				'us-west-000'    => 'US West (000)',
				'us-west-001'    => 'US West (001)',
				'us-west-002'    => 'US West (002)',
				'us-west-004'    => 'US West (004)',
				'eu-central-003' => 'EU Central (003)',
			],
			self::DigitalOcean => [
				'nyc3' => 'New York City',
				'sfo2' => 'San Francisco 2',
				'sfo3' => 'San Francisco 3',
				'tor1' => 'Toronto',
				'atl1' => 'Atlanta',
				'ams3' => 'Amsterdam',
				'fra1' => 'Frankfurt',
				'lon1' => 'London',
				'blr1' => 'Bangalore',
				'sgp1' => 'Singapore',
				'syd1' => 'Sydney',
			],
			self::Linode => [
				'us-east-1'      => 'Newark, NJ',
				'us-iad-1'       => 'Washington, DC',
				'us-ord-1'       => 'Chicago, IL',
				'us-lax-1'       => 'Los Angeles, CA',
				'us-mia-1'       => 'Miami, FL',
				'us-sea-1'       => 'Seattle, WA',
				'us-southeast-1' => 'Atlanta, GA',
				'br-gru-1'       => 'Sao Paulo',
				'gb-lon-1'       => 'London',
				'nl-ams-1'       => 'Amsterdam',
				'fr-par-1'       => 'Paris',
				'de-fra-1'       => 'Frankfurt',
				'es-mad-1'       => 'Madrid',
				'it-mil-1'       => 'Milan',
				'se-sto-1'       => 'Stockholm',
				'in-maa-1'       => 'Chennai',
				'id-cgk-1'       => 'Jakarta',
				'jp-osa-1'       => 'Osaka',
				'sg-sin-1'       => 'Singapore',
				'au-mel-1'       => 'Melbourne',
			],
			self::Wasabi => [
				'us-east-1'      => 'Virginia 1',
				'us-east-2'      => 'Virginia 2',
				'us-central-1'   => 'Plano, TX',
				'us-west-1'      => 'Oregon',
				'ca-central-1'   => 'Toronto',
				'eu-west-1'      => 'London',
				'eu-west-2'      => 'Paris',
				'eu-central-1'   => 'Amsterdam',
				'eu-central-2'   => 'Frankfurt',
				'ap-northeast-1' => 'Tokyo',
				'ap-northeast-2' => 'Osaka',
				'ap-southeast-1' => 'Singapore',
				'ap-southeast-2' => 'Sydney',
			],
			self::Scaleway => [
				'fr-par' => 'Paris',
				'nl-ams' => 'Amsterdam',
				'pl-waw' => 'Warsaw',
			],
			self::Vultr => [
				'ewr1' => 'New Jersey',
				'sjc1' => 'Silicon Valley',
				'ams1' => 'Amsterdam',
				'lhr1' => 'London',
				'blr1' => 'Bangalore',
				'del1' => 'New Delhi',
				'sgp1' => 'Singapore',
				'nrt1' => 'Tokyo',
				'syd1' => 'Sydney',
			],
			self::MegaS4 => [
				'eu-central-1' => 'Amsterdam',
				'eu-central-2' => 'Bettembourg',
				'ca-central-1' => 'Montreal',
				'ca-west-1'    => 'Vancouver',
			],
			// R2 signs as `auto`; MinIO and Other are whatever the operator runs.
			self::R2, self::MinIO, self::Other => [],
		};
	}

	/**
	 * Whether a form should offer a region choice for this provider.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public function has_regions(): bool {
		return [] !== $this->regions();
	}

	/**
	 * Whether a region is one this provider is known to offer.
	 *
	 * A false answer is not proof the region is wrong -- providers add
	 * regions faster than a list like this is updated -- so use it to warn,
	 * not to refuse.
	 *
	 * @since 1.1.0
	 *
	 * @param string $region Region code.
	 *
	 * @return bool
	 */
	public function knows_region( string $region ): bool {
		return isset( $this->regions()[ $region ] );
	}

	/**
	 * Where the bucket goes in the URL.
	 *
	 * @since 1.0.0
	 *
	 * @return AddressingStyle
	 */
	public function addressing(): AddressingStyle {
		return match ( $this ) {
			// A self-hosted MinIO is usually reached by IP or a bare
			// hostname, where a bucket in the hostname cannot resolve.
			self::R2, self::Vultr, self::MegaS4, self::MinIO, self::Other => AddressingStyle::Path,
			default                            => AddressingStyle::VirtualHosted,
		};
	}

	/**
	 * Build the endpoint host for this provider.
	 *
	 * @since 1.0.0
	 *
	 * @param string $region     Region name.
	 * @param string $account_id Account id — R2 only.
	 * @param string $endpoint   Explicit host, for MinIO and Other.
	 *
	 * @return string Host only, no scheme.
	 *
	 * @throws \InvalidArgumentException When a required part is missing.
	 */
	public function endpoint( string $region = '', string $account_id = '', string $endpoint = '' ): string {
		$region = '' !== trim( $region ) ? trim( $region ) : $this->default_region();

		if ( $this->needs_endpoint() ) {
			$host = preg_replace( '#^https?://#i', '', trim( $endpoint ) );
			$host = rtrim( (string) $host, '/' );

			if ( '' === $host ) {
				throw new \InvalidArgumentException( $this->label() . ' needs an endpoint host.' );
			}

			return $host;
		}

		if ( self::R2 === $this ) {
			if ( '' === trim( $account_id ) ) {
				throw new \InvalidArgumentException( 'Cloudflare R2 needs an account id.' );
			}

			return trim( $account_id ) . '.r2.cloudflarestorage.com';
		}

		return match ( $this ) {
			self::Aws          => 'us-east-1' === $region ? 's3.amazonaws.com' : 's3.' . $region . '.amazonaws.com',
			self::Backblaze    => 's3.' . $region . '.backblazeb2.com',
			self::DigitalOcean => $region . '.digitaloceanspaces.com',
			self::Linode       => $region . '.linodeobjects.com',
			self::Wasabi       => 's3.' . $region . '.wasabisys.com',
			self::Scaleway     => 's3.' . $region . '.scw.cloud',
			self::Vultr        => $region . '.vultrobjects.com',
			self::MegaS4       => 's3.' . $region . '.s4.mega.io',
			default            => throw new \InvalidArgumentException( 'Unhandled provider: ' . $this->value ),
		};
	}

	/**
	 * A configured signer for this provider.
	 *
	 * @since 1.0.0
	 *
	 * @param string $access_key Access key id.
	 * @param string $secret_key Secret access key.
	 * @param string $region     Region name.
	 * @param string $account_id Account id — R2 only.
	 * @param string $endpoint   Explicit host — MinIO and Other only.
	 *
	 * @return Signer
	 */
	public function signer(
		#[\SensitiveParameter] string $access_key,
		#[\SensitiveParameter] string $secret_key,
		string $region = '',
		string $account_id = '',
		string $endpoint = '',
		?int $timestamp = null
	): Signer {
		return new Signer(
			$access_key,
			$secret_key,
			$this->endpoint( $region, $account_id, $endpoint ),
			'' !== trim( $region ) ? trim( $region ) : $this->default_region(),
			$timestamp,
			$this->addressing()
		);
	}

	/**
	 * Every provider as `value => label`, for a settings dropdown.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public static function options(): array {
		$options = array();

		foreach ( self::cases() as $case ) {
			$options[ $case->value ] = $case->label();
		}

		return $options;
	}
}
