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

## Layout

```
src/
  Signer.php              signing, and nothing else
  Enums/
    Provider.php          every provider: endpoint, region, addressing, labels
    AddressingStyle.php   where the bucket goes
    Method.php            HTTP verbs
  Models/
    SignedRequest.php     what sign() hands back
  Support/
    Validate.php          every "is this allowed" question
    Headers.php           the canonical form SigV4 signs
    Endpoint.php          host, canonical URI, canonical query
    Filename.php          basename, extension, byte-safe clamping
    ContentDisposition.php  what a header can carry, and the ASCII fallback
    Transport.php         an optional wp_remote_request() bridge
```

Two lines to draw if you are adding something.

`Validate` holds *questions* — "is this a legal bucket name", "is this a
region that can go in a credential scope". They used to be regular
expressions in the middle of a constructor with a comment above each one
explaining what they were for; the comment is the answer to "what is this
checking", and a method name is a better one. A test asserts `Signer` calls
`preg_match()` nowhere at all.

`Filename` holds what is true of a filename whatever it is for.
`ContentDisposition` holds what is true of a *header*. Clamping a name to two
hundred bytes without losing its extension is the first; knowing that a
bidirectional override in a filename is a display-spoofing attack is the
second.


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
