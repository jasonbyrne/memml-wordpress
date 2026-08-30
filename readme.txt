=== Memml Calendar ===
Contributors: memml
Tags: events, calendar, volunteers, nonprofit, memml
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display your nonprofit's public Memml events and volunteer opportunities on WordPress.

== Description ==

Memml Calendar is a lightweight, server-rendered bridge to Memml's public,
unauthenticated v1 JSON feeds. It does not sync event data into WordPress and it
does not store Memml credentials.

Choose the calendar that belongs on each page:

* Use the Memml Calendar block or `[memml_calendar calendar="events" view="list" period="upcoming" url_key="main"]` shortcode to let visitors switch calendars and display views.
* Use the Memml Events block or `[memml_events view="list" period="upcoming" url_key="events"]` shortcode for general events with a List/Month switcher.
* Use the Memml Volunteers block or `[memml_volunteers view="list" period="upcoming" url_key="volunteers"]` shortcode for volunteer opportunities with a List/Month switcher.

Set the block's Maximum items setting, or the shortcode `limit` property, to show
only the next few items in a list. The default, 0, shows every item. Month view
always shows every item.

Set the block's List style setting, or the shortcode `list_style="rows"` property,
to stack compact full-width items instead of the default card grid. Subscription
links (Google Calendar, Apple / Outlook, RSS) appear above each calendar; turn
them off with the block's Subscribe links setting or `subscribe="no"`.
Clicking an item opens its full details in a pop-up, and events offer per-event
Apple / Outlook and Google add-to-calendar links.
Events with structured venue data show the venue name and address separately,
link complete addresses to Google Maps, and include any supplied venue contact,
parking, and arrival details in the pop-up. Legacy location text remains fully
supported.

Settings > Memml Calendar > Display defaults sets the site-wide initial view,
list style, and subscribe-links behaviour. Blocks and shortcodes follow those
defaults unless one sets its own value.

The blocks preview themselves in the editor using the same server-side renderer
visitors get, so layouts and filters can be checked without leaving the post.

Set `calendar="volunteers"` on `[memml_calendar]` when volunteer opportunities should
be selected first. Set `view="month"` on any shortcode when Month should be selected
first. These properties set the initial selections; visitors can change them.
In List view, visitors can choose Upcoming or Past. Upcoming is selected by default;
set `period="past"` to select Past initially. Dates and sorting use the organization's
timezone. Upcoming is oldest-first from today onward, while Past is newest-first.

Every visitor control is a real link, so the calendar keeps working when
JavaScript is unavailable and views can be opened in a new tab. When JavaScript is
available the same click is handled in place, without a page load.

Calendar changes are reflected in instance-scoped query parameters such as
`memml_main_calendar`, `memml_main_view`, `memml_main_period`, and
`memml_main_month`. Visitors can copy that URL to share the selected calendar,
layout, list filter, and displayed month. The optional block Share-link identifier
or shortcode `url_key` supplies the `main` portion and keeps links stable on pages
with multiple calendars. Calendars without one receive a per-page sequence.

Month view includes empty months between the earliest and latest dated feed items
when the feed spans five years or less. Wider feeds keep all populated months while
skipping long empty gaps to prevent excessive page markup.
Past lists depend on the public Memml feeds retaining historical records; the plugin
does not create or synchronize a separate history archive. Expired registration,
volunteer, and Add to calendar actions are not shown for past items.

The plugin requests public data from memml.com, caches successful responses,
revalidates them using ETags, and can show the last known-good response during a
temporary service or network error. After a failed request it waits a short
backoff period before contacting Memml again, so an outage cannot slow down every
page view. Settings > Memml Calendar lists the feed URLs the site reads and
provides a Clear cached data button for publishing a Memml change immediately.

Colors, spacing, and corner radii are exposed as CSS custom properties on the
`.memml-calendar` container, and every front-end rule uses `:where()`, so a theme
can restyle the calendar without fighting plugin specificity.

Deleting the plugin removes its settings and cached feed data.

== Installation ==

