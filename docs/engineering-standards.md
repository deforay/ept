# Engineering Standards

ePT is a long-lived Zend Framework 1 application maintained by a small team and
deployed as a single central instance. That shapes what this document is: a bar
for changes, not a style guide. It records the handful of invariants that a
reader cannot infer from the code, because those are the ones that get broken.

## 1. The review pass

Every non-trivial change gets an adversarial read before it lands, against the
brief below.

**The review brief** — single source of truth, read verbatim from this file, so it cannot drift:
> "You are reviewing a change to ePT: a Zend Framework 1 proficiency-testing platform running as a single central instance, where administrators configure shipments and participating laboratories submit results that per-scheme algorithms evaluate into reports. Do not summarize the code. Find: (1) any admin grid or listing query that derives the data manager from the admin session directly instead of `Common::getScopedDmId()`, and any participant, shipment or result query that drops the active-record restriction; (2) any evaluation path that writes `final_result` as something other than 1/2/3/4 (Pass/Fail/Excluded/NotEvaluated), scores a non-responder as a failure without going through `Evaluation::excludeNonResponder()`, or branches on the report-layout template name where it should read `global_config.instance`; (3) a migration under `database/migrations/` that is not re-runnable — `migrate.php` re-runs the current version by design, so DDL needs `handle_idempotent_ddl` or name-based idempotence, and `PREPARE`/`EXECUTE` are silently dropped by the parser — or a schema change made by editing `sql/init.sql`, or a migration that fails to bump both `APP_VERSION` in `constants.php` and `system_config.app_version`; (4) output reaching the wrong escaping helper: `jsTranslate` for JavaScript context, `htmlTranslate` for HTML attributes, `safeTranslate` as the dispatcher, and any participant-supplied text interpolated into a `.phtml` unescaped; (5) a participant response write that does not check `isShipmentEditable`, or code reading the defunct `evaluation_status` / `response_after_evaluate` columns instead of the response deadline; (6) wiring that fails silently — a new `scheduled-jobs` script missing from `ALLOWED_JOB_SCRIPTS`, a mass-email action without an admin `confirm()`, or a per-participant feature added to only one of the three per-shipment listing pages (admin evaluate, reports distribution, reports finalize); (7) a change to report or chart generation verified only at component level, where the real failure mode is a malformed final PDF. Rank findings by severity. If you find nothing in a category, say 'clear' — don't pad."

**Where the second opinion matters most:** the per-scheme evaluation algorithms,
every migration, the scoping and non-responder helpers in
`application/services/`, participant response writes, and report and chart
generation. Routine CRUD and copy changes do not need it — don't ritualize this
into overhead on a team this size.

**What counts as a finding:** a defect with a failure scenario — concrete inputs
or state, and the wrong output or lost data that results. A trade-off this
codebase has already made deliberately is a rebuttal, not a fix; say so rather
than reporting it.

**Stopping rule:** a pass returning zero critical findings. Not zero findings —
zero critical. Lesser ones are fixed forward on `master` like ordinary work.

## 2. Invariants a reviewer needs told

These are listed here so there is one place to update when they change. The
brief above names them; this table says where each one is defined.

| Invariant | Where it lives |
| --- | --- |
| Data-manager scoping for admin grids | `Common::getScopedDmId()` in `application/services/Common.php` |
| Non-responders excluded from scoring | `Evaluation::excludeNonResponder()` in `application/services/Evaluation.php` |
| Response editability | `isShipmentEditable()` in `application/models/DbTable/ShipmentParticipantMap.php` |
| Escaping by output context | `library/Pt/Commons/TranslateUtility.php`, see [Translation](TranslationGuide.md) |
| Schema changes are migrations | `database/migrations/`; `sql/init.sql` is a seed, never edited to effect a change |
| Version sync | `APP_VERSION` in `constants.php` and `system_config.app_version`, checked by `bin/check-version-sync.php` |
| Job-queue whitelist | `ALLOWED_JOB_SCRIPTS` in `scheduled-jobs/execute-job-queue.php` |

## 3. Making a change

Read the architecture docs before changing an unfamiliar area. Understand the
existing pattern rather than adding a second one.

- Keep controllers thin. Put logic in `application/services/`.
- Reuse the helpers in `library/Pt/Commons/` and `application/services/` before writing new ones.
- Put every schema change in `database/migrations/`. Never edit `sql/init.sql` to effect a change.
- Update the documentation in the same change, not afterwards.

## 4. Documentation conventions

Existing pages do not all follow these. Apply them to anything you write or
substantially edit, rather than reformatting pages you are not otherwise
touching.

| Rule | Detail |
| --- | --- |
| Page type | One purpose per page. A how-to guide gives steps. A reference lists options. Do not mix the two on one page. |
| Headings | Sentence case. Write "Supported test schemes", not "Supported Test Schemes". Keep product names and acronyms capitalised. |
| Sentences | Short and direct. Present tense. Start instructions with the verb. |
| New filenames | Kebab-case, for example `backup-and-migration.md`. `ARCHITECTURE.md` and `SchemeArchitecture.md` keep their names because renaming them breaks published URLs. |
| Callouts | Use `> **Title:**` blockquotes. MkDocs admonitions (`!!! warning`) render as literal text on GitHub, and these pages are read there as well as on the site. |
| Raw HTML | Avoid it. Markdown inside an HTML block does not render on GitHub, so Material card grids break the GitHub view. |
| Adding a page | Add it to the `nav` in `mkdocs.yml` and to the "Where to start" table in [the documentation index](README.md). A page missing from either one is hard to find. |
| Scheme names | Match the `scheme_list` table. The [supported test schemes](README.md#supported-test-schemes) table is the reference. |

Run `mkdocs build --strict` before pushing. The deploy workflow uses it, so a
broken link or a bad reference fails the build rather than shipping.
