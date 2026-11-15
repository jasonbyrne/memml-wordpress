=== Memml Calendar ===
Contributors: memml
Tags: events, calendar, volunteers, nonprofit, memml
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display your nonprofit's public Memml events and volunteer opportunities on WordPress.

== Description ==

Memml Calendar is a lightweight, server-rendered bridge to Memml's public,
unauthenticated v1 JSON feeds. It does not sync event data into WordPress and it
does not store Memml credentials.

Choose the calendar that belongs on each page:

* Use the Memml Calendar block or `[memml_calendar]` shortcode for events and volunteer opportunities, with visitor switchers enabled by default.
* Use the Memml Events block or `[memml_events]` shortcode for general events.
* Use the Memml Volunteers block or `[memml_volunteers]` shortcode for volunteer opportunities.
* Use the Memml Committees block or `[memml_committees]` shortcode to display a board of directors or public committee roster.

The Memml Committees block or `[memml_committees show_charter="no" show_vacant_seats="no"]`
shortcode lists every public committee for your organization, each with its
seats and the current holder's name, or Vacant when a seat is unfilled. The
charter and vacant seats are shown by default.

Settings > Memml Calendar is arranged in Connection, Calendar display, Content
and actions, Empty calendars, Appearance, and Status sections. Calendar display controls the
initially selected calendar, initial List or Month view, Month view on phones,
initial Upcoming or Past filter, maximum list items, the visitor switchers, and
the subscription row.
Appearance controls the list style and a few site-wide presets: a custom accent
color, a Light, Neutral, or Theme colors palette, comfortable or compact spacing,
rounded or square corners, and wide or compact card images. The defaults keep
the original look. Blocks offer Use site setting for every matching display and
content preference, and shortcodes inherit the site settings when properties
are omitted.

Once an organization key is saved, a Connected panel at the top of the settings
shows the organization name, its timezone, each available feed with its item
count, a Preview your calendar button, and copy buttons for the basic
shortcodes. If the key does not work, the panel says so instead.

Administrators can let Editors manage Memml Calendar with the Calendar managers
setting. Editors can then change the display, content, empty-calendar, and
appearance settings, refresh feeds, and use the preview, but only administrators
can change the organization key, the Memml base URL, or who manages the
calendar.

A preview beside the settings shows the Memml Calendar, Memml Events, or Memml
Volunteers block with the choices on the page before you save them. Each
preference section has a Reset section to defaults button that fills in its
defaults and updates the preview; Save Changes applies them. Show with nothing
upcoming previews the calendar without its upcoming items.

The Empty calendars section chooses, separately for Events and Volunteer
opportunities, what visitors see when nothing is upcoming: the standard message
(the default), your own message with an optional link such as a newsletter
sign-up, an email address, or a page on this site, or nothing at all. A hidden
feed leaves the combined calendar's switcher, and the combined calendar
disappears only when both feeds are hidden; editors see a short note in its
place in the block editor and settings preview. A calendar fixed to the Past
list uses its past items instead. When Memml cannot be reached and no earlier
copy is saved, visitors always see a short "temporarily unavailable" note rather
than your message or a hidden calendar. Blocks have a When nothing is upcoming
panel, and shortcodes accept `empty_state` (`standard`, `custom`, or `hide`),
`empty_message`, `empty_link_url`, and `empty_link_label`.

The Content and actions section controls images, descriptions, list item counts,
details pop-ups, venue/location and cost, volunteer availability, cancelled
events, RSVP, registration, Join online, volunteer signup, add-to-calendar
actions, and View event page links. These preferences are enabled by default. Each block groups its relevant
overrides under Content and Actions and offers Use site setting, Show, and Hide.

For an intentional exception, set block controls or shortcode properties such as
`[memml_calendar calendar="volunteers" view="list" period="upcoming" list_style="rows" limit="3" subscribe="no" calendar_switcher="no" layout_switcher="no" period_switcher="no" url_key="main"]`.
Explicit values continue to take precedence over site settings. A `limit` of 0
shows every item, while Month view always shows every item.

