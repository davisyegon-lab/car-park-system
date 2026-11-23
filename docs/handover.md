# Strathmore Smart Parking handover

## Start the demo

1. Check out `main` and follow the [XAMPP setup](../README.md#run-with-xampp). Import `database.sql` into a fresh demo database, then apply `parking-bays.sql`. To keep an existing database’s records, follow the README’s instructions for that database.
2. Open the parking homepage. Use the [fictional demo accounts](../README.md#run-with-xampp). For the hosted release, open the [deployment record](https://api.github.com/repos/teddexter0/car-park-system/deployments?environment=parking-demo&per_page=1), follow `statuses_url`, and use `environment_url` while the latest status is `success`.
3. Use `main` for the updated prototype. The [presentation tag](https://github.com/teddexter0/car-park-system/tree/v0.1.0-demo) points to the original presentation build. For that build, use its own README and a separate checkout and demo database.

## Run the checks

Run these from the repository root with PHP CLI, Node.js, MariaDB, and the PHP PDO MySQL and cURL extensions available.

```sh
php -l api.php
php -l config.php
php -l index.php
php -l tests.php
node --check app.js
php tests.php
```

The test runner connects using `PARKING_DB_HOST`, `PARKING_DB_PORT`, `PARKING_DB_USER`, and `PARKING_DB_PASSWORD`. Its defaults match the local XAMPP setup. The database account needs permission to create and drop the temporary `parking_test_*` schema. The suite seeds and drops that schema and starts PHP web servers on loopback. The [Temporary parking demo workflow](https://github.com/teddexter0/car-park-system/actions/workflows/parking-demo.yml) runs the PHP and JavaScript checks and the MariaDB suite before opening the public URL.

## Acceptance walkthrough

Run the walkthrough against a fresh fictional database. Record the starting Visitor Parking availability before making changes.

| Check | Steps and expected result |
| --- | --- |
| Guest map | Use the keyboard to focus a vacant bay and press Enter twice. The first press simulates arrival and lowers the displayed availability by one. The second returns it to the starting count. A second guest tab still shows the shared count. The starting count is 12 locally and 10 in the hosted seed with C02 booked and C03 closed. |
| Zone administration | As administrator, create an **Acceptance Zone** with capacity 2 and edit it to 3. After the gate flow below, deletion of the zone is rejected because it has session history. Deactivating it after checkout removes it from the public map. |
| Gate lifecycle | As gate officer, check in a fictional visitor with registration `HANDOVER23` to Visitor Parking. Mark the physical ID inspected and retained. Note the session number and verify availability drops from its starting count by one. A second check-in with that registration is rejected. Search for the first session by `#session-number`, transfer it to Acceptance Zone, and record a missed checkout with a reason. The ID return reminder appears and the session leaves the active list. |
| Refresh after a saved entry | With Gate operations already open, block `*api.php?action=dashboard` in browser DevTools and submit the check-in. The gate panel confirms the session number immediately. Remove the block and select **Refresh** in Active sessions to load the updated count and bay map. |
| Reports and audit | As administrator, find the visitor in Reports by session number. The completed run adds one gate entry and one exit to today’s Nairobi totals, and the live occupancy returns to its starting level. Filter the audit log by that session number and confirm vehicle entry, zone transfer, and corrected checkout. Visitor identifying details and the free-text reasons stay out of these views. |
| Session boundary | In a second tab in the same browser profile, open a protected view. Sign out in the first tab and confirm the second tab clears its protected view. On a narrow viewport, use **Menu**, Tab, and Escape to verify the open control regains focus when the menu closes. |

## Verified limits

- The prototype uses mock AMS accounts and gate-entered visit records. The campus plan is illustrative. A gate check-in stores a zone ID, and the map fills the first unreserved bays to reflect the zone’s occupied count. Student and guest interactions simulate sensors.
- The hosted release lets guests use the public map and restricts sign-in to gate officers and administrators. The public URL is temporary for up to five hours and 45 minutes, and each hosted deployment starts with a fresh database.
- Reports use Nairobi calendar days and cap matching session rows at 100. The audit view also caps results at 100 rows. Narrow the date or session filters for older events. Live occupancy and availability are a current snapshot. Report zone filters use the last assigned zone.
- Tested on PHP 8.4 and MariaDB 11.8 locally with Chromium on desktop and at 320 px. The hosted workflow uses PHP 8.2 and MariaDB 10.11. The browser walkthrough used fictional records and tested offline recovery by failing a single availability request.
