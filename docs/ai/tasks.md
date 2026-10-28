# Task board and backlog

Snapshot: **2026-10-28**. This initial board has **no active owner claims**. Tasks below are suggested bounded work items derived from the roadmap; they have not been delegated, started or completed by their presence here. During a parallel session, the designated coordinator is the sole editor of ownership/state fields.

## Coordination fields

Use stable task IDs. A real assignment must record owner, status, write set, dependencies and evidence location. Do not put credentials, private hosts or secret-bearing logs here.

| ID | Outcome / acceptance | Candidate write scope | Dependencies | Owner | State |
| --- | --- | --- | --- | --- | --- |
| INS-01 | Finalize installer contracts, fixed 80/443 wizard, signed release/DB lifecycle catalog and recovery journal | New installer contract/fixture paths; shared policy via integrator | [Installer design](../installer-design.md) | — | unclaimed |
| INS-02 | Qualified PHP/OS adapters and explicit eligible MariaDB/PostgreSQL local installation | Assigned platform/database installer modules and disposable-VM fixtures | INS-01, DB-01; supported OS access | — | unclaimed |
| INS-03 | Fixed-port Nginx/FPM, Let's Encrypt bootstrap, redirect policy and independent renewal | Assigned web/TLS installer modules and fixtures | INS-01, INS-02; broker contract coordinated | — | unclaimed |
| INS-04 | In-app certificate status/provider/custom import via restricted broker | Assigned certificate service/UI/fixtures; schema/routes via integrator | INS-03; protected secrets and reauthentication | — | unclaimed |
| INS-05 | Interrupted/repeated install and certificate recovery qualification across OS/DB matrix | Assigned installer integration fixtures and dated evidence | INS-02, INS-03, INS-04 | — | unclaimed |
| DB-01 | Run current repository fixtures on PostgreSQL and MariaDB; record versions, failures and fixes | `tests/database.php`, dedicated DB fixtures; request repository changes from owner | Disposable engines; coordinator controls schema changes | — | unclaimed |
| JOB-01 | Prove queue claim/retry/restart behavior and publication concurrency with real engines | Dedicated queue/concurrency fixtures, `JobRepository.php`, `Worker.php` by explicit assignment | DB-01; interfaces coordinated | — | unclaimed |
| MIG-01 | Versioned migrations with locking and upgrade/restore acceptance | New migration module/fixtures; schema/bootstrap wiring by integrator | DB-01; approved migration contract | — | unclaimed |
| BIND-01 | Real signed test zone passes validation, targeted load and reboot persistence | Dedicated BIND integration fixtures | Disposable supported Linux/BIND; do not own helper source implicitly | — | unclaimed |
| SSH-01 | Root/sudo/passwordless-sudo onboarding, fingerprint rejection and interrupted reruns qualify | Dedicated provisioning fixtures; assigned transport/bootstrap changes | Disposable members, supported OS matrix | — | unclaimed |
| OS-01 | Qualify EL 10 variants and Debian 13 with enforcing MAC and restricted helper access | Dedicated platform fixtures/results | SSH-01, BIND-01; distro access | — | unclaimed |
| SYNC-01 | Reconcile lost receipts, missing served zones and content drift without global reload | `Reconciler.php`, dedicated integration fixtures; helper contract via integrator | BIND-01; agreed inventory protocol | — | unclaimed |
| SEC-01 | Observe parent DS and actual signature validity; distinguish signed from chain-validated | Assigned DNSSEC observation module/fixtures | BIND-01; report/schema interfaces coordinated | — | unclaimed |
| SEC-02 | Safe KSK/ZSK lifecycle and rollover acceptance through cached DS/DNSKEY windows | Assigned signing lifecycle module/fixtures | SEC-01, MIG-01; separate design review | — | unclaimed |
| AUTH-01 | MFA/RBAC and recovery model implemented with access-control tests | Assigned auth/security files and fixtures | MIG-01; routes/schema/UI contracts coordinated | — | unclaimed |
| UI-01 | Report filters, pagination and clear draft/published state with accessible UI | Assigned views/CSS and UI fixtures | Controller/query changes via integrator | — | unclaimed |
| OPS-01 | Restore drill preserves keys, rejects stale replay and resumes safe publication | Dedicated recovery fixtures/runbooks | DB-01, BIND-01; isolated recovery environment | — | unclaimed |

These scopes are starting points, not blanket permissions. Refine them before assignment so two tasks do not own the same file. See [parallel protocol](parallel-work.md) for shared hot spots.

## Claim template

```text
ID:
Owner:
Status: claimed / in_progress / blocked / review / integrated
Outcome and acceptance:
Write paths:
Read-only dependencies:
Shared interfaces / integrator work:
Depends on:
Environment and checks:
Evidence / handoff:
Last updated (UTC):
```

When work is integrated, retain its ID and evidence link in an integrated-work log or dated handoff. Do not rewrite unverified release criteria as passed simply because a local task finishes.
