# ArzoieDNS handoff

Last reviewed: **2026-10-28**. This is the durable entry point for the next human or AI contributor. Read code and Git state before relying on this snapshot. During parallel work, the coordinator is its sole editor.

## Objective and requirements

Build a fully self-hosted authoritative DNS network for an administrator with multiple domains and geographically distributed servers. The selected stack is PHP 8.5.12+, custom MVC, Bootstrap, BIND, DNSSEC, SSH automation, and PostgreSQL/MariaDB. Member policy is EL 10 (Rocky/Alma/Oracle/RHEL) and Debian 13+. Ubuntu is excluded.

The public service must remain independent of the panel/database. Each member serves local signed zones. Only added, changed, deleted or re-signed zones whose desired revision differs from the member acknowledgment are deployed. Onboarding accepts root or sudo-user credentials, installs a managed member, and transitions to key-based restricted administration.

## Repository state

- The former Python/SnitchDNS application and Ubuntu deployment scripts have been replaced in the working tree. The rewritten application is copyright 2026 Jaspreet Singh under Apache License 2.0 with attribution in NOTICE. Historical versions retain their original licensing; third-party components retain their separate notices.
- No legacy database migration exists. No live servers, registrar records, production databases or DNSSEC keys were modified during the rewrite.
- No commit or deployment is implied by this handoff. Inspect `git status --short`; the rewrite and documentation may still be uncommitted.
- Local ignored `.tools/` and `vendor/` may exist from validation. They are not portable dependencies to assume on another machine.

## Implemented source paths

| Area | Entry points | Current behavior |
| --- | --- | --- |
| HTTP/MVC | `public/index.php`, `src/Core/Router.php`, `views/` | Authentication, CSRF, secure-session configuration, escaped local Bootstrap views |
| Domains | `src/Controllers/ZoneController.php`, `src/Models/ZoneRepository.php` | Draft records, explicit publish, immutable revisions, new serials, deletion tombstones |
| DNS rules | `src/Dns/Names.php`, `Record.php`, `ZoneRenderer.php` | Name/RDATA validation and deterministic complete-zone rendering |
| DNSSEC | `src/Dns/Signer.php` | Central stable KSK/ZSK generation, BIND signing/verification, artifacts, DS output |
| Deployment | `src/Services/Reconciler.php`, `src/Dns/SyncPlanner.php` | Per-member revision comparison, SSH staging, validated receipts, targeted operations |
| Jobs | `src/Models/JobRepository.php`, `src/Services/Worker.php` | One worker, retry/backoff, periodic health/reconciliation and snapshot renewal |
| Membership | `src/Services/Provisioner.php`, `src/Infrastructure/` | OS checks, host-key pinning, short-lived encrypted credentials and SSH lifecycle |
| Member helper | `resources/member/` | Fresh-server bootstrap, restricted helper, local validation/load check/rollback, deboarding |
| Reporting | `src/Controllers/DashboardController.php`, `views/reports.php` | Deployment matrix, signature expiry metadata, job history, audit and local health |
| Operations | `resources/systemd/`, `resources/nginx/`, `resources/php/` | Templates requiring environment-specific paths, identities, TLS and policy setup |

Implemented does not mean live-qualified. The code currently has one administrative role and a host-local worker lock, not distributed controller leadership.

## Evidence available

The [validation record](docs/validation.md) describes the rewrite checks: PHP 8.5.12 syntax, ten core test groups, six view-rendering/escaping checks, seven mocked member-helper scenarios, shell syntax, Composer validation/install/platform checks, and basic HTTP security responses passed.

**Not run:** real PostgreSQL or MariaDB integration, successful database-backed HTTP login, real SSH/password/sudo onboarding, BIND signing/validation, supported distro VMs, SELinux/AppArmor behavior, public TCP/UDP DNS, partitions or backup recovery. CI configuration is supplied for a prepared self-hosted runner; no successful CI run is claimed.

The documentation-only handoff work adds a project map, contribution rules and parallel-work protocol. It does not certify additional application capabilities.

## Highest-priority next work

1. **Qualify persistence:** run `tests/database.php` separately on PostgreSQL and MariaDB; test queue claims, retry exhaustion, simultaneous publications and rollback behavior beyond the current fixtures. Add versioned migrations before changing an installed schema.
2. **Qualify real BIND publication:** sign a test zone, serve it on disposable members, verify UDP/TCP and DNSSEC externally, and confirm targeted reload and restart persistence. Test interruption after remote success and before acknowledgment.
3. **Qualify provisioning:** root, sudo password and passwordless sudo on each supported OS; interrupted bootstrap reruns; enforcing MAC policies; account restrictions and access revocation. Do not treat the mocked helper as proof of these properties.
4. **Close correctness/operations gaps:** detect content/receipt drift, recover a missing zone currently marked synced, verify parent DS, implement safe key rollover and actual RRSIG expiry observation.
5. **Complete administration controls:** MFA/RBAC, credential rotation, alerting, report pagination/filtering and recovery workflows.

Full sequencing and acceptance criteria live in [the roadmap](docs/roadmap.md). Task IDs and ownership guidance live in [the task board](docs/ai/tasks.md). The list above is a priority recommendation, not a claim that work is assigned or started.

## Important details easy to miss

- Multiple coding agents are welcome; multiple production workers on different hosts are unsafe with the current lock.
- Re-signing deliberately copies the published snapshot with a newer serial; never accidentally publish drafts from the scheduler.
- A root-owned member receipt rejects decreasing revision IDs. Restoring an older control database requires a deliberate reconciliation plan.
- Signed artifacts expire. An indefinitely offline control plane cannot maintain DNSSEC availability after signatures expire.
- "Synced" is the last acknowledged revision plus local SOA verification, not present external reachability, complete RRset drift verification, or a validated registrar chain of trust.
- Helper rollback is a local previous-file restoration attempt, not an operator-facing historical rollback feature.
- Wheel/sudo membership must not result in broad root access. Deboard retains a deny policy and revokes the enrolled key.
- Bootstrap refuses an existing configured BIND service. Provider firewalls, custom nftables, package subscriptions and trustworthy host fingerprints remain prerequisites.
- `.env.example` describes configuration. Never paste real secrets into handoffs, tickets or task boards.

## Next-session checklist

Read root/scoped `AGENTS.md` → inspect Git state → read architecture/security and the relevant code → select a bounded task → coordinate file ownership → implement and verify → report exact evidence → update handoff through the coordinator.

Use the [handoff template](docs/ai/handoff-template.md) when ending a workstream or context window. Do not silently mark a task complete because its code exists; include evidence and remaining acceptance work.
