# How to translate ePT into your language

ePT ships in English. A laboratory that reads the interface in the wrong words reports the wrong result. This guide covers translating the interface so it stays correct for the people using it.

Translations live in GNU gettext catalogs at `application/languages/{locale}/{locale}.po`.

## Translate documentation pages

Public documentation uses whole-page translations. French pages live in `docs/fr/`
with the same filenames as their English sources. Use the header language picker
to switch between English and French.

1. Translate the complete page, including headings, tables, diagram labels and limitations.
2. Apply the same technical-writing rules in both languages: short sentences, direct instructions and consistent terms.
3. Preserve commands, routes, identifiers, version numbers, license expressions and requirement IDs.
4. Link to the French counterpart when it exists. Label links to untranslated pages as English.
5. Add the page to the shared navigation and its French label under `nav_translations` in `mkdocs.yml`.
6. Verify that the header language picker opens the corresponding translated page.
7. Run `mkdocs build --strict` to check navigation and links.

For in-app help, place the translated body in `docs/help/{audience}/fr_FR/`.
Keep the English frontmatter keys and source strings. Translate their displayed values
through the French PO/MO catalog. See [the help authoring reference](https://github.com/deforay/ept/blob/master/docs/help/README.md).

The public French section currently covers the twelve system and operations pages
listed in its index. Older guides remain English and are labeled accordingly.

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

These are the fixed terms. Match them in every string.

| English | French (fr_FR) | Vietnamese (vi_VN) |
| --- | --- | --- |
| Scheme | Programme | Chương trình |
| Shipment | Expédition | Vòng ngoại kiểm |
| Round | Cycle | Vòng |
| PT survey | Enquête PT | Khảo sát PT |
| Participant | Participant | Đơn vị tham gia |
| Laboratory, lab | Laboratoire | Phòng xét nghiệm |
| Lab ID | ID du laboratoire | Mã phòng xét nghiệm |
| Data manager | Gestionnaire de données | Người quản lý dữ liệu |
| PTCC | PTCC | PTCC |
| Enroll | Inscrire | Đăng ký |
| Sample | Échantillon | Mẫu |
| Panel (of samples) | Panel | Bộ mẫu |
| Panel (screen area) | Panneau | Khung |
| Dried tube specimen | Échantillon sur tube séché | Mẫu ống khô |
| Test kit | Kit de test | Bộ sinh phẩm |
| Assay | Technique | Phương pháp xét nghiệm |
| Platform (instrument) | Plateforme | Hệ thống máy |
| Viral load | Charge virale | Tải lượng virus |
| Result | Résultat | Kết quả |
| Response | Réponse | Phản hồi |
| Deadline | Date limite | Hạn nộp kết quả |
| Expiry date | Date d'expiration | Ngày hết hạn |
| Outstanding | En attente | Chưa phản hồi |
| Evaluate | Évaluer | Đánh giá |
| Not evaluated | Non évalué | Chưa được đánh giá |
| Pass | Réussi | Đạt |
| Fail | Échec | Không đạt |
| Satisfactory | Satisfaisant | Đạt |
| Unsatisfactory | Insatisfaisant | Không đạt |
| Excluded | Exclu | Loại trừ |
| Finalize | Finaliser | Hoàn tất |
| Report | Rapport | Báo cáo |
| Score | Score | Điểm |
| Certificate | Certificat | Giấy chứng nhận |
| Corrective action | Action corrective | Hành động khắc phục |
| Email | E-mail | Email |
| Email template | Modèle d'e-mail | Mẫu email |
| Login (account name) | Identifiant | Tên đăng nhập |
| Log in | Se connecter | Đăng nhập |

Notes on terms that read differently from the English word:

- A participant is a laboratory, not a person. Vietnamese uses "Đơn vị tham gia", the usual wording in external quality assessment.
- Vietnamese "Phòng thí nghiệm" means a research laboratory. A testing laboratory is "Phòng xét nghiệm".
- "Deadline" is the last day to submit results. Vietnamese "Ngày hết hạn" means an expiry date. Keep it for kit and sample expiry.
- "Excluded" means left out of the evaluation. Vietnamese "Không bao gồm" means "not included" and reads as a missing item.
- "Finalize" locks the round. Vietnamese "Hoàn thiện" means "perfect" or "polish". Use "Hoàn tất".
- French uses "Laboratoire" in full. Do not write "labo".
- Keep product, assay, test kit and instrument names in their original form. They are brand names.

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
| No finalized rounds yet. | Chưa có tệp nhật ký nào | There are no log files | Chưa có vòng nào được hoàn tất |

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
