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

namespace ArrayPress\S3Signer\Enums;

use ArrayPress\S3Signer\Data\Regions;
use ArrayPress\S3Signer\Signer;

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
	case Storj        = 'storj';
	case Filebase     = 'filebase';
	case GoogleCloud  = 'gcs';
	case Contabo      = 'contabo';
	case Hetzner      = 'hetzner';
	case OVHcloud     = 'ovhcloud';
	case Exoscale     = 'exoscale';
	case IBMCloud     = 'ibm';
	case AlibabaOSS   = 'alibaba';
	case TencentCOS   = 'tencent';
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
			self::Storj        => 'Storj',
			self::Filebase     => 'Filebase',
			self::GoogleCloud  => 'Google Cloud Storage',
			self::Contabo      => 'Contabo Object Storage',
			self::Hetzner      => 'Hetzner Object Storage',
			self::OVHcloud     => 'OVHcloud Object Storage',
			self::Exoscale     => 'Exoscale SOS',
			self::IBMCloud     => 'IBM Cloud Object Storage',
			self::AlibabaOSS   => 'Alibaba Cloud OSS',
			self::TencentCOS   => 'Tencent Cloud COS',
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
			self::Contabo      => 'eu2',
			self::Hetzner      => 'fsn1',
			self::OVHcloud     => 'gra',
			self::Exoscale     => 'ch-gva-2',
			self::IBMCloud     => 'us-south',
			self::AlibabaOSS   => 'eu-central-1',
			self::TencentCOS   => 'eu-frankfurt',
			// A single global endpoint, so the region is only what the
			// signature is computed against.
			self::Storj, self::Filebase, self::GoogleCloud => 'us-east-1',
			self::MinIO, self::Other => 'us-east-1',
		};
	}

	/**
	 * The regions this provider offers, as `code => label`.
	 *
	 * The tables live in Data\Regions; this is the way in. Empty means there
	 * is nothing to choose -- R2 signs everything as `auto`, a self-hosted
	 * endpoint has whatever region its operator configured, and a few
	 * providers run a single global endpoint.
	 *
	 * Every code here was checked by making an HTTPS request to the endpoint
	 * it produces and confirming something answered. Resolving the name is not
	 * enough and was the mistake this note used to record: every one of these
	 * provider domains serves wildcard DNS, so an invented region resolves
	 * exactly like a real one. A live endpoint answers -- 200, 307, 400 or 403
	 * depending on the provider -- where an invented one gives no response at
	 * all.
	 *
	 * It is still a convenience for building a form rather than a gate on
	 * signing: providers add regions without asking, and a region existing
	 * says nothing about whether a given account may use it, so endpoint()
	 * accepts any region rather than refusing one merely newer than this.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, string>
	 */
	public function regions(): array {
		return Regions::for( $this );
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
			self::R2, self::Vultr, self::MegaS4, self::Contabo, self::MinIO, self::Other => AddressingStyle::Path,
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
			self::Storj        => 'gateway.storjshare.io',
			self::Filebase     => 's3.filebase.com',
			self::GoogleCloud  => 'storage.googleapis.com',
			self::Contabo      => $region . '.contabostorage.com',
			self::Hetzner      => $region . '.your-objectstorage.com',
			self::OVHcloud     => 's3.' . $region . '.io.cloud.ovh.net',
			self::Exoscale     => 'sos-' . $region . '.exo.io',
			self::IBMCloud     => 's3.' . $region . '.cloud-object-storage.appdomain.cloud',
			self::AlibabaOSS   => 'oss-' . $region . '.aliyuncs.com',
			self::TencentCOS   => 'cos.' . $region . '.myqcloud.com',
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
