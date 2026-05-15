---
title: How to enroll participants
summary: The two kinds of enrollment in ePT — and how to do each one
audience: admin
estimated_minutes: 5
tags: [guide, enrollment, participants, workflow]
steps:
  - id: 1
    title: Two kinds of enrollment — what's the difference?
    target_pages: []
  - id: 2
    title: Add the participant to the system (if new)
    target_pages: [participants/index, participants/add, participants/bulk-import]
  - id: 3
    title: Enroll the participant in a scheme
    target_pages: [enrollments/index, enrollments/add, enrollments/bulk-enrollment]
  - id: 4
    title: Enroll the participant in a specific shipment
    target_pages: [shipment/manage-enroll, shipment/view-enrollments, shipment/add-enrollments, distributions/index]
---

# How to enroll participants

Enrollment in ePT happens at **two levels**, and many users get
tripped up because they look the same on the surface. This guide
explains the difference, then walks you through both.

It takes about 5 minutes.

## Step 1: Two kinds of enrollment — what's the difference?

There are two enrollments to keep straight:

1. **Scheme enrollment** — you tell the system "this lab does HIV
   Serology PT". You do this **once**, when the lab joins your
   programme. It does not send anything to anyone. It just means
   the lab is part of that scheme.

2. **Shipment enrollment** — for each PT round (each shipment),
   you tell the system "this specific shipment goes to these
   labs". You do this **for every shipment**.

Why both? Because a lab might be enrolled in HIV Serology
year-round, but for a specific quarter's shipment you might want
to exclude it (maybe it is closed for maintenance). Shipment
enrollment lets you pick and choose per round, without removing
the lab from the scheme.

A lab must be enrolled in the **scheme** before it can be
enrolled in any **shipment** of that scheme.

> So the order is always: **Add lab → Scheme enrollment →
> Shipment enrollment.**

## Step 2: Add the participant to the system (if new)

Skip this step if the lab is already in your system.

To add a new lab:

- Go to **Configure → PT Participants**.
- For one lab: click **Add** at the top right, fill in the name,
  unique code, contact details, and save.
- For many labs at once: click **Bulk Import Participants**,
  download the Excel template, fill it in, and upload. Fields
  with red headings are required.

After saving, the lab shows up in the participants list with a
unique Participant ID.

## Step 3: Enroll the participant in a scheme

This is the **one-time** step that lets a lab take part in a
scheme.

- Go to **Configure → Scheme Enrollments**.
- Pick the scheme from the dropdown at the top (for example,
  *HIV Serology*).
- Click the relevant scheme enrollment link.
- On the next page, you will see two lists side by side:
  - **Available** (left) — labs in your system that are **not**
    yet enrolled in this scheme.
  - **Enrolled** (right) — labs already enrolled.
- Move labs from left to right using the arrow buttons.
- Click **Enroll Selected** at the bottom to save.

A lab can be enrolled in more than one scheme — repeat this step
for each scheme the lab takes part in.

> Many users at large programmes use the **Bulk Enrollment**
> option instead. It lets you enroll many labs in many schemes
> at once via an Excel upload.

## Step 4: Enroll the participant in a specific shipment

This is the step you do **every time** you create a new shipment.

- Open the shipment (from **Manage → PT Survey**, click your
  survey, then open the shipment row).
- Click the **Enroll** button (red).
- You will see the same two-list layout:
  - **Available** (left) — labs that are scheme-enrolled but not
    yet on this shipment.
  - **Enrolled** (right) — labs that will receive this shipment.
- Move names from left to right.
- Click **Enroll** at the bottom to save.

If a lab you expect to see is **missing from the Available
list**, it is almost always because the lab is not yet enrolled
in the scheme (Step 3). Go back and enroll it in the scheme
first, then come back here.

After saving, the shipment will go to the labs on the **Enrolled**
list as soon as you click **Ship Now** on the PT Survey list.
