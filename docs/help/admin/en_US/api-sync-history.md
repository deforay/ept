---
title: API Sync History
summary: See the requests that data managers sent to ePT through its API, with what was sent and returned
tags: [api, sync, history, data-manager, troubleshooting]
---

# API Sync History

This page lists the requests that data managers send to ePT
through its API, from another application. Each row is one
request, such as a login or saved results. Use the page to check
whether a data manager's results reached ePT.

## Check whether a data manager's results arrived

1. Open **Configure → API Sync History**.
2. Under **Select Data Manager (Optional)**, choose the data
   manager.
3. Under **Pick a Date Range (Optional)**, choose the days when
   they sent the results.
4. Under **Select Sync Type (Optional)**, choose **Save
   Shipments**.
5. Find the row in the list.
6. Click **Show Params** to see what was sent and what ePT
   returned.

The pop-up has a **REQUEST** tab and a **RESPONSE** tab. Each
tab shows the address that was called and the data. If the
response has `"status": "fail"`, its message says why ePT refused
the request.

If no row matches, ePT did not save those results. Ask the data
manager to send them again.

## Filters

| Filter | What it does |
| --- | --- |
| **Pick a Date Range (Optional)** | Shows requests from these days only |
| **Select Sync Type (Optional)** | Shows one kind of request, such as **Login** or **Save Shipments** |
| **Select Scheme Type (Optional)** | Shows requests for one scheme |
| **Select Data Manager (Optional)** | Shows requests from one data manager |
| **Reset** | Clears every filter |

## Columns

| Column | What it shows |
| --- | --- |
| **Transaction ID** | A unique code for the request |
| **Number of Records Synced** | How many records the request carried |
| **Sync Type** | The kind of request |
| **Scheme Type** | The scheme, or **COMMON** when the request is not tied to one scheme |
| **URL** | The address that was called |
| **Synced On** | When ePT received the request |
| **Action** | **Show Params** |