Set the block's List style setting, or the shortcode `list_style="rows"` property,
to stack compact full-width items instead of the card grid. Subscription links
(Google Calendar, Apple / Outlook, RSS) can be controlled with the block's
Subscription row setting or the `subscribe` property. The block's Visitor
controls panel can also show or hide the List/Month, Upcoming/Past, and combined
calendar switchers. Shortcodes use `layout_switcher`, `period_switcher`, and
`calendar_switcher` with `yes` or `no`.
Advanced shortcodes can also set `show_images`, `show_descriptions`,
`show_item_count`, `show_details`, `show_venue_cost`,
`show_volunteer_availability`, `show_cancelled_events`, `show_rsvp`,
`show_registration`, `show_online`, `show_volunteer_signup`,
`show_add_to_calendar`, and `show_event_page` to `yes` or `no`. For example, `[memml_events show_images="no" show_cancelled_events="no"]`
hides event images and removes cancelled events from both List and Month views.
Clicking an item opens its full details in a pop-up, and events offer per-event
Apple / Outlook and Google add-to-calendar links. Current and upcoming events
also show a Join online action when the feed supplies an online meeting URL.
When an organizer advertises RSVP in Memml, upcoming events show an RSVP button
that links to the Memml RSVP page, with the organizer's capacity wording beside
it; once RSVPs are closed or full, a status is shown instead. The pop-up also
names an explicit RSVP deadline when the organizer set one. Cancelled events
keep their calendar and event page links but omit sign-up actions. Every event
also offers a View event page link to its Memml event page, and that link is
kept for past events so there is always a path to the full details.
Events with structured venue data show the venue name and address separately,
link complete addresses to Google Maps, and include any supplied venue contact,
parking, and arrival details in the pop-up. Legacy location text remains fully
supported.

The blocks preview themselves in the editor using the same server-side renderer
visitors get, so layouts and filters can be checked without leaving the post. A
calendar hidden because it has nothing upcoming shows editors a short note there.

Set `calendar="volunteers"` on `[memml_calendar]` when volunteer opportunities should
be selected first. Set `view="month"` on any shortcode when Month should be selected
first; on phones, the Month view on phones setting or `phone_view` property decides
whether those calendars open in List view instead. These properties set the initial selections; visitors can change them
when the matching switcher is enabled. When a switcher is hidden, the selected
state stays fixed and conflicting query parameters are ignored.
In List view, visitors can choose Upcoming or Past. The site setting controls the
initial selection; set `period="past"` for an explicit shortcode exception. Dates
and sorting use the organization's timezone. Upcoming is oldest-first from today
onward, while Past is newest-first.

Every enabled visitor control is a real link, so the calendar keeps working when
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
volunteer, online meeting, and Add to calendar actions are not shown for past items.

Events and volunteer opportunities that run across several days show their first
and last day, and Month view places them on every day they run, even across months.
A multi-day item stays in Upcoming, with its actions, until its last day has passed.

The plugin requests public data from memml.com, caches successful responses,
revalidates them using ETags, and can show the last known-good response during a
temporary service or network error. After a failed request it waits a short
backoff period before contacting Memml again, so an outage cannot slow down every
page view. The Status section of Settings > Memml Calendar shows a card for Events
and Volunteer opportunities (and Committees once a page has shown them) with the
organization, timezone, item count, when the feed was last updated from Memml, and
the latest problem, in plain language: Up to date, Due for a check, Showing an older
copy, Not available, or Not loaded yet. Each card has a Refresh button, and Refresh
all checks every feed, to publish a Memml change immediately. If Memml cannot be
reached, the last copy that loaded is kept.

Pages that show a Memml calendar work with LiteSpeed Cache automatically: they are
kept in the page cache no longer than the feed data they show stays fresh, and
refreshing feeds or saving Memml Calendar settings clears them. Developers can
connect other page caches with the `memml_page_cache_ttl` filter and the
`memml_page_cache_limit` and `memml_page_cache_purge` actions.

Colors, spacing, and corner radii are exposed as CSS custom properties on the
`.memml-calendar` container, and every front-end rule uses `:where()`, so a theme
can restyle the calendar without fighting plugin specificity. The Appearance
presets set the same custom properties, so theme CSS still takes precedence.

Deleting the plugin removes its settings and cached feed data.

== Installation ==

1. Download the `memml-calendar-VERSION.zip` file attached to the latest GitHub release. Do not use GitHub's automatically generated Source code archive.
2. In WordPress Admin, open Plugins > Add New Plugin and select Upload Plugin.
3. Choose the downloaded ZIP, select Install Now, and then activate Memml Calendar.
4. Open Settings > Memml Calendar and enter your Memml organization key. For a feed URL containing `/api/public/v1/river-city-neighbors/events.json`, the key is `river-city-neighbors`.
5. Select Test connection and confirm that your organization name, timezone, and item counts appear, then save.
6. The Connected panel lists your feeds, links to the preview, and has copy buttons for the basic shortcodes. Add the Memml Calendar, Memml Events, or Memml Volunteers block to a page, or paste a shortcode.

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

