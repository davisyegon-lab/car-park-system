# Development log

## 21 November 2026 EAT

- Added Nairobi date windows, audit filters for event and parking session number, and report history filters for session number or campus registration, last assigned zone, and current status. Reports default to today. Audit defaults to recent events across all dates.
- Separated gate entries and recorded exits in the selected window from live availability and occupancy. A session appears in history when it has a check-in or checkout inside the window. Transfers keep the same session and do not add another entry.
- Replaced visitor identifying details in report history with the session number. The audit response keeps structural metadata and indicates when a reason was recorded. The gate still receives operational details for active visits and reminds officers to return retained IDs on exit.
- PHP lint, JavaScript syntax, and all 16 integration scenarios passed locally against PHP 8.4 and MariaDB 11.8. Chromium verified the report form at 320 px and the audit form at desktop width, including keyboard submission, reset, empty results, date errors and visitor redaction.
- Using a half-open time range makes consecutive calendar windows meet at midnight without counting midnight twice. Fixtures covered leap day, cross-midnight exits, a repeat registration and current occupancy carried in from before the reporting window.

## 20 November 2026 EAT

- Aligned the mobile menu’s visual and keyboard order. Escape returns focus to the menu button, screen changes focus the destination heading, and role-specific controls identify the screens and sections they open. Sign-in errors appear in the form and keep keyboard focus on the submit button.
- Kept map links and focused bay or paging controls usable across refreshes. Paging controls precede the bay grid, touch targets are larger on phones, and booked and closed bays have text labels.
- Added a manual availability refresh. Background polling pauses while the page is hidden and resumes when it returns. A failed refresh keeps the last successful timestamp and map visible. Foreground requests take priority over polls, and stale responses cannot replace newer data.
- PHP lint, JavaScript syntax, and all 14 integration scenarios passed locally against PHP 8.4 and MariaDB 11.8. Chromium checks at 320 px and desktop width exercised guest, student, staff, security and administrator navigation, keyboard focus, pagination, sign-in errors, refresh failure and recovery, and passive polling.
- Keeping a focused control in the DOM, or restoring focus to a named destination when that control disappears, lets users continue from the same place after an asynchronous update.

## 19 November 2026 EAT

- Gate operators can search by normalized registration or exact `#session` number. Check-in and transfer confirmations identify the session or the affected zones.
- A form accepts one submission at a time. Within the transfer or checkout transaction, the API compares the expected zone with the current record so a stale view cannot silently override a move by another operator. Booking and closure examples populate the hosted campus map.
- Added integration assertions for competing cross-zone arrivals, conflicting transfers, repeated corrections, and search results after checkout. PHP lint and the JavaScript syntax check passed locally. The workspace blocked loopback access for the MariaDB suite. The demo workflow runs that suite before opening its public URL.
- A disabled button covers repeat clicks in one tab. The unique active-registration constraint and transactional checks protect the shared records when requests originate in different tabs or at different gates.
