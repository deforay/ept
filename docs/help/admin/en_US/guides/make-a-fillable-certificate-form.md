---
title: How to make a fillable PDF certificate form
summary: For admins who prefer PDF forms. Most admins upload a design and place the details in ePT instead
audience: admin
estimated_minutes: 20
tags: [guide, certificates, templates, pdf, libreoffice, excellence, participation]
steps:
  - id: 1
    title: Open the design in LibreOffice
    target_pages: []
  - id: 2
    title: Add a text field for each detail
    target_pages: []
  - id: 3
    title: Export a PDF form
    target_pages: []
  - id: 4
    title: Upload the PDF
    target_pages: [certificate-templates/index]
  - id: 5
    title: Check the preview
    target_pages: [certificate-templates/index]
---

# How to make a fillable PDF certificate form

Most admins do not need this guide. Uploading a design and placing the details in ePT is quicker. [See: How to set up a certificate template](make-a-certificate-template.md)

A fillable PDF form is the other kind of template: a PDF with a form field wherever a detail such as the participant name goes. ePT fills the fields. This guide turns your design into that form.

Each scheme needs two templates, one for **Excellence** and one for **Participation**. Repeat these steps for each one.

You need LibreOffice. It is a free office program that works like Microsoft Office. Microsoft Word cannot add form fields to a PDF, but LibreOffice can, and it opens Word files and PDFs. Download it from [libreoffice.org](https://www.libreoffice.org) and install it. Adobe Acrobat Pro also works, but the menu names differ.

## Step 1: Open the design in LibreOffice

- If the design is a Word file, save it as a PDF in Word first: **File → Save As → PDF**. Then open the PDF in LibreOffice. It opens in **LibreOffice Draw**.
- If the design is a PDF, open it in LibreOffice. It opens in **LibreOffice Draw**.
- If there is no design yet, create one in **LibreOffice Writer**. Set **Format → Page Style → Page → Orientation** to **Landscape**.

Leave every detail that changes per participant out of the design. That includes the participant name and the year. ePT prints them.

## Step 2: Add a text field for each detail

1. Choose **View → Toolbars → Form Controls**.
2. Click **Design Mode** on the Form Controls toolbar, if it is not already on.
3. Click **Text Box**.
4. Draw a box where the participant name goes. Make it as wide as the longest name.
5. Right-click the box and choose **Control Properties**.
6. On the **General** tab, set **Name** to `participant_name`.
7. Set **Border** to **Without frame**.
8. Set **Alignment** to **Center**.
9. Set **Font** to the font and size the name must print in.
10. Close the properties window.

Add a box the same way for each other detail the certificate shows. Use the exact field names below. A misspelled name prints nothing.

| Field name | Prints |
|---|---|
| `participant_name` | The lab name, or the person's name for individual participants. Required. |
| `lab_id` | The participant's Lab ID |
| `city` | The participant's city |
| `country` | The participant's country |
| `scheme_name` | The scheme, for example "HIV Viral Load" |
| `assay` | The VL or EID assay the participant used |
| `shipment_year` | The year of the shipments the certificate covers |
| `issue_date` | The date the certificates are generated |
| `signatory_name` | The **Certificate Signatory Name** from Global Settings |
| `signatory_title` | The **Certificate Signatory Title** from Global Settings |

[See: Certificate Templates](../certificate-templates.md) for where each value comes from.

## Step 3: Export a PDF form

1. Choose **File → Export As → Export as PDF**.
2. On the **General** tab, tick **Create PDF form**.
3. Click **Export** and save the file.

## Step 4: Upload the PDF

1. Go to **Configure → Certificate Templates**.
2. Find the scheme.
3. Under **Excellence** or **Participation**, click **Upload** or **Replace**.
4. Drop the PDF on the upload area, or click the area to pick the file.

ePT checks the PDF before saving it. If the check fails, the card says what to fix. Fix it in LibreOffice, export again and upload again.

If the PDF has no `participant_name` field, ePT treats it as a design and opens the designer. Check the field name in Step 2.

If the card lists fields that ePT does not fill, check each one. ePT prints those fields as they are in the template. A field meant for a participant detail has the wrong name. Rename it in Step 2 and upload again.

## Step 5: Check the preview

1. Click **Preview with a real participant**.
2. Check that every detail sits in the right place and fits its box.
3. Click **Done**.

The preview uses a participant from the scheme's latest evaluated shipment. If a detail is cut off, widen its box in LibreOffice and upload again.

The **Preview** button on each template card shows the same check at any time.
