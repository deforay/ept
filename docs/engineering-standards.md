# Engineering Standards

ePT is a long-lived Zend Framework 1 application maintained by a small team and
deployed as a single central instance. That shapes what this document is: a bar
for changes, not a style guide. It records the handful of invariants that a
reader cannot infer from the code, because those are the ones that get broken.

## 1. The review pass

Every non-trivial change gets an adversarial read before it lands, against the
brief below. Run it before pushing, not after somebody asks:

```bash
bin/dev/review                  # the working branch against master
bin/dev/review <commit-sha>     # one commit
bin/dev/review --uncommitted    # the working tree, before committing
bin/dev/review --since-last     # every commit not yet recorded as reviewed
bin/dev/review --pending        # list commits not yet recorded as reviewed
bin/dev/review --mark [sha]     # record a commit (default HEAD) as reviewed
bin/dev/review --verify <...>   # add a pass that tries to refute the first one's findings
```

`--verify` exists because one pass that both finds and reports has nothing
pushing back on it, and that is where the false positives are. The second pass
is given the findings and one job — break them — and reports only what
survives, with the file and line that settles each. It doubles the cost of a
run, so it is opt-in; `REVIEW_VERIFY=1` in `.env` makes it the default.

The ledger behind `--since-last`, `--pending` and `--mark` lives in
`$(git rev-parse --git-common-dir)/reviewed-commits.txt`: per clone, shared by
its worktrees, never committed. `REVIEW_LEDGER` in `.env` moves it. A commit is
recorded only after every pass of its review finished; a reviewer that fails,
or exits without printing a review, records nothing.

The reviewing CLI is named by `REVIEW_AGENT` and `REVIEW_AGENT_ARGS`, from the
shell or the untracked `.env` (see `.env.example`), so no tool name enters the
repository.

**The review brief** — single source of truth, read verbatim from this file, so it cannot drift:
> "You are reviewing a change to ePT: a Zend Framework 1 proficiency-testing platform running as a single central instance, where administrators configure shipments and participating laboratories submit results that per-scheme algorithms evaluate into reports. Do not summarize the code. Find: (1) any admin grid or listing query that derives the data manager from the admin session directly instead of `Common::getScopedDmId()`, and any participant, shipment or result query that drops the active-record restriction; (2) any evaluation path that writes `final_result` as something other than 1/2/3/4 (Pass/Fail/Excluded/NotEvaluated), scores a non-responder as a failure without going through `Evaluation::excludeNonResponder()`, or branches on the report-layout template name where it should read `global_config.instance`; (3) a migration under `database/migrations/` that is not re-runnable — `migrate.php` re-runs the current version by design, so DDL needs `handle_idempotent_ddl` or name-based idempotence, and `PREPARE`/`EXECUTE` are silently dropped by the parser — or a schema change made by editing `sql/init.sql`, or a migration that fails to bump both `APP_VERSION` in `constants.php` and `system_config.app_version`; (4) output reaching the wrong escaping helper: `jsTranslate` for JavaScript context, `htmlTranslate` for HTML attributes, `safeTranslate` as the dispatcher, and any participant-supplied text interpolated into a `.phtml` unescaped; (5) a participant response write that does not check `isShipmentEditable`, or code reading the defunct `evaluation_status` / `response_after_evaluate` columns instead of the response deadline; (6) wiring that fails silently — a new `scheduled-jobs` script missing from `ALLOWED_JOB_SCRIPTS`, a mass-email action without an admin `confirm()`, or a per-participant feature added to only one of the three per-shipment listing pages (admin evaluate, reports distribution, reports finalize); (7) a change to report or chart generation verified only at component level, where the real failure mode is a malformed final PDF; (8) a defect fixed in one copy of code that has near-identical siblings — one scheme's `response.phtml`, one scheme's response save in `application/services/Shipments.php`, or one layout under `scheduled-jobs/report-layouts/` — while the other copies keep it; (9) tests or test-harness expectations that assert the happy path but would still pass if the invariant they guard were deleted. Carry the evidence: every claim about code names the file and line and quotes the line it rests on. If you cannot find the line that proves a claim, report the finding as UNVERIFIED and say what you looked for — that is a third outcome alongside a finding and a clear, and it is always preferable to asserting on thin evidence. When a hand-maintained list is missing an entry, trace the whole path and report every entry it is missing, not the first. If the commit message or a comment already addresses a concern, say why that reasoning is insufficient rather than restating the concern. Report a defect outside the diff separately, not ranked among the diff's findings. Rank findings by consequence, not by feel: P1 is wrong scores or reports reaching participants, lost participant or shipment data, or an instance unable to upgrade; everything else starts at P2. If you find nothing in a category, say 'clear' — don't pad, and never reach for a finding to avoid an empty category."

**Where the second opinion matters most:** the per-scheme evaluation algorithms,
every migration, the scoping and non-responder helpers in
`application/services/`, participant response writes, and report and chart
generation. Routine CRUD and copy changes do not need it — don't ritualize this
into overhead on a team this size.

**What counts as a finding:** a defect with a failure scenario — concrete inputs
or state, and the wrong output or lost data that results. A trade-off this
codebase has already made deliberately is a rebuttal, not a fix; say so rather
than reporting it. Address or explicitly rebut every finding before it lands. A
rebuttal is a sentence saying why the code is right, not silence.

**Stopping rule:** a pass returning zero P1 findings. Not zero findings — zero
P1. Lesser ones are fixed forward on `master` like ordinary work.

**The fix is the riskiest commit.** A fix for a review finding is new code
written under pressure, and it is where the next P1 most often appears. Review
the fix too; skipping it because the original already passed is the weakest
place to save a pass.

**From a worktree, review commit by commit.** With no argument the script diffs
against the local `master`, which can hold another session's unpushed commits.
Their changes then show up as findings in files your branch never touched.

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

## 5. Maintaining versioned references

Update the reviewed version on affected reference pages when implementation changes.
Keep implementation behaviour separate from proposed requirements and executed evidence.

| Change | References to review |
| --- | --- |
| Schema migration | Core dictionary and physical column snapshot |
| API controller or payload | API route inventory and integration acceptance cases |
| Authentication or privileges | Security reference and user management |
| Scheduler or cleanup | Architecture, storage and retention, backup procedures |
| Dependency lockfile | Software and license inventory |
| Workflow or evaluation | Functional requirements, user guide and validation cases |

For a physical-column refresh, capture read-only metadata from a version-matched
reference database. Include table, column, ordinal position, SQL type, nullability,
default, column key and extra attributes from `information_schema.COLUMNS`.
Capture foreign-key column mappings from `information_schema.KEY_COLUMN_USAGE`.
Record the application version, server version and capture date.
Preserve the distinction between physical types and programme-approved business definitions.

For a license refresh, use each PHP package's name, version and license array in
`composer.lock`. Use each non-root package location, version and license in
`package-lock.json`. Keep missing metadata explicit and retain the bundled-asset limitation.

Keep country-specific assessment notes outside `docs/`. Public procedures use generic
examples. Local inventories, agreements, completed test evidence and approvals remain
separate deployment records.
