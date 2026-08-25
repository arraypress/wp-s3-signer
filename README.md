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
  Data/
    Regions.php           the region tables, one per provider
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
    Filename.php          basename, extension, clamping, transliteration
    Url.php               reduce anything pasted to a bare host
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
hundred bytes without losing its extension is the first, and so is
transliterating "Симфония.wav" into something readable; knowing that a
bidirectional override is a display-spoofing attack, and that a quoted-string
cannot carry an unescaped backslash, is the second.

`ascii_fallback()` is the seam between them and reads as one: reduce the name
with `Filename::to_ascii()`, then apply what the header imposes.


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

| Case | Provider | Addressing | Regions |
|------|----------|------------|---------|
| `Aws` | Amazon S3 | virtual-hosted | 33 |
| `R2` | Cloudflare R2 | path | signs as `auto` |
| `Backblaze` | Backblaze B2 | virtual-hosted | 5 |
| `DigitalOcean` | DigitalOcean Spaces | virtual-hosted | 11 |
| `Linode` | Akamai (Linode) Object Storage | virtual-hosted | 20 |
| `Wasabi` | Wasabi | virtual-hosted | 13 |
| `Scaleway` | Scaleway Object Storage | virtual-hosted | 3 |
| `Vultr` | Vultr Object Storage | path | 9 |
| `MegaS4` | MEGA S4 | path | 4 |
| `Storj` | Storj | virtual-hosted | one endpoint |
| `Filebase` | Filebase | virtual-hosted | one endpoint |
| `GoogleCloud` | Google Cloud Storage | virtual-hosted | one endpoint |
| `Contabo` | Contabo Object Storage | path | 3 |
| `Hetzner` | Hetzner Object Storage | virtual-hosted | 3 |
| `OVHcloud` | OVHcloud Object Storage | virtual-hosted | 8 |
| `Exoscale` | Exoscale SOS | virtual-hosted | 7 |
| `IBMCloud` | IBM Cloud Object Storage | virtual-hosted | 9 |
| `AlibabaOSS` | Alibaba Cloud OSS | virtual-hosted | 17 |
| `TencentCOS` | Tencent Cloud COS | virtual-hosted | 11 |
| `MinIO` | MinIO (self-hosted) | path | operator's own |
| `Other` | Any S3-compatible endpoint | path | operator's own |

Addressing style is not cosmetic. Under virtual-hosted addressing the bucket
belongs in the `Host` header and must *not* be repeated in the path; under
path-style the reverse. Signing one and requesting the other fails.

### How the tables were checked

Every host in `Data\Regions` was confirmed by making an HTTPS request to the
endpoint it produces. Resolving the name proves nothing — every one of these
provider domains serves wildcard DNS, so an invented region resolves exactly
like a real one. A live endpoint answers (200, 307, 400 or 403, depending on
the provider); an invented one gives no response at all.

Addressing style was settled the same way, by asking for
`some-bucket.<endpoint>`: a provider that supports virtual-hosted addressing
answers with a not-found, and one that does not fails to connect. That is what
puts Cloudflare R2 and Contabo on path style.

`Other` covers anything not listed. iDrive e2 belongs there rather than as a
case of its own — its endpoints carry a per-account instance number, so there
is no template to write.

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
