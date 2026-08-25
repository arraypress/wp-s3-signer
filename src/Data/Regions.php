<?php
/**
 * Region tables, one per provider.
 *
 * @package   ArrayPress\S3Signer
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     2.1.0
 */

declare( strict_types=1 );

namespace ArrayPress\S3Signer\Data;

use ArrayPress\S3Signer\Enums\Provider;

/**
 * The regions each provider offers, as `code => label`.
 *
 * Split out of the enum because it is data and the enum is behaviour. Two
 * hundred lines of tables in front of the six methods that matter made the
 * methods hard to find, and a table is easier to check when it is the only
 * thing in the file.
 *
 * @since 2.1.0
 */
final class Regions {

	/**
	 * The regions a provider offers.
	 *
	 * Empty means there is nothing to choose: R2 signs everything as `auto`, a
	 * self-hosted endpoint has whatever region its operator configured, and a
	 * few providers run a single global endpoint.
	 *
	 * This is a convenience for building a form rather than a gate on signing.
	 * Providers add regions without asking, and a region existing says nothing
	 * about whether a given account may use it, so Provider::endpoint()
	 * accepts any region rather than refusing one merely newer than this.
	 *
	 * @since 2.1.0
	 *
	 * @param Provider $provider The provider.
	 *
	 * @return array<string, string>
	 */
	public static function for( Provider $provider ): array {
		return match ( $provider ) {
			Provider::Aws          => self::aws(),
			Provider::Backblaze    => self::backblaze(),
			Provider::DigitalOcean => self::digitalocean(),
			Provider::Linode       => self::linode(),
			Provider::Wasabi       => self::wasabi(),
			Provider::Scaleway     => self::scaleway(),
			Provider::Vultr        => self::vultr(),
			Provider::MegaS4       => self::mega(),
			Provider::Contabo      => self::contabo(),
			Provider::Hetzner      => self::hetzner(),
			Provider::OVHcloud     => self::ovhcloud(),
			Provider::Exoscale     => self::exoscale(),
			Provider::IBMCloud     => self::ibm(),
			Provider::AlibabaOSS   => self::alibaba(),
			Provider::TencentCOS   => self::tencent(),
			default                => [],
		};
	}

	/**
	 * Amazon S3.
	 *
	 * @return array<string, string>
	 */
	private static function aws(): array {
		return [
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
		];
	}

	/**
	 * Backblaze B2.
	 *
	 * B2 assigns a region per account rather than letting one be picked, so
	 * this is for showing the operator which one they are on. 003 and 005 do
	 * not exist, whatever older lists claim.
	 *
	 * @return array<string, string>
	 */
	private static function backblaze(): array {
		return [
			'us-west-000'    => 'US West (000)',
			'us-west-001'    => 'US West (001)',
			'us-west-002'    => 'US West (002)',
			'us-west-004'    => 'US West (004)',
			'eu-central-003' => 'EU Central (003)',
		];
	}

	/**
	 * DigitalOcean Spaces.
	 *
	 * @return array<string, string>
	 */
	private static function digitalocean(): array {
		return [
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
		];
	}

	/**
	 * Akamai (Linode) Object Storage.
	 *
	 * @return array<string, string>
	 */
	private static function linode(): array {
		return [
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
		];
	}

	/**
	 * Wasabi.
	 *
	 * @return array<string, string>
	 */
	private static function wasabi(): array {
		return [
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
		];
	}

	/**
	 * Scaleway Object Storage.
	 *
	 * @return array<string, string>
	 */
	private static function scaleway(): array {
		return [
			'fr-par' => 'Paris',
			'nl-ams' => 'Amsterdam',
			'pl-waw' => 'Warsaw',
		];
	}

	/**
	 * Vultr Object Storage.
	 *
	 * @return array<string, string>
	 */
	private static function vultr(): array {
		return [
			'ewr1' => 'New Jersey',
			'sjc1' => 'Silicon Valley',
			'ams1' => 'Amsterdam',
			'lhr1' => 'London',
			'blr1' => 'Bangalore',
			'del1' => 'New Delhi',
			'sgp1' => 'Singapore',
			'nrt1' => 'Tokyo',
			'syd1' => 'Sydney',
		];
	}