1. Download the `memml-calendar-VERSION.zip` file attached to the latest GitHub release. Do not use GitHub's automatically generated Source code archive.
2. In WordPress Admin, open Plugins > Add New Plugin and select Upload Plugin.
3. Choose the downloaded ZIP, select Install Now, and then activate Memml Calendar.
4. Open Settings > Memml Calendar and enter your Memml organization key. For a feed URL containing `/api/public/v1/river-city-neighbors/events.json`, the key is `river-city-neighbors`.
5. Select Test connection and confirm that your organization name appears.
6. Add the Memml Calendar, Memml Events, or Memml Volunteers block to a page. Shortcodes are also available as described above.

To update a manually installed copy, download the newer release ZIP and upload it
through Plugins > Add New Plugin > Upload Plugin. WordPress will offer to replace the
installed version.

== Frequently Asked Questions ==

= Does this plugin need a Memml API key? =

No. Memml Calendar reads only unauthenticated public feeds and stores no credentials.

= Which timezone does the plugin use? =

Dates and times are rendered in the timezone supplied by the Memml organization feed,
not the WordPress site timezone.

= Can I show both kinds of calendar? =

Yes. Each page can use the events block, the volunteers block, or both.

= Can I use a month calendar instead of a list? =

Yes. Visitors can switch between List and Month. The block setting or `view` shortcode
property controls which view they see first.

= How are Upcoming and Past lists determined? =

The organization-local calendar date is compared with today. Upcoming includes today
and future dates in ascending order. Past includes dates before today in descending order.

= How quickly do Memml changes appear on my site? =

Within about ten minutes. To publish a change immediately, open
Settings > Memml Calendar and select Clear cached data.

= Can I show only the next few events? =

Yes. Set the block's Maximum items setting or the shortcode `limit` property. It
applies to Upcoming and Past lists; Month view always shows every item.

= Can I link directly to a selected calendar view? =

Yes. The URL updates as visitors change the calendar, layout, or displayed month.
Copying the current URL preserves those selections for the recipient.

== Changelog ==

= 0.4.1 =

* Added richer structured venue displays, including Google Maps links for full
  addresses and optional venue details in event pop-ups.

= 0.4.0 =

* Added site-wide display defaults (initial view, list style, subscribe links)
  under Settings > Memml Calendar. Blocks and shortcodes follow those defaults
  unless they choose their own value, and their view, list style, and subscribe
  settings now offer an explicit Site default choice.
* Redesigned the compact rows list style to match memml.com's lists: the date
  chip, item details, and the status badge or actions now share one compact
  full-width row instead of a stretched card.

= 0.3.0 =

* Added optional calendar subscription links for Google Calendar, Apple / Outlook,
  and RSS, with block and shortcode controls for hiding them.
* Added a compact row presentation alongside the existing card-grid list style.
* Calendar items now open full details in an accessible dialog while keeping
  registration and add-to-calendar actions directly available.
* Added per-event Google Calendar links and improved event date presentation.
* Lists now show item counts, card summaries are clamped for consistent layouts,
  and controls are grouped into a more compact toolbar.
* Crowded month cells now collapse additional items behind a disclosure control.
* Improved compatibility with theme typography and button styles.

= 0.2.0 =

* Raised the minimum WordPress version to 6.6. Earlier versions do not register a
  script the block editor bundle depends on, which left the blocks with no editing
  interface.
* Blocks now preview themselves in the editor using the server-side renderer.
* Added a Maximum items setting and a matching shortcode `limit` property for lists.
* Visitor controls are now links, so the calendar works without JavaScript and
  views can be opened in a new tab.
* A failed Memml request is no longer retried on every page view, so an outage
  cannot slow the whole site down.
* Added a Clear cached data button and the feed URLs to the settings screen, plus a
  Settings link on the Plugins screen and a setup notice until a key is saved.
* Saving an invalid organization key no longer discards the working one.
* Deleting the plugin now removes its settings and cached feed data.
* Fixed calendar controls inheriting theme link styles, which removed the primary
  button's contrast against its accent fill.
* Improved accessibility: today is marked in month view, the month grid is
  reachable by keyboard, controls have visible focus styles, and cancelled events
  no longer rely on a low-contrast opacity.

= 0.1.0 =

* Initial development release.
