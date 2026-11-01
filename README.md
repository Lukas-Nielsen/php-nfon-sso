# go-nfon-sso PHP module

This is a PHP port of the Go OAuth client for nfon SSO login flows.

## Installation

```bash
cd php
composer install
```

## Usage

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use ln\NfonSso\Client;

$client = new Client('https://admin.nfon.com', 'admin-portal');
$client->setUser('username', 'password', 'OTPSECRET');

$client->auth();

$token = $client->getToken();

$response = $client->getPortalApi('my-client', 'https://admin.nfon.com/api/endpoint', [], []);
print_r($response);
```

## Supported features

- login flow with Keycloak + optional OTP
- token refresh and persistence to JSON
- API GET/POST/PUT/PATCH/DELETE helpers
- file upload helper
- token validation and refresh before expiry

## Notes

The port follows the same authentication flow as the Go implementation, including PKCE and TOTP generation for the login challenge.
