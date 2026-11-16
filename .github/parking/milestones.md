# November 17–23 development brief

Build on the checked-out branch and read README.md before making changes. Keep the PHP, vanilla JavaScript and MariaDB stack, first-come-first-served parking, gate-recorded occupancy, and fictional demo data. Treat v0.1.0-demo as the fixed presentation release.

Only implement today's row. Check for existing implementations, especially if a previous run was retried. Treat the target as a planning budget. Make each commit an independently valid change with a specific purpose. Combine related changes when splitting would leave an incomplete or failing commit.

| Nairobi date | Target | Work |
| --- | --- | --- |
| 2026-11-17 | 5 | Establish repeatable acceptance checks for authentication, zone CRUD and the gate lifecycle. Improve setup diagnostics, document the exact demo run and show how each acceptance result was measured. |
| 2026-11-18 | 4 | Improve zone administration feedback, capacity validation and deactivation flows. Cover zone lifecycle failures and dependency preservation in integration tests. |
| 2026-11-19 | 4 | Improve gate lookups, protection against repeat submissions, transfer feedback and exception handling. Exercise conflicting arrivals and correction paths with repeatable tests. |
| 2026-11-20 | 4 | Improve role-based navigation, keyboard and mobile usability, and dashboard refresh behavior. Verify accessibility interactions and error feedback where the environment permits. |
| 2026-11-21 | 5 | Improve audit and report filters, explain reporting time windows and how occupancy differs from entries, preserve visitor privacy, and add tests for boundary conditions. |
| 2026-11-22 | 4 | Harden request validation, role authorization and session boundaries. Expand regression checks for specific failure cases while preserving the demo flow. |
| 2026-11-23 | 4 | Run end-to-end acceptance, fix observed regressions, reconcile the ERD and sequence diagram with the code, and document handover steps and verified limits. |

Keep workflows, git configuration, credentials and unrelated files out of your changes. Keep the compatibility of the existing seeded database and avoid unrelated refactors. Play out the requested use cases against actual code and report any test the environment could not run. The pipeline independently lints and tests each proposed commit against MariaDB before publication.

Add or update `docs/development-log.md` with an entry for this Nairobi date. Record implementation decisions, observed verification and one concrete software engineering learning outcome. Include the log in its related commit.

Write a temporary file `.parking-commit-plan.json` at repository root. It must be a JSON array such as `[{"message":"Validate zone capacity in the admin form","paths":["app.js","docs/development-log.md"]}]`. Give every changed path to exactly one group, and order groups so each group works on top of the previous one. Where multiple changes touch the same file, package them as one coherent commit. Keep this temporary plan out of the committed files. Leave Git staging, commits and publication to the workflow.