Yes. Visitors can switch between List and Month by default. The block setting or
`view` shortcode property controls which view they see first, and administrators
can hide the switcher when a calendar should stay in one view.

= Can someone manage the calendar without being an administrator? =

Yes. In Settings > Memml Calendar, an administrator can turn on Let Editors
manage Memml Calendar. Editors then get Settings > Memml Calendar and can change
every setting except the Connection section, which stays with administrators.
To choose other roles or individual people instead, give them the
`manage_memml_calendar` capability with a role editor plugin.

= Why do phones show List view when I chose Month? =

List view shows each item's details at a glance, while Month view on a phone
needs a tap on a day first. With Settings > Memml Calendar > Month view on phones
set to Switch to List (the default), calendars that open in Month view open in
List view on phones and other narrow screens. Wide screens still open in Month
view, visitors can still choose Month, and a shared link to Month view always
opens Month. A calendar fixed to Month shows its upcoming list on phones. Choose
Keep Month to open Month view everywhere. Blocks have the same setting, and
shortcodes accept `phone_view="list"` or `phone_view="month"`. The settings
preview shows the phone layout when Show at phone width is checked.

= What does Month view look like on a phone? =

On phones, and in narrow columns or sidebars on wide screens, Month view
becomes a compact grid that fits without scrolling sideways. Days with events or volunteer
opportunities show dots instead of titles, with hollow dots for cancelled or
postponed events and a plus sign when a day has more than three items. A
multi-day item marks every day it runs. The month's items are listed by day beneath the grid, in the same style
as List view, and tapping a day jumps to that day's items. This works without
JavaScript. Calendars with room for it show the full month grid with titles.
Long words in event titles wrap instead of widening the page.

= How are Upcoming and Past lists determined? =

The organization-local calendar date is compared with today. Upcoming includes today
and future dates in ascending order. Past includes dates before today in descending order.
A multi-day item counts as upcoming until its last day has passed.

= How quickly do Memml changes appear on my site? =

Usually within ten to fifteen minutes. To publish a change sooner, open
Settings > Memml Calendar and select Refresh all under Status. Memml's own servers
can keep a feed for up to five minutes, so if you refresh right after an edit and
the change is still missing, refresh again a few minutes later.

= My site uses a page cache plugin. Will calendars go out of date? =

LiteSpeed Cache is supported automatically: pages with Memml calendars expire from
its cache along with the feed data, and refreshing feeds clears them. For other
page caches, exclude pages with Memml calendars from caching or give them a short
cache lifetime, and clear the page cache after refreshing Memml feeds.

= What do visitors see when there are no upcoming events? =

By default, "No upcoming events are currently available." Under Settings > Memml
Calendar > Empty calendars you can show your own message with an optional link,
such as a newsletter sign-up, or hide the calendar until something is upcoming.
Events and Volunteer opportunities each have their own choice, and a block or
shortcode can make its own. If Memml is temporarily unreachable, visitors see a
short "temporarily unavailable" note instead, so an outage never looks like an
empty calendar.

= Can I show only the next few events? =

Yes. Set the site-wide Maximum items default, or override it with the block's
Maximum items setting or shortcode `limit` property. It applies to Upcoming and
Past lists; Month view always shows every item.

= Can I link directly to a selected calendar view? =

Yes. The URL updates as visitors use enabled calendar, layout, list-filter, or
month controls. Copying the current URL preserves those selections for the recipient.

== Changelog ==

= 0.6.0 =

* Calendars that open in Month view now open in List view on phones, where the
  month grid was cut off. Visitors can still choose Month, and a shared link to
  a view always opens it. To keep Month on phones, set Settings > Memml
  Calendar > Month view on phones to Keep Month, or use the block setting or
  the shortcode's `phone_view` property.
* Month view on phones, and in narrow columns or sidebars, is now a compact
  grid that fits without scrolling sideways. Busy days show dots and link to
  that day's items, listed by day beneath the grid.
* Long words in event titles and details wrap instead of widening the page,
  the calendar switchers wrap on small screens, and the details pop-up fits
  small phones in every theme.
