# WordPress S3 Signer

AWS Signature Version 4 signing for S3-compatible object storage, for WordPress
plugins. Presigned URLs, signed request headers, and a thin WordPress HTTP API
transport. No AWS SDK, no HTTP client to configure.

Verified against Cloudflare R2, AWS S3, Backblaze B2 and DigitalOcean Spaces.

## Requirements

* PHP 8.2 or later

## Installation

```bash
composer require arraypress/wp-s3-signer
```

## Why a signing library

SigV4 is unforgiving in a specific way: the signature covers a *canonical
request* that must describe, byte for byte, the request that actually goes on
the wire. Get any part of that wrong — the addressing style, the URI encoding
of the object key, the query-string encoding, the `Host` header — and the
provider returns `SignatureDoesNotMatch` with nothing to say which part was
wrong.

Those rules live here, in one place, with tests, rather than being
reimplemented per plugin.

## Usage

### Presigned download link

```php
use ArrayPress\S3Signer\Provider;

$signer = Provider::R2->signer( $access_key, $secret_key, account_id: $account_id );

// 60-second link, saved by the browser as "Album Master.wav"
$url = $signer->presign_get( 'my-bucket', 'masters/a1.wav', 60, 'Album Master.wav' );
```

`response-content-disposition` is honoured by S3 and R2 on a presigned GET, so
you can store opaque keys and still deliver a real filename.

### Direct browser upload

```php
$url = $signer->presign_put( 'my-bucket', 'uploads/track.wav' );
```

Bytes go straight to storage, so upload size is bounded by the provider rather
than by `post_max_size`.

### A request your server makes

```php
use ArrayPress\S3Signer\Transport;

$request  = $signer->sign_delete_object( 'my-bucket', 'masters/a1.wav' );
$response = Transport::send( $request );

if ( ! is_wp_error( $response ) && Transport::is_success( $response['status'] ) ) {
	// Deleted.
}
```

`Transport` is the only part of this package that performs I/O. The signer
itself never does, which is what keeps it testable.

## Providers

`Provider` knows each provider's endpoint, default region and addressing style:

| Case | Provider | Addressing |
|------|----------|------------|
| `Aws` | Amazon S3 | virtual-hosted |
| `R2` | Cloudflare R2 | path |
| `Backblaze` | Backblaze B2 | virtual-hosted |
| `DigitalOcean` | DigitalOcean Spaces | virtual-hosted |
| `Linode` | Linode Object Storage | virtual-hosted |
| `Wasabi` | Wasabi | virtual-hosted |
| `Scaleway` | Scaleway Object Storage | virtual-hosted |
| `Vultr` | Vultr Object Storage | path |
| `MegaS4` | MEGA S4 | path |
| `MinIO` | MinIO (self-hosted) | path |
| `Other` | Any S3-compatible endpoint | path |

Addressing style is not cosmetic. Under virtual-hosted addressing the bucket
belongs in the `Host` header and must *not* be repeated in the path; under
path-style the reverse. Signing one and requesting the other fails.

## Relationship to other packages

* **`arraypress/wp-s3-browser`** builds on this: media library integration,
  REST endpoints, listing tables, caching.
* **`sugarcommerce/s3-signer`** is the upstream this was forked from. The two
  are developed independently.

## Testing

```bash
composer test
```

## License

GPL-2.0-or-later
