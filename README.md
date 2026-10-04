# CTRLServers Pterodactyl Admin Extension

Adds two authenticated metadata endpoints to a Pterodactyl panel:

- `GET /admin/gettitle`
- `GET /admin/getpanelversion`

Both endpoints use Pterodactyl's Application API authentication and rate limiting. Requests must provide a valid Application API key or a root-administrator Client API key.

## Install

Run these commands from the Pterodactyl panel directory:

```sh
composer config repositories.ctrlservers-adminextension vcs https://github.com/CTRLServers/adminextension
composer require ctrlservers/adminextension:dev-main
php artisan optimize:clear
```

Laravel package discovery loads the extension automatically. No Pterodactyl core files are changed.

## Requests

```sh
curl https://panel.example.com/admin/gettitle \
  -H "Authorization: Bearer ptla_YOUR_KEY" \
  -H "Accept: application/json"
```

```json
{
  "title": "Pterodactyl"
}
```

```sh
curl https://panel.example.com/admin/getpanelversion \
  -H "Authorization: Bearer ptla_YOUR_KEY" \
  -H "Accept: application/json"
```

```json
{
  "version": "1.15.1"
}
```

The version remains a string because Pterodactyl versions contain multiple numeric components and development builds may return `canary`.

## Update

```sh
composer update ctrlservers/adminextension
php artisan optimize:clear
```

## Remove

```sh
composer remove ctrlservers/adminextension
php artisan optimize:clear
```

## Responses

Unauthenticated requests return Pterodactyl's normal authentication error. Authenticated non-administrator accounts are rejected by Pterodactyl's Application API middleware.
