---
title: Certificate Templates
summary: The PDF templates that annual certificates print from, the fields ePT fills and the checks on upload
tags: [certificates, templates, pdf, fields, excellence, participation]
---

# Certificate Templates

**Configure → Certificate Templates** holds one Excellence template and one Participation template per scheme. Each template is a PDF form. ePT fills its form fields with each participant's details and flattens the result into a plain PDF.

[See: How to make a certificate template](guides/make-a-certificate-template.md)

## Template types

| Template | Used for |
|---|---|
| Excellence | Participants who responded to and passed every shipment of the scheme in the batch |
| Participation | Participants who responded to every shipment of the scheme but did not pass them all |

A participant who missed a shipment of the scheme gets no certificate for that scheme.

## Fields

ePT fills a form field when its name matches a field name below. Older names still work. Field names are not case-sensitive.

| Field name | Older names | Value | Source |
|---|---|---|---|
| `participant_name` | `participantname`, `labname`, `participant` | The lab name. For individual participants, the first and last name. | Participant record |
| `lab_id` | None | The Lab ID | Participant record |
| `city` | None | The city | Participant record |
| `country` | None | The country name | Participant record |
| `scheme_name` | None | The scheme name, for example "HIV Viral Load" | Scheme |
| `assay` | `assayname` | The VL assay or EID extraction assay. Empty for other schemes. | The participant's latest response in the batch |
| `shipment_year` | `shipmentyear` | The year of the participant's latest shipment of the scheme in the batch | Shipment date |
| `issue_date` | None | The date the batch is generated, in the date format from Global Settings | Generation date |
| `signatory_name` | None | **Certificate Signatory Name** | Configure → Global Settings |
| `signatory_title` | None | **Certificate Signatory Title** | Configure → Global Settings |

`participant_name` is required. Every other field is optional.

A form field with any other name keeps the text the template gives it.

## Checks on upload

ePT checks every uploaded template before saving it.

| Check | Result when it fails |
|---|---|
| The file is a PDF. | Rejected |
| ePT can read the form fields. | Rejected. The PDF is damaged or password-protected. |
| The PDF has form fields. | Rejected |
| One field is named `participant_name` or an older name for it. | Rejected |
| The participant name field is a text field. | Rejected |
| ePT can fill the PDF in a test run. | Rejected. XFA forms fail this check. |
| Every field name is in the field list. | Saved. The card lists the other fields in orange. |

Generating certificates runs the same checks on each template again. A template that fails is listed in the batch's error message.

## Template card actions

| Action | Result |
|---|---|
| Preview | Opens the template filled with a participant from the scheme's latest evaluated shipment. Uses placeholder text when the scheme has no evaluated response. |
| View | Opens the blank template |
| Replace | Uploads a new template in place of the current one, after the checks |
| Remove | Deletes the template. The scheme gets no certificates of that type until a new template is uploaded. |

## Requirements

The server needs `pdftk` to check and fill templates.
