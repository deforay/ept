---
title: Certificate Templates
summary: The designs annual certificates print on, the details ePT adds and the checks on upload
tags: [certificates, templates, design, designer, pdf, fields, excellence, participation]
---

# Certificate Templates

**Configure → Certificate Templates** holds one Excellence template and one Participation template per scheme. ePT prints every annual certificate from these templates.

[See: How to set up a certificate template](guides/make-a-certificate-template.md)

## Template types

| Template | Used for |
|---|---|
| Excellence | Participants who responded to and passed every shipment of the scheme in the batch |
| Participation | Participants who responded to every shipment of the scheme but did not pass them all |

A participant who missed a shipment of the scheme gets no certificate for that scheme.

## Kinds of template

| Kind | What it is | How ePT prints the details |
|---|---|---|
| Design | A PDF or image of the certificate, uploaded without form fields | At the boxes placed in the designer |
| PDF form | A PDF with a form field named `participant_name` | Into the PDF's form fields |

The template card shows the kind after the file name.

## Template states

| State | Meaning |
|---|---|
| Ready | Certificates print from this template. |
| Needs details | A design without a Participant name box. Certificates do not print until the box is placed. |
| Check fields | A PDF form with fields that ePT does not fill. Those fields print as they are in the template. |
| Missing | No template. The scheme gets no certificates of this type. |

## Details

Each detail has a name in the designer and a field name for PDF forms.

| Designer name | PDF form field | Older field names | Value | Source |
|---|---|---|---|---|
| Participant name | `participant_name` | `participantname`, `labname`, `participant` | The lab name. For individual participants, the first and last name. | Participant record |
| Lab ID | `lab_id` | None | The Lab ID | Participant record |
| City | `city` | None | The city | Participant record |
| Country | `country` | None | The country name | Participant record |
| Scheme name | `scheme_name` | None | The scheme name, for example "HIV Viral Load" | Scheme |
| Assay | `assay` | `assayname` | The VL assay or EID extraction assay. Empty for other schemes. | The participant's latest response in the batch |
| Shipment year | `shipment_year` | `shipmentyear` | The year of the participant's latest shipment of the scheme in the batch | Shipment date |
| Issue date | `issue_date` | None | The date the batch is generated, in the date format from Global Settings | Generation date |
| Signatory name | `signatory_name` | None | **Certificate Signatory Name** | Configure → Global Settings |
| Signatory title | `signatory_title` | None | **Certificate Signatory Title** | Configure → Global Settings |

Participant name is required. Every other detail is optional.

In a PDF form, field names are not case-sensitive. A form field with any other name keeps the text the template gives it.

## Designer boxes

Each box holds text: details, fixed wording or both. A detail appears in the text as its field name in braces, for example `{participant_name}`.

| Setting | Values | Default |
|---|---|---|
| Font | Sans, Serif, Sans for all languages, Serif for all languages | Sans |
| Size | 4 to 120 points | 28 for Participant name, 14 for other details |
| Style | Bold, italic | Bold for Participant name |
| Colour | Any colour | Black |
| Alignment | Left, centre, right | Centre |
| When the text is too long | Make it smaller, or wrap onto lines | Make it smaller |

**Make it smaller** shrinks the text until it fits the box width, to no less than 60% of the chosen size. Text still too long at that size wraps onto more lines, centred on the box.

**Wrap onto lines** wraps the text inside the box and shrinks it until it fits the box height.

Sans and Serif have Western European characters only. Text with other characters, such as Vietnamese or Cyrillic, prints in the matching font for all languages.

The designer's **Showing** list holds the participant from the scheme's latest evaluated shipment and the participant with the longest name. **Preview PDF** prints one page for each, with unsaved changes.

## Checks on upload

| Upload | Result |
|---|---|
| A PDF with a `participant_name` text field that ePT can fill | Saved as a PDF form |
| Any other PDF that ePT can read | Saved as a design. The designer opens. |
| A PNG or JPG image | Turned into a one-page PDF the size of an A4 page and saved as a design. The designer opens. |
| A PDF that ePT cannot read directly | Rewritten with pdftk or Ghostscript, then saved as a design |
| A damaged or password-protected PDF, or any other file | Rejected |

Replacing a design keeps its boxes.

Generating certificates checks each template again. A template that fails is listed in the batch's error message.

## Template card actions

| Action | Result |
|---|---|
| Place details | Opens the designer. Designs only. |
| Preview | Opens the template filled with sample participants |
| View | Opens the blank template |
| Replace | Uploads a new file in place of the current one, after the checks |
| Remove | Deletes the template and its boxes |

## Requirements

PDF forms need `pdftk` on the server. Designs need nothing extra. Ghostscript lets ePT read more PDFs on upload.
