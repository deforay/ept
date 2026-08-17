# How to translate ePT into your language

ePT ships in English. A laboratory that reads the interface in the wrong words reports the wrong result. This guide covers translating the interface so it stays correct for the people using it.

Translations live in GNU gettext catalogs at `application/languages/{locale}/{locale}.po`.

## Before you start

- Install [Poedit](https://poedit.net) 3.x.
- Ask the instance maintainer to run `php bin/refresh-translations.php`. That command pulls new English strings into your `.po` file. See [CLI tools](cli-tools.md).
- Read the next section before you translate the first string.

## Translate in the laboratory testing context

ePT runs proficiency testing for medical laboratories. A PT provider sends prepared samples to enrolled laboratories. Each laboratory tests the samples and reports its results. The provider scores those results and issues a report.

Every string means something in that setting. Translate the meaning, not the English word.

| Word | What it means in ePT | What it never means |
| --- | --- | --- |
| PT | Proficiency testing | Physical therapy, physiotherapy, patient, part-time |
| Scheme | A category of testing, such as HIV Serology or Viral Load | A diagram, a plan, or a scam |
| Shipment | A batch of samples sent out to laboratories | A commercial delivery or an order |
| Round | One testing cycle of a scheme | A shape or a drinks round |
| Panel | The set of samples in one shipment | A screen area or a control panel |
| Participant | An enrolled laboratory | An individual person |
| Outstanding | Has not responded yet | Excellent, or a destination |
| Result | What a laboratory reported for a sample | An outcome in the general sense |
| Evaluate | Score a laboratory's reported results | Consider or estimate |
| Finalize | Lock a round so it can no longer change | Complete in the general sense |

## Keep one word for one thing

Pick one translation for each term. Use it in every string, on every screen.

Rotating between two words for the same thing makes the interface unreadable. A laboratory that sees three different words for "shipment" cannot tell whether they mean three different things.

These are the terms already fixed in the shipped catalogs. Match them.

| English | French (fr_FR) | Vietnamese (vi_VN) |
| --- | --- | --- |
| Scheme | Programme | Chương trình |
| Shipment | Expédition | Vòng ngoại kiểm |
| Round | Cycle | Vòng |
| Participant | Participant | Người tham gia |
| Laboratory | Laboratoire | Phòng thí nghiệm |
| Response | Réponse | Phản hồi |
| Deadline | Date d'échéance | Ngày hết hạn |
| Outstanding | En attente | Chưa phản hồi |
| Pass | Réussi | Đạt |
| Fail | Echec | Không Đạt |
| Excluded | Exclu | Không bao gồm |
| Not evaluated | Non évalué | Chưa được đánh giá |
| Evaluate | Évaluer | Đánh giá |
| Finalize | Finaliser | Hoàn thiện |
| Report | Rapport | Báo cáo |
| Score | Score | Điểm |

Adding a language? Fill in this table for it first. Translate afterwards.

## Steps

1. Open `application/languages/{locale}/{locale}.po` in Poedit.
2. Sort by translation status so untranslated entries come first.
3. Translate each empty entry. Check the source file path shown in the sidebar when the meaning is unclear.
4. Check every entry Poedit marks as needing work. See the section below.
5. Save the file. Poedit writes the `.po` and compiles the `.mo` beside it.
6. Verify your work. See the section below.

## Check every entry marked as needing work

Poedit marks an entry orange when it copied the translation from a similar English string. These copies are guesses. They are wrong often enough that you must read every one.

Real examples found in the shipped catalogs:

| English | Guessed translation | What it meant | Correct |
| --- | --- | --- | --- |
| Outstanding | destination | destination | En attente |
| Next action | extraction | extraction | Action suivante |
| unable to test | Activer les tests personnalisés | Enable custom tests | non réalisés |
| Rounds completed in | Các cột được đánh dấu trong | The columns marked in | Số vòng đã hoàn thành trong |
| No finalized rounds yet. | Chưa có tệp nhật ký nào | There are no log files | Chưa có vòng nào được hoàn thiện |

Read the English. Read the guess. If the guess does not say the same thing, replace it and clear the mark.

## Keep placeholders and markup exactly as they are

Copy these through unchanged:

- Number and text placeholders: `%d`, `%s`, `%1$s`
- HTML tags: `<strong>`, `<br>`, `<a href="...">`
- Email addresses and URLs

`%d` becomes a number when the page loads. Deleting it removes the number. Changing it breaks the page.

To reorder a sentence that has two placeholders, number them. `%s uploaded %d files` becomes `%2$d fichiers envoyés par %1$s`.

## Write for a professional reader

- Use the formal register. French uses "vous". Vietnamese uses neutral professional forms.
- Include articles. Write "Générer les rapports", not "Générer rapports".
- Match the capitalisation style of the English string.
- Use the correct accents and diacritics for your language.
- Leave no space at the start or end of a translation.

## Verify your work

1. Confirm Poedit reports zero untranslated and zero entries needing work.
2. Run `msgfmt --check-format -o /dev/null application/languages/{locale}/{locale}.po`. It prints nothing when the placeholders are intact. It names the line when they are not.
3. Sign in to ePT, switch the interface to your language, and open the admin dashboard.
4. Read the round table. Every column heading and status should read as a laboratory professional would say it.

If a string still shows in English, the `.mo` file is stale. Ask the maintainer to rerun `php bin/refresh-translations.php --locale={locale}`.

## Where strings come from

Two sources feed the catalogs. Knowing which is which explains why a string appears.

| Source | Example | Notes |
| --- | --- | --- |
| Application code | Column headings, buttons, messages | Extracted from `.phtml` and `.php` files |
| Database lookup tables | Test result names, sample types | Extracted from `r_*` tables |

Every instance has its own lookup data, so the catalog holds the values from all of them. Expect strings for tests and sample types your instance does not run. Translate them anyway. Leaving them empty costs nothing, but translating them helps the next country.

## Reporting a problem

Report an English string that cannot be translated correctly, or one that is missing from the catalog, at [deforay/ept](https://github.com/deforay/ept). Include the English text and the screen it appears on.
