# Changelog

## 0.1.0 — repository reset (2026-10-28)

- Replace the SnitchDNS/Python application with a PHP 8.5.12 custom MVC control plane.
- Delegate authoritative DNS to BIND, with centralized pre-signing and SSH publication of local signed zones.
- Add draft records, immutable revisions, per-server acknowledgments, deletion tombstones, retries, and signature renewal of published snapshots.
- Add member lifecycle workflows, explicit EL 10/Debian 13+ policy, SSH fingerprint pinning, encrypted short-lived bootstrap secrets, and restricted automation privileges.
- Add a local Bootstrap UI, deployment reports, health summaries and audit history.
- Add PostgreSQL/MariaDB schemas, operational templates, tests and architecture/security/deployment documentation.
- Add root/scoped contributor instructions, human/AI handoff, full project map, contribution guide, parallel ownership protocol, unclaimed task backlog and review/handoff templates.
- License the rewritten application under Apache License 2.0, copyright 2026 Jaspreet Singh, with author attribution in NOTICE. Historical versions and third-party components retain their original licensing. No existing live servers are changed and no legacy data migration is supplied.

This initial implementation requires live OS/database/BIND/SSH qualification. Parent DS verification, automated key rollover, MFA/RBAC, external probes and additional recovery hardening remain planned work; see `docs/roadmap.md`.
