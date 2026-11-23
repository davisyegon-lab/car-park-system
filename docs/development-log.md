# Development log

## 23 November 2026 EAT

- Ran the hosted-mode walkthrough from the guest map through zone create and edit, visitor entry, duplicate rejection, transfer, corrected checkout, zone deactivation, reports and audit. Visitor Parking started with 10 available bays, fell to nine after entry and returned to 10 after the transfer and checkout. The report gained one entry and one exit. The same session yielded three audit events and no visitor-identifying fields in report history.
- Reproduced a saved check-in that kept saying “Recording the assignment” when the next availability request failed. The gate now shows the confirmed session number immediately and waits for fresh availability before showing the map and count. A subsequent refresh recovered both in Chromium.
- Reconciled the ERD and gate sequence against the actual MariaDB schema and PHP request flow. Both diagrams parsed and rendered in Mermaid. The handover records setup, acceptance steps, the temporary release process and the prototype’s verified limits.
- PHP lint, JavaScript syntax and all 19 MariaDB integration scenarios passed locally. Chromium also passed keyboard and mobile navigation at 320 px, report and audit filtering, cross-tab session handling, and hosted operator workflows with fictional records.
- The database save and the follow-up read can succeed independently. Present the committed session identifier as soon as the write succeeds so a refresh failure does not make an operator think the save is still pending.

## 22 November 2026 EAT

- Enforced action-specific HTTP methods, role checks and JSON object fields on the server. Invalid media types, oversized bodies, unexpected fields, explicit nulls for typed fields and control characters fail before any parking record is written. Editing an inactive zone without an active flag preserves its status.
- Bound authenticated requests to the signed-in account and renewed the session identifier and CSRF token at sign-in and sign-out. Account status changes invalidate prior sessions, even if an account is reactivated before an old tab requests data. The status-change audit record supplies the authorization version using the existing schema.
- Enforced a 30-minute idle limit and an eight-hour absolute lifetime. State checks and automatic dashboard refreshes do not renew the idle timer. Tabs clear drafts and protected views when the account changes. Previously issued sessions require a fresh sign-in.
- PHP lint, JavaScript syntax and all 19 integration scenarios passed locally against PHP 8.4 and MariaDB 11.8. Chromium verified that a stale gate draft could not submit under a replacement account, sign-out cleared another tab’s report, and the hosted gate and bay workflows still worked.
- Rotating a cookie and its request token together matters because browser tabs share cookies but keep their own memory. The old token lets the server identify a stale tab before accepting its request under the new account.

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