* Added an Empty calendars section: for Events and Volunteer opportunities
  separately, show the standard message, a custom message with an optional
  link, or hide the calendar when nothing is upcoming. Blocks and shortcodes
  can override it.
* The Status section now shows a card for each feed with its item count, last
  update, and any problem in plain language, with Refresh buttons for each
  feed or all of them.
* Administrators can let Editors manage Memml Calendar settings. The
  organization key and connection stay with administrators, and a new
  `manage_memml_calendar` capability can be granted to other roles.
* After connecting, a Connected panel shows your organization, each feed's
  item count, a preview button, and shortcodes to copy. Test connection now
  reports the timezone and item counts.

= 0.5.0 =

* Reorganized Settings > Memml Calendar into Connection, Calendar display,
  Content and actions, Appearance, and Status sections, with a note explaining
  that the settings apply to blocks and shortcodes using site settings.
* Added a live preview beside the settings that shows the Memml Calendar,
  Memml Events, or Memml Volunteers block with your choices before you save
  them, and a Reset section to defaults button for each preference section.
* Added Appearance presets: a custom accent color with automatically readable
  button text, a Light, Neutral, or Theme colors palette, comfortable or
  compact spacing, rounded or square corners, and wide or compact card images.
  The defaults keep the current look, and theme CSS still takes precedence.
* Events and volunteer opportunities that run across several days now show
  their first and last day, appear on every day they run in Month view, and
  stay in Upcoming with their actions until their last day has passed.
* Fixed Google Calendar links for multi-day all-day events, which ended one day
  too late.

= 0.4.7 =

* Pages that show a Memml calendar now work with LiteSpeed Cache: they are kept
  in the page cache no longer than the feed data they show stays fresh, and
  Clear cached data or saving Memml Calendar settings clears them. Previously a
  page cache could keep showing outdated events and volunteer opportunities for
  days.
* Added the `memml_page_cache_ttl` filter and the `memml_page_cache_limit` and
  `memml_page_cache_purge` actions for connecting other page caches.
* Corrected how quickly Memml changes appear: usually within ten to fifteen
  minutes, because Memml's servers can also keep a feed for up to five minutes.

= 0.4.6 =

* Added the Memml Committees block and `[memml_committees]` shortcode to
  display a board of directors or other public committee as a roster of
  seats, each with its current holder's name or a Vacant label. Committees
  with a charter show it beneath the committee name by default; both the
  charter and vacant seats can be hidden per block or with the
  `show_charter` / `show_vacant_seats` shortcode properties.

= 0.4.5 =

* Added RSVP actions from Memml's public events feed: an RSVP button with the
  organizer's capacity wording while RSVPs are open, a Full or RSVP closed
  status once they are not, and the RSVP deadline in the details pop-up.
* Added a View event page link to every event's Memml page, kept for past
  events so there is always a path to the full details. Both are site-wide
  action preferences with block overrides and `show_rsvp` / `show_event_page`
  shortcode properties, enabled by default.
* Polished the rows list style so items with different content share one
  shape: stacked when/where/cost lines, structured venues on one line,
  add-to-calendar links under the description, and a fixed-width right-hand
  column for the status badge, calls to action, and event page link.
* Cost now displays as a small tag, and the details pop-up separates its
  actions from the description.
* Cancelled events no longer show RSVP, registration, Join online, or volunteer
  actions.

= 0.4.4 =

* Added site-wide visitor-toolbar preferences, with inherited block controls,
  for the calendar, List/Month, and Upcoming/Past switchers and subscription row.
  Hidden switchers now keep their configured state fixed.
* Added site-wide and per-block visibility preferences for images, descriptions,
  item counts, details, venue and cost, volunteer availability, cancelled events,
  registration, Join online, volunteer signup, and add-to-calendar actions.
* Added matching advanced shortcode properties. Existing calendars keep all
  content, actions, and visitor controls enabled unless an administrator hides
  them, and explicit block or shortcode values continue to take precedence.

= 0.4.3 =

* Completed the site-wide display defaults with initial calendar, Upcoming or
  Past filter, and maximum list items. Blocks can use the site setting for every
  preference, while explicit block and shortcode values remain unchanged.
* Improved the spacing and wrapping of per-event Add to calendar links.

= 0.4.2 =

* Added Join online actions for current and upcoming events whose public feed
  supplies a meeting URL.
* Prevented registration, volunteer, online meeting, and add-to-calendar actions
  from appearing after an event or volunteer opportunity date.

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
