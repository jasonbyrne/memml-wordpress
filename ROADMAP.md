# Memml Calendar Roadmap

This is the source of truth for planned product work. It prioritizes the needs
of small nonprofit organizations: simple administration, dependable public
calendars, accessible visitor experiences, and minimal technical maintenance.

## How to use this roadmap

- The item marked **NEXT** is the default item to implement when a request says
  "work on the next roadmap item."
- Work items in each milestone are ordered by priority and dependency.
- Mark an item **DONE** only when its implementation, relevant tests, and
  documentation are complete.
- When an item is completed, add its completion date and move **NEXT** to the
  first remaining item.
- If priorities change, update this document before beginning the replacement
  work so there is still exactly one **NEXT** item.

Status values: **NEXT**, **PLANNED**, **DONE**, and **DEFERRED**.

## Product principles

1. **Admin first.** A nonprofit should configure the plugin once under
   Settings, add a Memml block or a plain shortcode, and get the intended
   result without learning shortcode properties.
2. **Useful defaults.** Blocks inherit site settings unless an editor makes an
   intentional exception. Existing explicit block and shortcode values remain
   backward compatible.
3. **Progressive disclosure.** Common choices stay simple; technical and
   unusual options stay in an Advanced section.
4. **Trust and accessibility.** Feed health, stale data, errors, and visitor
   controls should be understandable and accessible.
5. **Low maintenance.** Prefer capabilities that do not require a nonprofit to
   duplicate Memml data or regularly repair its WordPress configuration.

## Configuration model

Public display configuration follows this precedence:

1. Valid visitor state in a shared URL
2. Explicit block or shortcode override
3. Feed-specific admin preference, when available
4. Site-wide admin preference
5. Plugin default

New blocks should use site settings by default. Shortcode properties remain
supported as advanced overrides, but the primary documentation and onboarding
should show shortcodes without properties.

## Upstream Memml changes to know about

### Memml public page addresses are moving (planned 2026-11-08, not yet shipped)

Memml plans to move every organization's public pages under the organization
key, alongside a new hub page at `https://memml.com/<organization_key>`. This is
slice 7 of Memml's self-service home (Memml roadmap M12 phase 4). It has not
shipped; today's addresses remain live until it does.

