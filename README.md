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

## Settings page

**Settings → Memml Calendar** is arranged in five sections:

- **Connection** — the organization key, **Test connection**, and an
  **Advanced** Memml base URL for testing against another environment.
- **Calendar display** — the initial calendar, view, and list filter, the
  maximum list items, the visitor switchers, and the subscription row.
- **Content and actions** — which details and calls to action calendars show.
- **Appearance** — the list style. Calendars otherwise inherit the theme; see
  [Theming](#theming).
- **Status** — how long feed data is cached, the feed URLs this site reads, and
  **Clear cached data**.

These are site-wide settings: they apply to every block set to **Use site
setting** and every shortcode without its own properties, while explicit block
and shortcode choices keep their values.

A **Preview** beside the settings (below them on narrower screens) shows the
Memml Calendar, Memml Events, or Memml Volunteers block as it will look with the
choices currently on the page, before they are saved. It uses the saved
organization key, renders with the calendar's own styles and the theme's global
styles in a separate frame, and only administrators can open it. Each preference
section has **Reset section to defaults**, which fills in that section's
defaults and updates the preview; **Save Changes** applies them.

## Cached feed data

Successful Memml responses are cached for about ten minutes and revalidated with
ETags. After a failed request the plugin waits a short backoff period before
trying again, serving the last known-good response in the meantime, so an
unreachable Memml service cannot slow down every page view.

**Settings → Memml Calendar → Status** lists the feed URLs this site
reads and provides a **Clear cached data** button for publishing a Memml change
immediately. Saving a new organization key or base URL clears the cache
automatically.

Three filters adjust the timings: `memml_feed_cache_ttl`,
`memml_feed_stale_ttl`, and `memml_feed_failure_ttl`.

Memml's public feeds are also cached at Memml's edge for up to five minutes, so
a change can take up to about fifteen minutes to appear. If Clear cached data
runs within a few minutes of an edit and the change is still missing, clear it
again a few minutes later.

### Full-page caches

A full-page cache can keep serving a rendered calendar long after the feed
changes. LiteSpeed Cache, for example, keeps public pages for a week by default.
The plugin works with LiteSpeed Cache automatically:

- A page that renders a Memml block or shortcode asks LiteSpeed Cache to keep it
  no longer than the feed data it shows stays fresh (at most ten minutes by
  default), and one minute while Memml is unavailable or stale data is shown.
  A shorter public lifetime configured in LiteSpeed Cache is kept.
- Those pages carry a `memml` cache tag. Clear cached data and any change to
  Memml Calendar settings purge the tagged pages.

For other page caches, exclude pages with Memml calendars or give them a short
lifetime, or connect the cache through these hooks:

- `memml_page_cache_ttl` filter: adjusts the requested lifetime in seconds for
  each rendered feed. It receives the lifetime, the feed (`events`,
  `volunteers`, or `committees`), and the feed result. Return `0` to leave page
  caches alone.
- `memml_page_cache_limit` action: fires with the maximum page lifetime in
  seconds when a page shows Memml content, and again if a later feed on the
  same page expires sooner.
- `memml_page_cache_purge` action: fires when cached pages showing Memml
  content should be discarded.

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
stacks compact full-width items — date chip, then short when/where/cost lines,
the description, and the add-to-calendar links, with the status badge, calls
to action, and event page link in a fixed-width right-hand column so they line
up from row to row — which suits a narrow column or a text-heavy feed. `subscribe` controls the
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
cancelled events, RSVP, registration, Join online, volunteer signup,
add-to-calendar actions, and View event page links. All are enabled by
default, preserving existing calendars. Each block groups the relevant overrides under **Content** and
**Actions**, with **Use site setting**, **Show**, and **Hide** choices.

Advanced shortcode placements can use `show_images`, `show_descriptions`,
`show_item_count`, `show_details`, `show_venue_cost`,
`show_volunteer_availability`, `show_cancelled_events`, `show_rsvp`,
`show_registration`, `show_online`, `show_volunteer_signup`,
`show_add_to_calendar`, and `show_event_page`. For example:

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
**Join online** action. RSVP, registration, volunteer, online meeting, and
add-to-calendar actions are omitted after the event's last day in the
organization's timezone.

Events and volunteer opportunities that run across several days show their
first and last day, such as "Fri, Sep 11 – Sun, Sep 13 · All day", and timed
items pair each day with its time. Month view places a multi-day item on every
day it runs, even across months; later days show "All day" or, on the last day,
"Until" its end time, and carry an `is-continued` class for theme styling. A
multi-day item stays in Upcoming until its last day has passed. Dates follow
memml.com's rules, where an all-day item's end is the midnight after its last
day.

When an organizer advertises RSVP in Memml, the feed's `rsvp` object drives an
**RSVP** action that links to the Memml RSVP page. While RSVPs are open the
action is a primary button, with the organizer's chosen capacity wording beside
it, such as "Spots available" or "3 spots remaining". Once RSVPs are closed or
the event is full, the calendar shows a **Full** or **RSVP closed** status
instead of a link. RSVP can appear alongside a registration link, Join online,
and volunteer signup when an event offers all of them. The details pop-up also
names an explicit RSVP deadline, such as "RSVP by September 10, 2026 at 11:59
pm", when the organizer set one that differs from the event start.

Cancelled events keep their add-to-calendar and event page links but omit RSVP,
registration, Join online, and volunteer actions, since there is nothing left
to sign up for.

Every event also carries a **View event page** link to its Memml event page,
where visitors can read the full record or manage an existing RSVP. Unlike the
other actions, this link is kept for past events, so there is always a path to
the Memml event page from cards, rows, month entries, and the details pop-up.

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
