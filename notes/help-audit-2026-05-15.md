# In-app help audit (admin)

Audit date: 2026-05-15. Status reviewed: 2026-08-06.

This is a point-in-time engineering report, not documentation. It lives outside
`docs/` so it does not build into the public site at deforay.github.io/ept.

Reviewed: the slug map in `application/layouts/scripts/admin.phtml`, the help
drawer at `application/views/partials/help-drawer.phtml`, the drawer JS at
`public/js/helpDrawer.js`, and the help docs under `docs/help/admin/en_US/`.

## Status at 2026-08-06

| Finding | Status |
| --- | --- |
| A. Broken slug map | Resolved |
| B. Uncovered controllers | Open |
| C. Discoverability gaps | Partly resolved |
| D. Content quality | Partly resolved |
| E. Drawer instrumentation | Open |

## A. Broken slug map (resolved)

The original audit found 12 ghost keys and 5 mapped slugs with no `.md` file.
Commit `e7aa738` fixed this. Every slug the map resolves to now has a matching
file, and the `shipments/*` keys became `shipment/*`.

Two of the original 12 ghost keys were never broken. `distribution/index` and
`finalize/index` are real controllers in the `reports` module. The audit only
listed `application/modules/admin/controllers`, so it missed them. `admin.phtml`
is the shared layout for both modules.

The audit also did not account for the `scheme-config` branch at
`admin.phtml:145`. That branch derives the slug from the `scheme` request
parameter, so `dts-settings.md`, `tb-settings.md`, and `vl-settings.md` are
reachable even though no literal map key points at them.

## B. Uncovered controllers (open)

34 of 50 admin controllers have no entry in the slug map. The drawer falls back
to the topic index on those pages:

```text
alerts                    error                     participant-messages
announcement              feedback-responses        partners
api-history               help                      profile
audit-log                 home-section-links        recency-settings
certificate-batches       impersonate               sample-not-tested-reasons
certificate-templates     job-tracking              scheme-config
contact-us                log-viewer                scheme-re-evaluate
covid19-gene-type         login                     schemes
covid19-settings          mail-template             spotlight
custom-fields             test-platform
custom-test
```

Several of these need no help topic. `error`, `login`, `impersonate`, `profile`,
and `spotlight` are not workflow screens. `scheme-config` resolves through its
own branch. The real gaps are `schemes`, `custom-test`, `custom-fields`,
`recency-settings`, `covid19-settings`, and `mail-template`.

## C. Discoverability gaps (partly resolved)

Guide mode now exists. `helpDrawer.js` tracks `activeGuide` and `guideStep` in
`sessionStorage`, and guides under `docs/help/admin/en_US/guides/` render one
step at a time with a "You're here" marker. Two guides are written:
`add-a-shipment.md` and `enroll-participants.md`.

Still open:

- No inline contextual help. Confusing terms in the UI carry no definition.
  The terms worth covering are "Shipment", "PT Survey", "Scheme", "Enrollment".
- No first-time nudge. The drawer never opens on its own.
- Empty states do not point at the next action. A PT Survey with no shipments
  shows an empty table.

## D. Content quality (partly resolved)

`evaluate-shipment.md` is still the longest topic. The sample exclusion, manual
limits, and VL range sections could each become a linked sub-topic.

The three VL topics (`vl-assay.md`, `vl-assay-add.md`, `vl-assay-edit.md`) are
still the thinnest in the set at roughly 34 to 42 lines each.

## E. Drawer instrumentation (open)

The drawer sends no telemetry. There is no record of which topics are opened or
how long anyone reads them. Without this, the next audit is guesswork again.

## Recommendations, ranked by impact for effort

1. Write the participant help topics. `layout.phtml` maps 9 participant menu
   items to 7 slugs, and `docs/help/participant/` does not exist. Every
   participant help lookup falls through to the topic index. This is the
   largest remaining gap.
2. Add inline contextual help next to jargon in the UI. Hovering shows a
   one-line definition. Clicking opens the full topic in the drawer.
3. Map the 6 real gaps listed in section B.
4. Instrument the drawer. Log the opened slug to `audit_log`.
5. Replace empty states with action-oriented panels.
6. Split `evaluate-shipment.md` and expand the three VL topics.

## Appendix: how to re-run this audit

```bash
ls application/modules/admin/controllers
ls application/modules/reports/controllers
awk '/\$helpSlugMap *= *\[/,/^\];/' application/layouts/scripts/admin.phtml
ls docs/help/admin/en_US/
```