| Today | Planned |
| --- | --- |
| `/calendar/<key>`, `/calendar/<key>/<event>` | `/<key>/events`, `/<key>/events/<event>` |
| `/calendar/<key>/<event>/rsvp` | under `/<key>/events/<event>` |
| `/volunteer/<key>/...` | `/<key>/volunteer/...` |
| `/donate/<key>` | `/<key>/donate` |
| `/portal/<key>` (membership levels and sign-up) | `/<key>/join` |
| `/portal/<key>` (a person's own records) | `/<key>/portal` |

What the plugin needs to know:

- **No code change is expected.** The plugin renders the page URLs each feed
  supplies and never builds Memml page URLs itself; keep it that way. When the
  move ships, the `url`, `calendar`, `volunteerSignupUrl`, RSVP, and similar
  fields switch to the new addresses in the same Memml release.
- **Old addresses redirect permanently,** so links in cached feed data and in
  pages already served keep working.
- **Feed endpoints do not move.** `/api/public/v1/<key>/...` stays where it is,
  so the connection test and every feed request are unaffected.
- **When it ships:** update any documentation or examples that show the old
  page addresses, and re-run the smoke test against a live organization to
  confirm the new links. Test fixtures that hard-code old-style URLs (for
  example the RSVP link in `tests/wp-env-smoke.php`) remain valid as fixtures
  but may be refreshed to the new shape.
- Memml's planned new feeds for the 0.8 milestone are listed in Memml's
  `docs/features/public-feeds.md` under "Planned with the self-service home".

## Roadmap summary

| ID | Milestone | Status | Outcome |
| --- | --- | --- | --- |
| RM-001 | 0.5 Admin-first controls | **DONE** | Complete the site-wide display defaults and inheritance model. |
| RM-002 | 0.5 Admin-first controls | **DONE** | Let administrators choose which visitor controls are available. |
| RM-003 | 0.5 Admin-first controls | **DONE** | Add practical content and action visibility preferences. |
| RM-004 | 0.5 Admin-first controls | **DONE** | Reorganize settings and add a live calendar preview. |
| RM-005 | 0.5 Admin-first controls | **DONE** | Add a small set of safe appearance presets. |
| RM-015 | 0.6 Reliability | **DONE** | Keep page caches from serving outdated Memml content. |
| RM-016 | 0.6 Reliability | **DONE** | Show multi-day events across every day they run. |
| RM-006 | 0.6 Reliability | **NEXT** | Show clear feed health and targeted refresh controls. |
| RM-007 | 0.6 Reliability | **PLANNED** | Add useful, customizable empty states and calls to action. |
| RM-008 | 0.6 Reliability | **PLANNED** | Support delegated calendar management and improve onboarding. |
| RM-009 | 0.7 Visitor experience | **PLANNED** | Add a mobile-friendly chronological agenda view. |
| RM-010 | 0.7 Visitor experience | **PLANNED** | Add headings, introductions, and optional destination links. |
| RM-011 | 0.7 Visitor experience | **PLANNED** | Add simple search and high-value visitor filters. |
| RM-012 | 0.7 Visitor experience | **PLANNED** | Add appropriate Event structured data for search engines. |
| RM-013 | 0.7 Visitor experience | **PLANNED** | Reduce initial markup while preserving no-JavaScript navigation. |
| RM-014 | 0.7 Visitor experience | **DONE** | Show Memml RSVP actions and keep a link to the Memml event page. |
| RM-017 | 0.8 Getting involved | **PLANNED** | Add a Get involved block of the organization's Memml destinations. |
| RM-018 | 0.8 Getting involved | **PLANNED** | Add an Our sponsors block that recognizes paid sponsors with their logos. |
| RM-019 | 0.8 Getting involved | **PLANNED** | Add a Join block that lists membership levels with join links. |

## 0.5 — Admin-first controls

### RM-001 — Complete site-wide display defaults

**Status:** DONE

**Completed:** 2026-09-01

Add administrator preferences for the choices that currently require block or
shortcode configuration:

- Initially selected calendar: Events or Volunteer Opportunities
- Initially selected list filter: Upcoming or Past
- Maximum items in list view, where `0` means all items
- Existing initial view, list style, and subscription preferences remain in the
  same unified display-default model
- Optional Events and Volunteers overrides should be presented only if they can
  remain easy to understand; otherwise defer them until real demand is clear

Blocks must offer **Use site setting** for every inherited preference. Existing
blocks and shortcodes with explicit values must continue to render unchanged.

**Completion criteria:** Sanitization, defaults, rendering resolution, block
controls, backward compatibility tests, README usage, and WordPress readme usage
are all updated.

### RM-002 — Control the visitor toolbar

**Status:** DONE

**Completed:** 2026-09-01

Add admin defaults, with block overrides, for:

- List/Month switcher
- Upcoming/Past switcher
- Events/Volunteer Opportunities switcher on the combined calendar
- Subscription row

When a switcher is disabled, render only the administrator-selected state where
practical. Preserve accessible links and shareable state for enabled controls.

### RM-003 — Control visible content and actions

**Status:** DONE

**Completed:** 2026-09-01

Start with the controls most likely to simplify a small organization's calendar:

- Images
- Descriptions
- Item count
- Details dialog
- Venue and cost
- Volunteer availability
- Cancelled events
- Registration, Join online, volunteer signup, and add-to-calendar actions

Use sensible enabled defaults and group related controls. Avoid turning the
settings screen into a template builder.

### RM-004 — Improve settings information architecture and preview

**Status:** DONE

**Completed:** 2026-11-08

Organize the admin experience into clear sections:

- Connection: organization key, Test connection, and the Advanced base URL
- Calendar display: initial calendar, view, list filter, list limit, visitor
  toolbar, and subscription row
- Content and actions: the existing content and action preferences
- Appearance: list style, with a note that calendars inherit the theme; the
  presets themselves remain RM-005
- Status: cache explanation, the feeds this site reads, and Clear cached data;
  per-feed health cards remain RM-006

Add a server-rendered preview beside the settings. It previews the Memml
Calendar, Memml Events, or Memml Volunteers block with the saved connection and
the display, content, action, and appearance choices currently in the form,
updating before they are saved. It renders in an isolated frame with the
calendar's own assets and the theme's global styles, so preview navigation
never changes the settings page URL.

Clearly explain that changes affect blocks and shortcodes using site settings,
while explicit block or shortcode choices keep their values. Each preference
section has a Reset section to defaults action that restores that section's
defaults in the form and updates the preview; Save Changes applies them.

**Completion criteria:** Sectioned settings page, preview endpoint restricted to
administrators with a nonce, preview options that never persist, per-section
reset, unit tests for preview option resolution and section defaults, README,
readme.txt, and settings-page help are updated. Saved option names and values
remain unchanged.

### RM-005 — Add safe appearance presets

**Status:** DONE

**Completed:** 2026-11-08

Provide a limited set of theme-friendly choices in the Appearance settings
section:

- Accent color: the default blue or a custom color, with readable button text
  chosen automatically
- Palette: Light (the current look), Neutral (soft gray surfaces), or Theme
  colors (text and borders follow the theme, with lightly tinted surfaces and a
  dialog that uses the theme's base and contrast colors when available)
- Comfortable or compact spacing
- Rounded or square corners
- Wide or compact card images; hiding images remains the Content and actions
  Images preference rather than a second control

The defaults reproduce the current appearance and markup exactly. Presets set
the existing CSS custom properties at zero specificity, so theme rules and
custom-property overrides continue to win. They apply to the calendar, events,
volunteer, and committee blocks and the settings preview. Presets are
site-wide; per-block appearance overrides are deferred until demand is clear.
Do not add fine-grained typography or arbitrary CSS editors.

**Completion criteria:** Sanitized settings with defaults, Appearance section
controls included in Reset section to defaults and the live preview, renderer
classes and accent CSS, unit tests for sanitization, classes, and accent
contrast, README, and readme.txt are updated.

## 0.6 — Reliability and day-to-day administration

### RM-015 — Page-cache compatibility

**Status:** DONE

**Completed:** 2026-09-27

Full-page caches can keep serving a rendered Memml calendar long after the feed
changes. LiteSpeed Cache's default public lifetime is a week, which hid new
volunteer opportunities on a live site for days.

- A page that renders a Memml block or shortcode asks LiteSpeed Cache to keep
  it no longer than the feed data it shows stays fresh, and much less while
  Memml is unavailable or stale data is shown. It never lengthens a shorter
  lifetime the site has configured.
- Those pages carry a `memml` LiteSpeed cache tag. Clear cached data and any
  change to Memml Calendar settings purge the tagged pages.
- A `memml_page_cache_ttl` filter adjusts or disables the requested lifetime.
  `memml_page_cache_limit` and `memml_page_cache_purge` actions let sites
  connect other page caches.

**Completion criteria:** Lifetime calculation, LiteSpeed tagging, purge on
clear and settings changes, unit tests, README, readme.txt, and settings-page
help are updated.

### RM-016 — Multi-day events

**Status:** DONE

**Completed:** 2026-11-08

Memml events and volunteer opportunities can run across several days, but the
plugin treated every item as happening only on its start day. Match memml.com's
own date rules, where an all-day item's end is the midnight after its last day:

- Date labels show the first and last day of a multi-day item, and a timed
  item shows its start and end day with their times.
- Month view places a multi-day item on every day it runs, across month
  boundaries, with continuation days showing All day or the end time.
- An item stays in Upcoming, and keeps its timely actions, until its last day
  has passed in the organization timezone.
- The Google add-to-calendar link uses organization-local dates and the feed's
  exclusive all-day end, so multi-day all-day events are not one day too long.

**Completion criteria:** Renderer changes, unit tests for labels, month
placement, Upcoming/Past, and Google links, README, and readme.txt are updated.

### RM-006 — Feed status and refresh tools

**Status:** NEXT

Show separate Events and Volunteers status cards with organization, timezone,
item count, last successful refresh, fresh/cached/stale state, and the latest
recoverable error. Add per-feed refresh and Refresh all actions. Use plain
language rather than cache implementation terminology.

### RM-007 — Empty states and calls to action

**Status:** PLANNED

For Events and Volunteers, allow the administrator to select:

- Standard message
- Custom message
- Hide the empty calendar
- Custom message with an optional link

Keep safe defaults and distinguish an empty feed from a temporarily unavailable
feed.

### RM-008 — Delegated management and onboarding

**Status:** PLANNED

Introduce a dedicated `manage_memml_calendar` capability that can be assigned to
an Events or Communications Manager without granting all administrator powers.
After a successful connection, show the organization name, timezone, available
feeds, a preview action, and copyable basic shortcodes without properties.

## 0.7 — Visitor usefulness and reach

### RM-009 — Agenda view

**Status:** PLANNED

Add a chronological agenda layout optimized for phones and modest event volumes.
It must support the existing date rules, actions, accessible controls, block
preview, and shared URLs.

### RM-010 — Calendar context and destination links

**Status:** PLANNED

Allow an optional inherited heading, short introduction, and destination link
such as View all events or Learn about volunteering. Maintain appropriate
heading hierarchy when embedded in a page.

### RM-011 — Search and simple filters

**Status:** PLANNED

Begin with feed data that already exists:

- Keyword search across title and description
- Online or in-person events
- Volunteer opportunities that still need people
- Date range

Do not add category filtering until the Memml public feeds provide a stable
category contract.

### RM-012 — Event structured data

**Status:** PLANNED

Emit valid Event structured data when the feed contains enough information.
Handle cancelled and postponed states correctly, avoid duplicating structured
data for the same event on a page, and add validation-focused tests.

### RM-013 — Reduce initial calendar markup

**Status:** PLANNED

Avoid rendering every feed, period, and layout into hidden initial markup. Keep
the current real query-string links as the no-JavaScript path, then progressively
enhance visitor changes. Measure the markup reduction and preserve accessibility,
shareable URLs, and stale-cache behavior.

### RM-014 — RSVP actions and Memml event page links

**Status:** DONE

**Completed:** 2026-09-05

Render the `rsvp` object Memml's public events feed supplies for organizer-
advertised RSVPs: a primary RSVP action with the organizer's capacity wording
while RSVPs are open, and a Full or RSVP closed status once they are not. RSVP
can coexist with registration, Join online, and volunteer signup actions.

Always provide a path to the Memml event page. A View event page link appears
on cards, rows, month entries, and the details dialog, and is kept for past
events after the timely actions expire.

Both are site-wide action preferences with block overrides and `show_rsvp` /
`show_event_page` shortcode properties, enabled by default. The details dialog
names an explicit RSVP deadline. Cancelled events omit sign-up actions.

Completed alongside: a rows-layout and dialog consistency pass — stacked
when/where/cost lines, add-to-calendar links under the description, a
fixed-width right-aligned column reserved for calls to action and the event
page link, cost shown as a tag, and a rule separating dialog actions — so items
with different content share one shape.

## 0.8 — Getting involved

Planned 2026-11-08 alongside Memml's self-service home (Memml roadmap M12
phase 4). The organization's own website stays its home page; these blocks are
how the things a visitor can do with the organization (volunteer, give, join,
see their own records) reach it. Each block depends on a Memml public feed that
does not exist yet and is recorded in Memml's `docs/features/public-feeds.md`
under "Planned with the self-service home". None of these items can start until
its feed ships. As with existing feeds, the plugin never builds Memml page URLs
itself; it uses the URLs each feed supplies, so Memml can move its public pages
(planned: under `memml.com/<organization_key>`) without breaking a site.

### RM-017 — Get involved block

**Status:** PLANNED

**Depends on:** Memml `organization.json`.

Render the organization's public Memml destinations as a row or grid of
buttons, each with its label and short line from the feed: Events, Volunteer,
Donate, Join, My portal, and the Memml hub page. Show only the destinations the
feed lists, since Memml lists only what the organization actually uses. My
portal is a plain link; signing in always happens on Memml, never in a form on
the WordPress site. Administrators can hide individual destinations site-wide,
with block overrides. Consider using the same feed for RM-010's optional
destination links.

**Completion criteria:** block and shortcode, site-wide visibility settings
with block overrides, an accessible empty state when the feed lists nothing,
caching consistent with the existing feeds, tests, README and readme.txt usage.

### RM-018 — Our sponsors block

**Status:** PLANNED

**Depends on:** Memml `sponsors.json`.

Show paid sponsors whose sponsorship includes a "Logo on website" benefit,
grouped by project and ordered by level, with the logo the sponsor chose, their
name, and a link to their site. Text fallback when a sponsor has no logo. An
administrator can limit the block to one project (for example, a single event's
sponsors). Rendering this block is how an organization delivers the "Logo on
website" benefit, so it must never show a sponsor the feed does not list.

**Completion criteria:** block and shortcode, project filter, accessible logo
alt text from the sponsor name, image sizing that keeps mixed logos even,
tests, README and readme.txt usage.

### RM-019 — Join block

**Status:** PLANNED

**Depends on:** Memml `membership-levels.json`.

List the organization's public membership levels with price, term, a short
benefit summary, and a Join button to the level's Memml sign-up URL. The block
renders nothing (and says why to editors only) when the organization is not
advertising joining.

**Completion criteria:** block and shortcode, card and list layouts consistent
with existing list styles, editor-only notice when membership is not
advertised, tests, README and readme.txt usage.

## Deferred until demand is demonstrated

- Multiple Memml organizations on one WordPress site
- Importing or synchronizing Memml records into WordPress posts
- Detailed analytics dashboards; lightweight action hooks may be considered
  earlier when an integration needs them
- Administrator-configurable cache lifetimes and failure backoff
- Per-block or per-shortcode appearance overrides; the site-wide Appearance
  presets and theme CSS cover them in the meantime
- Built-in integrations for page caches other than LiteSpeed Cache, such as
  WP Rocket, W3 Total Cache, or host-level caches; the `memml_page_cache_limit`
  and `memml_page_cache_purge` actions cover them in the meantime
- Complex taxonomies and location management
- A full template builder or arbitrary CSS editor
