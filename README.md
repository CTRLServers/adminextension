# CTRLServers Pterodactyl Admin Extension

Adds authenticated metadata and settings endpoints to a Pterodactyl panel:

- `GET /admin/gettitle`
- `GET /admin/getpanelversion`
- `GET|PATCH /admin/ctrlservers/settings/general`
- `GET|PATCH /admin/ctrlservers/settings/mail`
- `GET|PATCH /admin/ctrlservers/settings/advanced`

All endpoints use Pterodactyl's Application API authentication and rate limiting. Requests must provide a valid Application API key or a root-administrator Client API key.

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
  "version": "1.15.1",
  "latest_version": "1.15.1",
  "up_to_date": true,
  "update_check_available": true
}
```

The installed version remains a string because Pterodactyl versions contain multiple numeric components and development builds may return `canary`. The latest stable version is retrieved from the official Pterodactyl GitHub releases API and cached for one hour. If the check is unavailable, `latest_version` and `up_to_date` are `null`, and `update_check_available` is `false`.

## Settings

Each settings `GET` response contains `fields` with the panel's current values and `options` for frontend dropdowns. Submit changed values to the same URL with `PATCH` and a JSON body.

The `PATCH` response has the same shape as `GET`, populated with the saved values, so a frontend can replace its local form state directly after a successful update.

The general endpoint manages the company name, two-factor requirement, and default language. Its language options are generated from the language directories installed on that panel.

The mail endpoint manages SMTP host, username, port, TLS or SSL encryption, password, sender address, and sender name. Existing SMTP passwords are never returned. The response provides `smtp_password_configured`; omit `smtp_password` or send an empty string to keep the current password.

The advanced endpoint manages reCAPTCHA, HTTP timeouts, and automatic allocation creation. Starting and ending ports are required when automatic allocation creation is enabled.

All updates use Pterodactyl's settings repository and restart its queue worker after saving.

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
