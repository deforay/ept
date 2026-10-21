---
title: PDF Report Settings
summary: Letterhead, logo, PDF template, layout and approver used on generated reports
tags: [config, reports, pdf, logo, template, letterhead, approver]
---

# PDF Report Settings

This page controls the **look** of every PDF report the system
produces: participant reports, summary reports and certificates.

The side menu lists the three sections. Click a section name to
jump to it. Type in **Search settings** to show only the fields
whose label matches. **Expand All** and **Collapse All** open or
close every section.

## Letterhead

ePT builds the letterhead from these settings. If a PDF template
is uploaded in the next section, the template provides the
letterhead instead and these settings are not used.

- **Report Header Text** *(required)*: text at the top of every
  report. Keep it to one or two lines. Long text pushes the rest
  of the page down.
- **Institute Address Position**: where the institute address
  from *Global Config* appears.
  - **Header**: under the report title.
  - **Footer**: in the page footer.
  - **Don't Show**: nowhere.
- **Report Logo**: the logo printed on every report. The box shows
  the current logo. Use **Select image** or **Change** to upload
  one. Use **Remove** to clear it. PNG or JPG, about 200×150
  pixels, works best.

## PDF Template

This section is optional. A PDF uploaded here becomes the page
background of every report, such as a pre-printed letterhead with
a watermark and footer lines.

- **Report Template**: the PDF file. When a template is uploaded,
  you see a preview with three buttons:
  - **View Full Size**: opens the PDF.
  - **Replace**: uploads a new file in its place.
  - **Delete**: removes the template. You are asked to confirm.
- **Template Top Margin**: how far down the page report content
  starts, in pixels, so it clears the template's letterhead.
  A typical value is `40`.

Only `.pdf` files are accepted.

## Layout & Output

- **Report Layout**: the layout the reports use. This field only
  appears when the application has at least one layout.
- **Reports for Excluded Submissions**: *YES* also makes reports
  for participants whose submission was excluded from evaluation.
  Non-responders never get a report.
- **Default Approver Name**: offered as the approver when a
  shipment is finalized. Reports finalized before an approver was
  recorded print this name. Whoever finalizes a shipment can
  change it for that shipment.

## Saving

Click **Update** to save the whole form, including an uploaded
logo or template. The **Update** and **Back** buttons stay in view
as you scroll. Required fields are marked with `*`.

## Tips

- Changes apply to the next reports ePT builds. Test them by
  regenerating one shipment's reports from *Analyze → Generate
  Reports*.
- If you replace the logo or template, regenerate any important
  draft reports. Finalized reports keep the look they had when
  they were finalized.
- If report content overlaps the template's letterhead, increase
  **Template Top Margin**. If the first page has a large empty
  gap, decrease it.
