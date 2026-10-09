# CTRLServers Pterodactyl Admin Extension

Adds authenticated metadata and settings endpoints to a Pterodactyl panel:

- `GET /admin/gettitle`
- `GET /admin/getpanelversion`
- `GET /admin/getsysteminformation`
- `GET|POST /admin/ctrlservers/application-api`
- `DELETE /admin/ctrlservers/application-api/{apikey}`
- `GET|POST /admin/ctrlservers/database-hosts`
- `GET|PATCH /admin/ctrlservers/database-hosts/{host}`
- `GET /admin/ctrlservers/servers`
- `GET /admin/ctrlservers/servers/{server}`
- `GET|PATCH /admin/ctrlservers/settings/general`
- `GET|PATCH /admin/ctrlservers/settings/mail`
- `GET|PATCH /admin/ctrlservers/settings/advanced`
- `GET /admin/ctrlservers/nodes/{node}/status`
- `GET /admin/ctrlservers/nodes/{node}/information`
- `GET /admin/ctrlservers/nodes/{node}/configuration`
- `POST /admin/ctrlservers/nodes/{node}/deployment`
- `GET /admin/ctrlservers/nodes/{node}/servers`
- `GET /admin/ctrlservers/nodes/{node}/allocations`
- `GET /admin/ctrlservers/nests`
- `GET|PATCH|DELETE /admin/ctrlservers/nests/{nest}`
- `GET /admin/ctrlservers/nests/{nest}/eggs/{egg}`
- Egg configuration, variables, install script, import, and export endpoints below each Egg resource

All endpoints use Pterodactyl's Application API authentication and rate limiting. Requests must provide a valid Application API key or a root-administrator Client API key.

The Application API endpoints list panel application keys with their creator and permission metadata, create new keys using Pterodactyl's native key service, and delete existing application keys.

The database host endpoints list, create, view, and update database hosts through Pterodactyl's native connection-validation services. Detailed responses include the databases attached to the host and their server information.

The server endpoints return panel servers with their owner, Node, Egg, Nest, default allocation, resource limits, and lifecycle state.

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
  "update_check_available": true,
  "release_url": "https://github.com/pterodactyl/panel/releases/tag/v1.15.1"
}
```

The installed version remains a string because Pterodactyl versions contain multiple numeric components and development builds may return `canary`. The latest stable version and its GitHub release URL are retrieved from the official Pterodactyl GitHub releases API and cached for one hour. If the check is unavailable, `latest_version`, `up_to_date`, and `release_url` are `null`, and `update_check_available` is `false`.

The system information endpoint reports the host CPU name, core count and current utilization, plus used and total memory and disk space. CPU data is read from Linux procfs, while disk usage is measured on the filesystem containing the Pterodactyl installation.

The node status endpoint checks the selected Node through Pterodactyl's Wings repository and returns an `online` boolean. A failed Wings connection is reported as offline without exposing connection details.

The node information endpoint reports the Wings version, operating system, architecture, kernel version, and CPU thread count returned by the selected Node.

The node configuration endpoint returns Pterodactyl's native YAML configuration for the selected Node. The deployment endpoint creates or reuses a node-scoped Application API key and returns the Wings auto-configuration command.

The node servers endpoint returns the servers assigned to the selected Node with their owner and Egg or Nest service names. The node allocations endpoint returns every allocation with the name of its assigned server when applicable.

## Settings

Each settings `GET` response contains `fields` with the panel's current values and `options` for frontend dropdowns. Submit changed values to the same URL with `PATCH` and a JSON body.

The `PATCH` response has the same shape as `GET`, populated with the saved values, so a frontend can replace its local form state directly after a successful update.

The general endpoint manages the company name, two-factor requirement, and default language. Its language options are generated from the language directories installed on that panel.

The mail endpoint manages SMTP host, username, port, TLS or SSL encryption, password, sender address, and sender name. Existing SMTP passwords are never returned. The response provides `smtp_password_configured`; omit `smtp_password` or send an empty string to keep the current password.

The advanced endpoint manages reCAPTCHA, HTTP timeouts, and automatic allocation creation. Starting and ending ports are required when automatic allocation creation is enabled.

All updates use Pterodactyl's settings repository and restart its queue worker after saving.

## Nests and Eggs

The Nest endpoints provide searchable Nest summaries, detailed Nest and Egg data, safe Nest updates and deletion, Egg JSON import and export, configuration editing, variable management, and install script editing. Nest deletion uses Pterodactyl's native deletion service and is rejected while servers remain attached.

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
