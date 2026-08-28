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

* Use the Memml Calendar block or `[memml_calendar calendar="events" view="list"]` shortcode to let visitors switch calendars and display views.
* Use the Memml Events block or `[memml_events view="list"]` shortcode for general events with a List/Month switcher.
* Use the Memml Volunteers block or `[memml_volunteers view="list"]` shortcode for volunteer opportunities with a List/Month switcher.

Set `calendar="volunteers"` on `[memml_calendar]` when volunteer opportunities should
be selected first. Set `view="month"` on any shortcode when Month should be selected
first. These properties set the initial selections; visitors can change them.

Calendar changes are reflected in the page URL using `memml_calendar`, `memml_view`,
and `memml_month` query parameters. Visitors can copy that URL to share the selected
calendar, layout, and displayed month. Query parameters override the shortcode or
block's initial selections when a shared link is opened.

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

= Can I use a month calendar instead of a list? =

Yes. Visitors can switch between List and Month. The block setting or `view` shortcode
property controls which view they see first.

= Can I link directly to a selected calendar view? =

Yes. The URL updates as visitors change the calendar, layout, or displayed month.
Copying the current URL preserves those selections for the recipient.

== Changelog ==

= 0.1.0 =

* Initial development release.
