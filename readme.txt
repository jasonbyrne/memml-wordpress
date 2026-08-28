=== Memml Calendar ===
Contributors: memml
Tags: events, calendar, volunteers, nonprofit, memml
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display your nonprofit's public Memml events and volunteer opportunities on WordPress.

== Description ==

Memml Calendar is a lightweight, server-rendered bridge to Memml's public,
unauthenticated v1 JSON feeds. It does not sync event data into WordPress and it
does not store Memml credentials.

Choose the calendar that belongs on each page:

* Use the Memml Calendar block or `[memml_calendar default="events"]` shortcode to let visitors switch views.
* Use the Memml Events block or `[memml_events]` shortcode for the general events calendar.
* Use the Memml Volunteers block or `[memml_volunteers]` shortcode for volunteer opportunities.

Set `default="volunteers"` on `[memml_calendar]` when volunteer opportunities should
be selected first.

The plugin requests public data from memml.com, caches successful responses,
revalidates them using ETags, and can show the last known-good response during a
temporary service or network error.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/memml`, or install a release ZIP.
2. Activate Memml Calendar through the Plugins screen in WordPress.
3. Open Settings > Memml Calendar and enter your Memml organization key.
4. Select Test connection and confirm that your organization name appears.
5. Add either Memml Events or Memml Volunteers to a page.

== Frequently Asked Questions ==

= Does this plugin need a Memml API key? =

No. Memml Calendar reads only unauthenticated public feeds and stores no credentials.

= Which timezone does the plugin use? =

Dates and times are rendered in the timezone supplied by the Memml organization feed,
not the WordPress site timezone.

= Can I show both kinds of calendar? =

Yes. Each page can use the events block, the volunteers block, or both.

== Changelog ==

= 0.1.0 =

* Initial development release.
