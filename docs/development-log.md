# Development log

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
