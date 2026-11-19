---
title: Log Viewer
summary: Read ePT's error logs to find out what went wrong, without access to the server
tags: [logs, errors, troubleshooting, debug]
---

# Log Viewer

The Log Viewer shows ePT's log files. ePT writes one file per day.
Use it to find the error behind a failed page or job.

## Find the error behind a problem

1. Open **Configure → Log Viewer**.
2. In the first list, choose the day the problem happened.
3. In the level list, choose **ERROR**.
4. Type a word from the problem in the search box, such as a
   shipment code or an email address.
5. If a line has **context** under it, click **context** to see
   the details.

The newest lines come first.

If an error page shows an **Error ID**, click **View Logs** on
that page. The Log Viewer opens with the Error ID already in the
search box.

## Choose the log

The two tabs at the top choose the log:

- **Application Errors**: problems on the server.
- **Client (Browser) Errors**: problems in users' browsers.

## Controls

| Control | What it does |
| --- | --- |
| File list | Chooses the day. The newest day comes first. Each file shows its size |
| Level list | Shows only lines of one level: **DEBUG**, **INFO**, **WARNING**, **ERROR** or **CRITICAL**. **All levels** shows every line |
| **Search text (case-insensitive)…** | Shows only lines that contain this text |
| **Max lines** | How many lines to show, from 50 to 5000. The default is 500 |
| **Refresh** | Reloads the file |
| **Clear filters** | Clears the level, the search and the line limit |
| **Delete file** | Deletes the chosen file |
| **Delete all** | Deletes every file in the current tab |

Above the lines, the page shows how many lines it found, the file
size, and when ePT last wrote to the file.

## Delete old logs

1. Choose the file.
2. Click **Delete file**.
3. Confirm.

To delete every file in the tab, click **Delete all** and confirm
twice. Deleted files cannot be restored. ePT records each deletion in
the application log.
