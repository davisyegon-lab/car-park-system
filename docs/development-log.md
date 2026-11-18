# Development log

## 19 November 2026 EAT

- Gate operators can search by normalized registration or exact `#session` number. Check-in and transfer confirmations identify the session or the affected zones.
- A form accepts one submission at a time. Within the transfer or checkout transaction, the API compares the expected zone with the current record so a stale view cannot silently override a move by another operator. Booking and closure examples populate the hosted campus map.
- Added integration assertions for competing cross-zone arrivals, conflicting transfers, repeated corrections, and search results after checkout. PHP lint and the JavaScript syntax check passed locally. The workspace blocked loopback access for the MariaDB suite. The demo workflow runs that suite before opening its public URL.
- A disabled button covers repeat clicks in one tab. The unique active-registration constraint and transactional checks protect the shared records when requests originate in different tabs or at different gates.
