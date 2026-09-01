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

## Roadmap summary

| ID | Milestone | Status | Outcome |
| --- | --- | --- | --- |
| RM-001 | 0.5 Admin-first controls | **DONE** | Complete the site-wide display defaults and inheritance model. |
| RM-002 | 0.5 Admin-first controls | **DONE** | Let administrators choose which visitor controls are available. |
| RM-003 | 0.5 Admin-first controls | **DONE** | Add practical content and action visibility preferences. |
| RM-004 | 0.5 Admin-first controls | **NEXT** | Reorganize settings and add a live calendar preview. |
| RM-005 | 0.5 Admin-first controls | **PLANNED** | Add a small set of safe appearance presets. |
| RM-006 | 0.6 Reliability | **PLANNED** | Show clear feed health and targeted refresh controls. |
| RM-007 | 0.6 Reliability | **PLANNED** | Add useful, customizable empty states and calls to action. |
| RM-008 | 0.6 Reliability | **PLANNED** | Support delegated calendar management and improve onboarding. |
| RM-009 | 0.7 Visitor experience | **PLANNED** | Add a mobile-friendly chronological agenda view. |
| RM-010 | 0.7 Visitor experience | **PLANNED** | Add headings, introductions, and optional destination links. |
| RM-011 | 0.7 Visitor experience | **PLANNED** | Add simple search and high-value visitor filters. |
| RM-012 | 0.7 Visitor experience | **PLANNED** | Add appropriate Event structured data for search engines. |
| RM-013 | 0.7 Visitor experience | **PLANNED** | Reduce initial markup while preserving no-JavaScript navigation. |

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

**Status:** NEXT

Organize the admin experience into clear sections:

- Connection
- Calendar display
- Content and actions
- Appearance
- Status

Add a server-rendered preview for Events and Volunteers. Clearly explain that
changes affect blocks using site settings, and provide a reset-to-default action
for each preference section.

### RM-005 — Add safe appearance presets

**Status:** PLANNED

Provide a limited set of theme-friendly choices:

- Accent color
- Theme inherited, light, or neutral palette
- Comfortable or compact spacing
- Rounded or square cards
- Wide, compact, or hidden images

Continue to support CSS custom properties and normal theme overrides. Do not add
fine-grained typography or arbitrary CSS editors.

## 0.6 — Reliability and day-to-day administration

### RM-006 — Feed status and refresh tools

**Status:** PLANNED

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

## Deferred until demand is demonstrated

- Multiple Memml organizations on one WordPress site
- Importing or synchronizing Memml records into WordPress posts
- Detailed analytics dashboards; lightweight action hooks may be considered
  earlier when an integration needs them
- Administrator-configurable cache lifetimes and failure backoff
- Complex taxonomies and location management
- A full template builder or arbitrary CSS editor
