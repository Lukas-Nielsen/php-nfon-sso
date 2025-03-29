# php-nfon-ssh

package to login to the nfon portals with the nfon sso

## getting started

```sh
composer require ln/nfon_sso
```

## usage

### conf

you need the portal base url eg. https://admin.nfon.com or https://start.cloudya.com and the client id eg. admin-portal or cloudya

```php
$client = new Client(string <portalBaseUrl>, string <clientId>);
```

### auth

#### login

```php
$client->Login(string <username>, string <password>);
```

#### otp

```php
$client->OTP(int <otp (6 digit)>);
```

### token operation

```php
// refresh the token internally
$client->RefreshToken(): bool;

// read token from json file
$client->TokenFromJsonFile(string </path/to/token/file.json>): bool;

// write token to json file
$client->TokenToJsonFile(string </path/to/token/file.json>): bool;

// set token
$client->SetToken(Token <token object>): void;

// get token
$client->GetToken(): Token;
```

### functions

#### get, delete

```php
$client-><get|delete>(string <uri>, array <query>, array <header>)
```

#### post, put, patch

```php
$client-><post|put|patch>(string <uri>, array <payload>, array <query>, array <header>)
```

## known possible client id's

### admin portal

- dts-admin-portal
- dts-admin-portal-preview
- dfn-admin-portal
- dfn-admin-portal-preview
- chess-admin-portal
- chess-admin-portal-preview
- dialog-telekom-admin-portal
- dialog-telekom-admin-portal-preview
- telekom-admin-portal
- telekom-admin-portal-preview
- o2-business-admin-portal
- o2-business-admin-portal-preview
- versatel-admin-portal
- versatel-admin-portal-preview
- smarticloud-admin-portal
- smarticloud-admin-portal-preview
- phoneup-admin-portal
- phoneup-admin-portal-preview
- admin-portal
- admin-portal-preview

### user portal

- centrexx
- cloudya
- dfn
- dialog-telekom
- o2
- telekom
- one-and-one
- promelit
- phoneup
- nconnect-voice
- sip-trunk
- o2-business-teams-telefonie
