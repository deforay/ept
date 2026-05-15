# In-App Help Audit (Admin)

Date: 2026-05-15

Reviewed: slug map in `application/layouts/scripts/admin.phtml` (lines 612–667), help drawer at `application/views/partials/help-drawer.phtml`, drawer JS at `public/js/helpDrawer.js`, and the 32 help docs under `docs/help/admin/en_US/`.

## Summary

- **Coverage is much worse than it looks.** 12 of the 47 mapped pages point at controllers or actions that do not exist. The user lands on `/admin/shipment/...`, but the slug map only has `shipments/*` (plural) — so the drawer silently falls back to the generic topic index on every shipment page.
- **Content is in good shape.** 32 docs, ~14.7k words total, avg 459 w/doc. Reads cleanly. Only 1 doc over 800 words; only 2 under 150. Jargon use is low.
- **Discoverability is the biggest gap.** Help is purely opt-in via a small `?` icon in the top nav (or Ctrl+/). There is no first-time nudge, no inline contextual help, no on-page lifecycle ribbon. A user who doesn't know they're stuck won't open the drawer.

## Findings

### A. Broken slug map entries (high severity, easy fix)

**12 ghost keys — point at controllers/actions that don't exist:**

| Map key | Why it's broken |
|---|---|
| `index/dashboard` | `IndexController` has no `dashboardAction()` (only `indexAction`, `getSchemeParticipants`, `loadCharts`, `eptOverview`) |
| `participants/manage` | No `manageAction` on `ParticipantsController` |
| `shipments/index` | Controller is `ShipmentController` (singular), not `shipments` |
| `shipments/add` | same |
| `shipments/edit` | same |
| `shipments/manage` | same |
| `distributions/manage` | No `manageAction` on `DistributionsController` |
| `reports/index` | No `ReportsController` in admin module |
| `distribution/index` | No `DistributionController` (singular) |
| `finalize/index` | No `FinalizeController` |
| `schemes/index` | No `SchemesController` |
| `schemes/edit` | same |

**5 mapped slugs that have no `.md` file:**

- `dashboard.md`
- `reports.md`
- `schemes.md`
- `shipments.md`
- `users.md`

These two issues compound: even when the URL matches a slug, the doc may not exist, so the drawer opens the index instead. Net effect — entire workflows have no functional in-page help.

### B. Uncovered controllers (22)

The 22 admin controllers below have **zero** entries in the slug map. The drawer always falls back to the topic index on these pages:

```
alerts                       custom-test
announcement                 feedback-responses
api-history                  home-section-links
audit-log                    job-tracking
certificate-batches          log-viewer
certificate-templates        mail-template
contact-us                   participant-messages
covid19-gene-type            partners
covid19-settings             recency-settings
custom-fields                sample-not-tested-reasons
shipment ⚠                   test-platform
```

The most painful one is `shipment` — it is the screen at the heart of the workflow that triggered this audit. After the user clicks "Add and continue to Shipment", they land on `/admin/shipment/add` and the help drawer has nothing for them. This is almost certainly why the user got stuck.

### C. Discoverability gaps (highest leverage to fix)

- Trigger is a small `?` icon in the top nav, with no label and no first-time nudge. Non-technical users won't notice it.
- No localStorage / onboarding logic — the drawer never auto-opens on a user's first visit to a page.
- No inline contextual help (no ⓘ tooltips next to confusing terms like "Shipment", "Distribution", "Scheme").
- No lifecycle ribbon or progress indicator. The user has no visual cue that creating a survey is step 1 of 5+.
- Empty states do not push toward help. A survey with no shipments shows an empty table, not a clear "next: add a shipment" call.

### D. Content quality (low priority)

Content is generally clear and well-structured. Minor cleanup items:

- `evaluate-shipment.md` is the longest (854 words). A few sections (sample exclusion, manual limits, VL range) could be split into linked sub-topics.
- `vl-assay-add.md`, `vl-assay-edit.md`, `vl-assay.md` are 113–154 words each — likely too thin to be useful when something actually goes wrong. Consider merging or expanding.
- "Modal", "mapping", and "action" appear often. Domain-OK, but worth a final pass to replace with plainer wording where possible (e.g., "pop-up" instead of "modal").

### E. Drawer UX (functional, minor)

- Trigger label says "Help (Ctrl+/)". Good — both modalities advertised.
- Search box and topic index fall back work as designed.
- No "Was this helpful? Y/N" feedback widget — there is no signal for which docs are actually useful.
- No analytics: no `console.log` / no fetch to a telemetry endpoint when the drawer opens. **You are flying blind on whether help is used at all.**

## Recommendations, ranked by impact-for-effort

1. **Fix the broken slug map.** Rename `shipments/*` → `shipment/*`, drop ghost keys, add real entries for `shipment/add`, `shipment/edit`, `shipment/manage-enroll`, `shipment/view-enrollments`, `shipment/ship-it`. Write the missing 3 docs (`shipment`, `shipment-add`, `shipment-ship-it`). This alone restores help for the most-used screens. *Effort: ~half a day. Risk: zero.*
2. **Add inline contextual help (ⓘ next to jargon terms in the UI).** Specifically: "Shipment", "Distribution", "Scheme", "Enrollment". Hover/tap shows a 1-line definition. Reuses the help-drawer URL on click for the full doc. *Effort: ~1 day to wire the component, then incremental as labels are touched.*
3. **Add a lifecycle ribbon on PT Survey and Shipment pages.** "Step 2 of 5 — Add a shipment to survey `DBS-2026-Q1`" with a "Next: enroll participants" link. Directly addresses the incident that triggered this audit. *Effort: 1–2 days.*
4. **First-time auto-coach.** On a user's first visit to PT Survey list or Shipment add, briefly highlight the primary CTA with a dismissible callout. localStorage keyed per page. *Effort: 1 day, reuses drawer copy.*
5. **Instrument the drawer.** Log `open`, `slug`, dwell-time to a lightweight endpoint or even just `audit_log`. Without this, future audits are guesswork. *Effort: half a day.*
6. **Replace empty states with action-oriented panels.** Survey detail with no shipments → "This survey has no shipments yet. A shipment is the actual panel of samples you'll send. → Add the first shipment." *Effort: 1 day, view-by-view.*
7. **Content polish pass on the 3 anemic VL docs and the long evaluate-shipment.md.** *Effort: half a day.*

If only one ships before the next training round: **#1 (fix the slug map)**. It is pure debt and is silently blocking the exact users this audit is for.

## Appendix — repro

To reproduce these findings:

```bash
# Run the audit script (inline in the audit chat) against:
ls application/modules/admin/controllers
grep -n "helpSlugMap" application/layouts/scripts/admin.phtml
ls docs/help/admin/en_US/
```

47 mapped pages, 32 docs, 5 broken slug → doc links, 12 ghost keys, 22 uncovered controllers, 0 orphan docs.
