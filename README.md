# Memml Calendar

Memml Calendar is a WordPress plugin that displays a nonprofit's public events and volunteer opportunities from [Memml](https://memml.com). It uses Memml's unauthenticated public feeds, stores no credentials, and renders dates in the organization's configured timezone.

## Requirements

- WordPress 6.2 or newer
- PHP 7.4 or newer
- A Memml organization with public feeds enabled

## Install from a release ZIP

1. Open the repository's **Releases** page and download the attached `memml-calendar-VERSION.zip` file. Do not download GitHub's automatically generated **Source code** archive.
2. In WordPress Admin, open **Plugins → Add New Plugin**.
3. Select **Upload Plugin**, choose the downloaded ZIP, and select **Install Now**.
4. When installation finishes, select **Activate Plugin**.
5. Open **Settings → Memml Calendar**.
6. Enter the Memml organization key and select **Test connection**. For example, the organization key in the URL below is `river-city-neighbors`:

   ```text
   https://memml.com/api/public/v1/river-city-neighbors/events.json
   ```

7. Confirm that the connection test displays the organization's name, then save the settings.
8. Add the **Memml Calendar**, **Memml Events**, or **Memml Volunteers** block to a page.

The organization key is configured once for the WordPress site. Blocks and shortcodes only control the initially selected calendar, layout, and date filter.

## Shortcodes

```text
[memml_calendar calendar="events" view="list" period="upcoming" url_key="main"]
[memml_events view="list" period="upcoming" url_key="events"]
[memml_volunteers view="month" url_key="volunteers"]
```

Visitors can change the calendar, List/Month layout, Upcoming/Past list filter, and displayed month. Those changes are stored in instance-scoped query parameters so the resulting URL can be shared.

## Updating a manual installation

Download the new release ZIP and upload it through **Plugins → Add New Plugin → Upload Plugin**. WordPress will offer to replace the installed version. GitHub-hosted releases do not provide automatic WordPress updates.

## Build an installable ZIP

Install the development dependencies once:

```bash
npm ci
```

Then build the production block assets and create the release archive:

```bash
npm run package
```

The command creates `dist/memml-calendar-VERSION.zip`. Its top-level directory is `memml/`, so the archive can be uploaded directly through WordPress Admin.

The archive includes only runtime plugin files. Development dependencies, tests, source files, and repository tooling are excluded.

## Development checks

```bash
composer lint
composer test
npm run lint:js
npm run lint:css
npm run build
npm run test:e2e
```

The complete WordPress smoke test can be run with:

```bash
npm run wp-env:start
npm run test:wp-env
npm run wp-env:stop
```

## License

Memml Calendar is licensed under GPL-2.0-or-later. See [LICENSE](LICENSE).
