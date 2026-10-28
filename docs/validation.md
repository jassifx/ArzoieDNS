# Initial rewrite validation — 2026-10-28

Executed locally with the official SHA256-verified Windows PHP 8.5.12 CLI build. Test binaries, Composer downloads, caches and dependencies are kept in ignored `.tools/` and `vendor/`; they are not part of the application source.

| Check | Result |
| --- | --- |
| PHP syntax checks across application, views, CLI and tests | Passed |
| Domain/owner validation, typed records, TXT escaping, RRset/CNAME rules | Passed |
| Deterministic zone rendering and minimum NS redundancy | Passed |
| Selective sync decisions, including offline deletion tombstones | Passed |
| Explicit OS allowlist and Ubuntu rejection | Passed |
| Sodium credential encryption/tamper rejection | Passed |
| Ten standalone domain/security test groups total | Passed |
| Six UI view rendering/XSS-escaping checks | Passed |
| Seven member-helper tests using mocked BIND/system commands | Passed |
| Shell syntax for bootstrap and member helper | Passed (Git Bash) |
| Composer manifest, lock consistency and platform requirements | Passed |
| Composer dependency resolution/install | Passed; lockfile generated |
| Dependency advisory check during Composer update | No advisories reported at resolution time |
| HTTP login page and locally served Bootstrap CSS | 200 responses |
| Unauthenticated protected route | Redirected to login |
| Invalid CSRF POST | 403 response |
| `.env` request under public document root | 404 response |
| Security headers and HttpOnly/SameSite session cookie | Observed |

The member-helper tests run the actual helper source against a temporary filesystem with mocked `rndc`, `dig`, signature/zone validators, ownership changes, and locking. They verify targeted add/reload, checksum rejection before mutation, stale/path input rejection, previous-file restoration after load failure, repeatable deletion, and absence of global reload. They do **not** establish real BIND validation, locking semantics, root privileges or MAC-policy compatibility.

The UI tests render populated views and exercise real HTTP middleware without a database-backed successful login. Production HTTPS/FPM has not been exercised.

Not executed locally: PostgreSQL/MariaDB integration (services unavailable), real SSH/password/sudo onboarding, BIND/DNSSEC tools, VM provisioning, SELinux/AppArmor, DNS over public UDP/TCP, multi-region partitions, or recovery drills. Database tests and a self-hosted CI matrix are supplied, but their presence is not evidence that they passed. See `roadmap.md` for release acceptance gates.

## Contributor documentation update — 2026-10-28

Added root and scoped `AGENTS.md`, human/AI handoff and contributing guides, a full source/runtime map, documentation navigation, AI task/handoff instructions, parallel ownership rules, an initially unclaimed backlog, and a pull-request template. Updated README and changelog to link the new material.

Verified maintained Markdown links/anchors and referenced files against the working tree, checked the project map and CLI/test names against source, and ran `git diff --check`. Documentation remains explicit about unverified live infrastructure. No application code changed in this update; application tests were not rerun or newly claimed as passed.

## Project licensing update — 2026-10-28

Replaced the current root license with the official Apache License 2.0 text, added NOTICE crediting Jaspreet Singh, updated Composer metadata and current licensing documentation, and added the author/license credit to the shared UI footer. Historical versions in Git were not relicensed. Bootstrap and dependency licenses remain separate.

Executed: `composer validate --strict`, PHP syntax check for `views/layout.php`, all six view rendering/escaping checks, stale current-MIT-claim search, and `git diff --check`; all passed. The Apache license text was retrieved from `https://www.apache.org/licenses/LICENSE-2.0.txt`. Applicable redistribution attribution is required under Apache 2.0; an unremovable footer or mandatory credit on every private/network-only use is not claimed.
