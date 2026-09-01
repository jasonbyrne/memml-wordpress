# Repository Instructions

These instructions apply to every task in this repository.

## Roadmap workflow

- Read `ROADMAP.md` before planning feature work.
- Treat `ROADMAP.md` as the source of truth for planned product work.
- When the user asks for the "next roadmap item," implement the single item
  marked **NEXT** unless the user explicitly selects another item.
- Before beginning roadmap work, verify that there is exactly one **NEXT** item
  and that its completion criteria are sufficiently clear. Refine the roadmap
  first if the scope has materially changed.
- Do not mark an item **DONE** until the implementation, relevant automated
  tests, and documentation are complete.
- When completing an item, add its completion date, change its status to
  **DONE**, and move **NEXT** to the first remaining item. Keep exactly one
  **NEXT** item while unfinished roadmap work remains.
- If implementation reveals new follow-up work, add or amend a stable roadmap
  item rather than leaving the requirement only in chat or a code comment.

## Documentation maintenance

Documentation is part of the implementation, not a separate optional task.

- Update `README.md` when installation, development workflow, architecture,
  configuration, blocks, shortcodes, theming, or user-visible behavior changes.
- Update `readme.txt` when WordPress administrator or visitor behavior,
  requirements, installation, FAQs, blocks, shortcodes, or configuration changes.
- Keep overlapping user-facing claims in `README.md` and `readme.txt` consistent.
- Update `ROADMAP.md` whenever roadmap status, priority, scope, or sequencing
  changes.
- Update inline help, block inspector help, and settings-page descriptions when
  the corresponding behavior changes.
- Add changelog entries and bump versions only as part of an explicitly requested
  release or deploy-preparation task; ordinary feature work should leave release
  versioning for the release workflow.
- If a change genuinely needs no documentation update, state that conclusion in
  the final handoff rather than silently skipping the documentation review.

## Verification and handoff

- Run checks proportional to the change and report what was run.
- For roadmap work, verify both default behavior and backward compatibility for
  explicit block and shortcode attributes.
- In the final handoff, name the roadmap item worked, summarize documentation
  changes, and identify the new **NEXT** item when the current item was completed.
- Preserve unrelated working-tree changes and never include them in roadmap
  status updates or completion claims.

## Shell paths

- This machine uses `zsh`. Quote any path containing brackets or parentheses.
- Prefer `rg --files | rg 'pattern'` or quoted `rg -- 'text' 'path'` forms.

