---
title: How to add a shipment
summary: Step-by-step walkthrough — from PT Survey to a shipment that is ready to ship
audience: admin
estimated_minutes: 5
tags: [guide, shipment, workflow, pt-survey]
steps:
  - id: 1
    title: Make sure you have a PT Survey
    target_pages: [distributions/index]
  - id: 2
    title: Click "Add Scheme" next to your survey
    target_pages: [distributions/index]
  - id: 3
    title: Fill in the shipment details
    target_pages: [shipment/add]
  - id: 4
    title: Enroll participants in this shipment
    target_pages: [shipment/manage-enroll, shipment/view-enrollments, shipment/add-enrollments]
  - id: 5
    title: Ship it
    target_pages: [distributions/index]
---

# How to add a shipment

A **shipment** is one panel of test samples that you send to
participants. Every shipment lives **inside a PT Survey** — so
before you can add a shipment, you need a PT Survey to put it
under.

This guide walks you all the way from the survey to a shipped
shipment. It takes about 5 minutes.

## Step 1: Make sure you have a PT Survey

Go to **Manage → PT Survey**. You will see the list of all PT
Surveys in your system.

- If a survey already exists for the date you want, you can add
  your new shipment under it. Move to **Step 2**.
- If not, click **Add New PT Survey** at the top right. Pick a
  date and a code, then save.

Don't worry — the PT Survey by itself does not send anything to
participants. It is just a folder for one round of testing.

## Step 2: Click "Add Scheme" next to your survey

On the PT Survey list, find your survey's row. In the **Action**
column, click **Add Scheme**.

"Add Scheme" is the same thing as "Add Shipment" — it adds a new
shipment under that survey for one test type.

If your survey already has one shipment and you want to add
another (for example, you want to send both an HIV Serology panel
and a VL panel on the same date), just click **Add Scheme**
again. You can have more than one shipment per survey.

## Step 3: Fill in the shipment details

You are now on the **Add Shipment** page. This is the screen
that confuses many users — it looks like a brand new task, but
it isn't. **You did not lose your PT Survey.** This page is just
the next step.

Fill in:

- **Scheme** — the test type (for example, *Dried Tube Specimen —
  HIV Serology*).
- **Shipment Code** — a code is filled in for you. You can change
  it if you want.
- **Result Due Date** — the last date by which participants must
  send in their results.
- **Samples** — click **+ Sample/Control** to add a sample row.
  For each sample, give it a name (like `S1`) and pick its
  **expected result**. Most panels have 3 to 5 samples.

Click **Add Shipment** at the bottom to save.

## Step 4: Enroll participants in this shipment

After saving, you are taken to the **Enroll** page for this
shipment.

- On the **left** is a list of **Available** participants — these
  are the labs that are already enrolled in the scheme you picked.
- On the **right** is the **Enrolled** list — the labs that will
  get this shipment.

Move names from left to right using the arrow buttons. Click
**Enroll** to save.

> If a lab you want is missing from the **Available** list, it
> probably is not yet enrolled in the scheme. See the guide
> *How to enroll participants* — it explains the difference
> between scheme enrollment and shipment enrollment.

## Step 5: Ship it

Go back to **Manage → PT Survey**.

Find your survey in the list. If everything is set up, the
**Ship Now** button will be visible in the **Action** column.

- Click **Ship Now**.
- A confirmation pop-up will ask if you are sure. Click **OK**.
- That's it — the shipment is now visible to the participants
  you enrolled, and they can begin entering their results.

> **Heads up:** Shipping cannot be undone. Once you click Ship
> Now, participants can see the shipment and start responding.
> So check your samples and enrollments first.
