# Delivery plan and release gates

## Current baseline

The repository now contains the replacement MVC application, selective revision publication, BIND signing/SSH provisioning workflows, initial schema, local Bootstrap UI, operational templates and automated checks. These are implemented source paths, not a declaration of live deployment readiness.

The original Python app and Ubuntu scripts were removed. This reset does not migrate old users, passwords, zones, query logs or infrastructure. Existing live infrastructure is untouched.

## Stage 1 — qualify the baseline

- Run both database backends against real services: PostgreSQL 16+ and MariaDB 10.11+ with InnoDB, native prepared statements and strict SQL mode.
- Exercise root, sudo-password and passwordless-sudo bootstrap, wrong password, wrong fingerprint, partial package failure, interrupted SSH and credential expiry. Prove secrets never enter command arguments or logs.
- Qualify fresh and interrupted/rerun bootstrap on Rocky 10, AlmaLinux 10, Oracle Linux 10, subscribed RHEL 10 and Debian 13. Future Debian versions must pass the same suite even though the version gate allows them.
- Test SELinux enforcing/AppArmor, RNDC policy, wheel/sudo restrictions, BIND restart persistence, UDP/TCP 53 and IPv4/IPv6 connectivity.
- Verify BIND version/tool output compatibility; choose and enforce a production BIND release baseline after OS package qualification.

Acceptance: three independent VMs can be enrolled, receive identical signed zones, reboot and keep serving while the panel/database are stopped.

## Stage 2 — publication and recovery hardening

- Add real BIND fixtures for NXDOMAIN/NODATA, wildcard/empty-nonterminal behavior, CNAME, delegated child zones, glue, long TXT and DNSSEC validation.
- Test bulk RRset edits, malformed data, full disks, missing files, bad signatures, upload interruption, crash after addzone/reload and lost acknowledgments.
- Add external authoritative UDP/TCP probes, RRset/hash drift detection, member receipt inventory reconciliation, and repair when a supposedly synced zone disappears.
- Extend rollback receipts/history and add operator-controlled content rollback that always produces a newer serial/revision.
- Add versioned numbered schema migrations, migration locking and backup/restore tests.
- Bound signing/upload sizes and all timeouts; load test high zone/member counts. Add pagination and filters to the initial fixed-size report history.

Acceptance: only changed zones reload; unaffected members/zones receive no unnecessary transfers; offline members catch up; deletion tombstones survive outages; no stale revision can overwrite a newer one.

## Stage 3 — complete DNSSEC operations

- Implement explicit key lifecycle states, ZSK prepublication, KSK rollover overlap, parent DS observation and retirement gates.
- Report earliest actual RRSIG expiration per zone/member, renewal failures, chain-of-trust status and clock skew.
- Validate DS at the parent before marking a domain secure. Add deliberate DNSSEC disablement workflow with parent DS removal gates.
- Separate signer service/credential access and optionally integrate an HSM or offline KSK ceremony.

Acceptance: first signing, renewal, restore and key rollovers preserve validating resolver availability through cached DNSKEY/DS/signature windows.

## Stage 4 — administrator and fleet security

- TOTP/WebAuthn MFA, recovery workflows, per-zone RBAC, read-only roles and authenticated scoped API tokens.
- Reverse-proxy and application source rate limits; session revocation and administrator/password management.
- Per-member SSH keys and key rotation; forced-command upload/control protocol; detached signed deployment manifests.
- Verify delegation removal and wait TTLs from observed data rather than relying only on operator acknowledgments. Add drain cancellation and safe reenrollment after removal.
- Tamper-evident remote audit export, report downloads, webhook/email alerts and retention policies.

Acceptance: access-control tests cover every route and mutation, secrets remain absent from logs/backups exported without keys, and compromise of one member cannot access another member or private signing material.

## Stage 5 — release operations

The [installer design](installer-design.md) defines the automated setup workstreams INS-01 through INS-05: fixed ports 80/443, a database-engine interview with long-term maintenance policy, safe PHP-FPM/Nginx provisioning, and independent certificate lifecycle management. These are planned, not completed features.

- Reproducible locked dependencies and vulnerability checks on every release; signed release artifacts and upgrade rollback procedures.
- CI database and disposable-VM matrix; scheduled recovery drills and public delegation/DNSSEC checks.
- Separate control-plane installer/package pipeline with approved PHP 8.5 repositories/builds, TLS setup and SELinux/AppArmor policy qualification.
- Optional standby control-plane recovery with explicit worker fencing; distributed leadership is required before concurrent controllers are supported.

No production-ready label until stages 1–3 and the necessary administrator controls pass. Keep warnings about unimplemented parent validation, key rollover, external health and single-host worker leadership visible until those capabilities exist and are tested.
