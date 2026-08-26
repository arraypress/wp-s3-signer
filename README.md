# S3 Signer

Sign requests to S3-compatible storage, so a file can be handed out without
being made public or streamed through PHP.

## What it does

Selling a file means the URL has to work for the buyer and nobody else. The
answer is a presigned URL — a link that carries its own signature and expires
— and producing one means AWS Signature Version 4, which is a specific
sequence of canonicalising, hashing and HMAC steps that fails silently if any
of it is wrong.

This implements the signing and nothing else. No HTTP client, no SDK, no
config file: pass keys, get a URL.

## Features

* Produce a download link that expires in the number of seconds you choose
* Set the filename the browser saves as, regardless of the object's key
* Produce an upload link, so a file goes to storage without touching the server
* Sign delete, head and multipart requests, when a URL is not what you need
* Point at any S3-compatible provider — R2, B2, Wasabi, DigitalOcean, MinIO
* Get regional endpoints and addressing style right per provider

## Installation

```bash
composer require arraypress/wp-s3-signer
```

## Quick start

A sixty-second link, which the browser saves under a name of your choosing:

```php
use ArrayPress\S3Signer\Provider;

$signer = Provider::R2->signer( $access_key, $secret_key, account_id: $account_id );

$url = $signer->presign_get( 'my-bucket', 'masters/a1.wav', 60, 'Album Master.wav' );
```

The object stays private. The link works until it expires, and then does not.

## What it does not do

It signs; it does not send. Nothing here makes an HTTP request, lists a
bucket or manages a file — `arraypress/wp-s3-browser` is the layer that does,
and it is built on this.

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later

## License

GPL-2.0-or-later