	/**
	 * MEGA S4.
	 *
	 * @return array<string, string>
	 */
	private static function mega(): array {
		return [
			'eu-central-1' => 'Amsterdam',
			'eu-central-2' => 'Bettembourg',
			'ca-central-1' => 'Montreal',
			'ca-west-1'    => 'Vancouver',
		];
	}

	/**
	 * Contabo Object Storage.
	 *
	 * @return array<string, string>
	 */
	private static function contabo(): array {
		return [
			'eu2'  => 'European Union',
			'usc1' => 'US Central',
			'sin1' => 'Singapore',
		];
	}

	/**
	 * Hetzner Object Storage.
	 *
	 * @return array<string, string>
	 */
	private static function hetzner(): array {
		return [
			'fsn1' => 'Falkenstein',
			'nbg1' => 'Nuremberg',
			'hel1' => 'Helsinki',
		];
	}

	/**
	 * OVHcloud Object Storage.
	 *
	 * @return array<string, string>
	 */
	private static function ovhcloud(): array {
		return [
			'gra'         => 'Gravelines',
			'rbx'         => 'Roubaix',
			'sbg'         => 'Strasbourg',
			'bhs'         => 'Beauharnois',
			'de'          => 'Frankfurt',
			'uk'          => 'London',
			'waw'         => 'Warsaw',
			'eu-west-par' => 'Paris',
		];
	}

	/**
	 * Exoscale SOS.
	 *
	 * @return array<string, string>
	 */
	private static function exoscale(): array {
		return [
			'ch-gva-2' => 'Geneva',
			'ch-dk-2'  => 'Zurich',
			'de-fra-1' => 'Frankfurt',
			'de-muc-1' => 'Munich',
			'at-vie-1' => 'Vienna 1',
			'at-vie-2' => 'Vienna 2',
			'bg-sof-1' => 'Sofia',
		];
	}

	/**
	 * IBM Cloud Object Storage.
	 *
	 * @return array<string, string>
	 */
	private static function ibm(): array {
		return [
			'us-south' => 'Dallas',
			'us-east'  => 'Washington, DC',
			'ca-tor'   => 'Toronto',
			'br-sao'   => 'Sao Paulo',
			'eu-gb'    => 'London',
			'eu-de'    => 'Frankfurt',
			'jp-tok'   => 'Tokyo',
			'jp-osa'   => 'Osaka',
			'au-syd'   => 'Sydney',
		];
	}

	/**
	 * Alibaba Cloud OSS.
	 *
	 * @return array<string, string>
	 */
	private static function alibaba(): array {
		return [
			'cn-hangzhou'    => 'Hangzhou',
			'cn-shanghai'    => 'Shanghai',
			'cn-qingdao'     => 'Qingdao',
			'cn-beijing'     => 'Beijing',
			'cn-zhangjiakou' => 'Zhangjiakou',
			'cn-shenzhen'    => 'Shenzhen',
			'cn-hongkong'    => 'Hong Kong',
			'us-west-1'      => 'Silicon Valley',
			'us-east-1'      => 'Virginia',
			'ap-southeast-1' => 'Singapore',
			'ap-southeast-3' => 'Kuala Lumpur',
			'ap-southeast-5' => 'Jakarta',
			'ap-northeast-1' => 'Tokyo',
			'ap-northeast-2' => 'Seoul',
			'eu-central-1'   => 'Frankfurt',
			'eu-west-1'      => 'London',
			'me-east-1'      => 'Dubai',
		];
	}

	/**
	 * Tencent Cloud COS.
	 *
	 * @return array<string, string>
	 */
	private static function tencent(): array {
		return [
			'ap-beijing'       => 'Beijing',
			'ap-shanghai'      => 'Shanghai',
			'ap-guangzhou'     => 'Guangzhou',
			'ap-chengdu'       => 'Chengdu',
			'ap-hongkong'      => 'Hong Kong',
			'ap-singapore'     => 'Singapore',
			'ap-tokyo'         => 'Tokyo',
			'ap-seoul'         => 'Seoul',
			'na-siliconvalley' => 'Silicon Valley',
			'na-ashburn'       => 'Virginia',
			'eu-frankfurt'     => 'Frankfurt',
		];
	}
}
