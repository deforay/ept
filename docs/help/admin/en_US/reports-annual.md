---
title: Annual Performance Report
summary: Evaluated results for a date range, and the certificates that follow from them
tags: [reports, annual, certificates, excellence, participation, csv, excel]
---

# Annual Performance Report

**Reports → Annual Performance Report** lists every evaluated response to the finalized shipments in a date range. The same page generates the excellence and participation certificates.

## Which responses count

The report covers shipments that are finalized and not cancelled. A response counts when it passed, or when it was submitted and failed. Participants who did not respond never show as failed.

## Report options

| Field | What it does | Default |
|---|---|---|
| Shipment Date | Includes shipments whose shipment date falls in this range. | Last 12 months |
| Scheme | Includes shipments of the picked schemes. You can pick more than one. | All schemes |
| Shipment | Includes only the shipments you pick. The list holds every finalized shipment in the date range and schemes. | All shipments in the list |

Changing the date range or the schemes reloads the **Shipment** list. A picked shipment stays picked while it is still in the list.

| Report type | File | Contents |
|---|---|---|
| Annual Report Only | CSV | One row per response, with the participant, the shipment, the score, the result, the warnings and the corrective actions. |
| For Certification | Excel | One row per participant, with the score and result of every shipment, and the certificate type for each scheme. |

The certificate type is **Excellence** when the participant responded to and passed every shipment of the scheme. It is **Participation** when the participant responded to every shipment but did not pass them all. It is **N.A.** otherwise.

## Certificates

Certificates are issued on **Reports → Annual Certificates**, which uses the same results as the For Certification file. [See: How to issue annual certificates](guides/issue-annual-certificates.md)
