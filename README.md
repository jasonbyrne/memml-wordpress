# Memml Calendar

Memml Calendar is a WordPress plugin that displays a nonprofit's public events and volunteer opportunities from [Memml](https://memml.com). It uses Memml's unauthenticated public feeds, stores no credentials, and renders dates in the organization's configured timezone.

## Requirements

- WordPress 6.6 or newer
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

The organization key and display defaults are configured once for the WordPress
site. Blocks and shortcodes inherit those defaults unless a placement needs an
intentional exception.

## Cached feed data

Successful Memml responses are cached for about ten minutes and revalidated with
ETags. After a failed request the plugin waits a short backoff period before
trying again, serving the last known-good response in the meantime, so an
unreachable Memml service cannot slow down every page view.

**Settings → Memml Calendar → Cached feed data** lists the feed URLs this site
reads and provides a **Clear cached data** button for publishing a Memml change
immediately. Saving a new organization key or base URL clears the cache
automatically.

Three filters adjust the timings: `memml_feed_cache_ttl`,
`memml_feed_stale_ttl`, and `memml_feed_failure_ttl`.

## Theming

The front-end stylesheet is written with `:where()` so theme rules win by
default. Colors, spacing, and radii are exposed as custom properties that a
theme can redefine on `.memml-calendar` or any ancestor:

```css
.memml-calendar {
	--memml-accent: #b91c1c;
	--memml-card-background: #1c1f26;
	--memml-text: #f4f6fa;
	--memml-border: #333a45;
	--memml-muted: #aab2bf;
}
```

See `assets/calendar.css` for the full list.

## Shortcodes

```text
[memml_calendar]
[memml_events]
[memml_volunteers]
```

By default, visitors can change the calendar, List/Month layout, Upcoming/Past
list filter, and displayed month. Enabled controls use instance-scoped query
parameters, so the resulting URL can be shared.

Most calendars need no shortcode properties because they inherit **Settings →
Memml Calendar**. Advanced placements can explicitly set
`calendar` (`events` or `volunteers`), `view` (`list` or `month`), `period`
(`upcoming` or `past`), `list_style` (`grid` or `rows`), `limit`, `subscribe`,
`calendar_switcher`, `layout_switcher`, and `period_switcher`. Boolean overrides
accept `yes` or `no`:

```text
[memml_calendar calendar="volunteers" view="list" period="upcoming" list_style="rows" limit="3" subscribe="no" calendar_switcher="no" layout_switcher="no" period_switcher="no" url_key="main"]
```

`limit` caps how many items each list shows, which suits a sidebar or a "next few
events" section. A value of `0` shows every item. Month view always shows every
item, because a month grid with items missing would misrepresent the calendar.

`list_style` chooses how lists render: `grid` arranges cards in columns; `rows`
stacks compact full-width items — date chip, details, and actions in one line —
which suits a narrow column or a text-heavy feed. `subscribe` controls the
Google Calendar / Apple / Outlook / RSS subscription links shown above the
calendar (`yes` or `no`).

Site-wide defaults cover the initially selected calendar, initial view, initial
list filter, list style, maximum list items, the three visitor switchers, and the
subscription row. Every matching block control offers **Use site setting**.
Hiding a switcher fixes the calendar to its configured calendar, view, or list
filter, omits the unused alternate content, and ignores conflicting visitor
query parameters. Existing explicit block attributes and shortcode properties
continue to take precedence, including `limit="0"` for an unlimited list.

The **Content and actions** settings control images, descriptions, list item
counts, details dialogs, venue/location and cost, volunteer availability,
cancelled events, registration, Join online, volunteer signup, and
add-to-calendar actions. All are enabled by default, preserving existing
calendars. Each block groups the relevant overrides under **Content** and
**Actions**, with **Use site setting**, **Show**, and **Hide** choices.

Advanced shortcode placements can use `show_images`, `show_descriptions`,
`show_item_count`, `show_details`, `show_venue_cost`,
`show_volunteer_availability`, `show_cancelled_events`, `show_registration`,
`show_online`, `show_volunteer_signup`, and `show_add_to_calendar`. For example:

```text
[memml_events show_images="no" show_descriptions="no" show_cancelled_events="no" show_registration="yes"]
```

Hiding cancelled events removes them from both List and Month views. Content
preferences apply wherever that content is available; for example, row and
month layouts do not introduce images when images are enabled.

Every item opens a pop-up with its full details — including the complete
description, which list cards clamp after a few lines — and events offer
per-event Apple / Outlook and Google add-to-calendar links. When the feed
supplies an online meeting URL, current and upcoming events also show a
**Join online** action. Registration, volunteer, online meeting, and
add-to-calendar actions are omitted after the event date in the organization's
timezone.

Events with structured venue data show the venue name and formatted address as
a richer location variant. Complete street addresses link to Google Maps, and
the details pop-up also includes any venue description, website, phone number,
parking information, and arrival instructions supplied by the feed. Older
events that only provide a `location` string continue to display that text.

Every enabled control is a real link. The calendar therefore keeps working with
JavaScript disabled, and visitors can middle-click or right-click a view to open
it in a new tab. When JavaScript is available the same click is handled in place,
without a page load.

## Block previews

The blocks preview themselves in the editor with the same server-side renderer
visitors get, so the layout, filters, and item limit can be checked without
leaving the post. Previews are non-interactive, and a block shows an actionable
placeholder instead when no organization key has been saved yet.

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

## Product roadmap

Planned work is tracked in [ROADMAP.md](ROADMAP.md). It is ordered around the
needs of small nonprofit organizations and identifies one explicit next item so
future development tasks can resume without relying on conversation history.

## License

Memml Calendar is licensed under GPL-2.0-or-later. See [LICENSE](LICENSE).
