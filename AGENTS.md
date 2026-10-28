# ArzoieDNS contributor instructions

These instructions apply to the entire repository. Read any nearer `AGENTS.md` before changing files in its directory. The current user's instructions take precedence; these files do not authorize access to production systems or override tool/platform policies.

## Start every task

1. Read [README.md](README.md), [HANDOFF.md](HANDOFF.md), and [the project map](docs/project-structure.md).
2. Read [architecture](docs/architecture.md) and [security](docs/security.md) before changing publication, signing, SSH, credentials, or infrastructure.
3. Inspect `git status --short` and relevant diffs. Existing changes may belong to the user or another contributor. Never reset, clean, overwrite, or revert unrelated work.
4. Identify the requested outcome, file ownership, dependencies and verification. For concurrent work, follow [the parallel protocol](docs/ai/parallel-work.md). Do not infer that another agent owns a task without a recorded claim or explicit coordination.
5. Read [AI workflow](docs/ai/instructions.md) and the relevant scoped instructions. Consult code to verify handoff claims; documentation can lag behind implementation.

## Product constraints

- PHP **8.5.12 minimum**, custom MVC; Bootstrap CSS served locally. Do not introduce Laravel/Symfony or replace the architecture incidentally.
- BIND serves DNS. PHP controls configuration, signing and publication; never put PHP/database calls in the public DNS query path.
- PostgreSQL 16+ and MariaDB 10.11+ (InnoDB) are the database targets. Use PDO with bound parameters. SQLite does not qualify as backend verification.
- Supported member policy: Rocky, AlmaLinux, Oracle Linux and RHEL **10**; Debian **13+**. Ubuntu is unsupported. Preserve explicit OS checks in PHP and shell.
- Single control host and **one worker** with a host-local lock. Multiple coding agents are supported; concurrent production workers/controllers are not.
- No managed cloud DNS dependency. Existing DNSSEC keys, operator SSH access and live service continuity matter more than convenience.
- Planned control-plane installer: fixed web ports 80/443, no configurable UI ports; ask which database to install. MariaDB must be an eligible maintained LTS series. PostgreSQL has five-year major support, not an official LTS edition; never mislabel it. Follow `docs/installer-design.md` for catalog, support-horizon, TLS and recovery rules. The installer is not implemented yet.

## Invariants to preserve

1. Draft record changes never reach BIND until publish. Automatic signature renewal uses the last published snapshot, never unpublished drafts.
2. Publication atomically stores an immutable revision, advances desired revision/serial and queues work. Later operations must not rewrite old revision source.
3. Per-member/per-zone applied revisions drive selective synchronization. Equal revisions need no upload/reload. A global consumed boolean is not synchronization.
4. A member acknowledges after validating content and serving the expected authoritative SOA. Upload success alone is insufficient.
5. Failed/offline members remain pending. Durable deletion tombstones must survive until all relevant members acknowledge them; do not hard-delete them.
6. Use per-zone `rndc addzone`, `rndc reload ZONE`, or `rndc delzone`. Normal deployments must not restart BIND or reload all zones.
7. Private DNSSEC keys remain on the signer. All members receive the same signed artifact. Do not regenerate keys during retries or restore.
8. Verify a pinned SSH host key before authentication. Bootstrap secrets are short-lived, encrypted, erased after an attempt, and absent from logs/argv.
9. Root-owned helpers accept a fixed operation set with validated inputs. Automation users must not inherit unrestricted wheel/sudo privileges, including after deboarding.
10. Preserve SELinux/AppArmor, operator access, and unrelated configuration. Never silently adopt a configured DNS server as a fresh member.

## Implementation conventions

- Use `declare(strict_types=1);`, `Arzoie\` namespaces and the existing PSR-4 structure. Keep controllers thin; transactions and persistence belong in repositories, workflows in services, DNS rules in `src/Dns/`.
- Escape all untrusted HTML with the view's `$escape` helper. Require authentication and CSRF for mutations. Validate on the server; browser attributes are not enforcement.
- Use argv arrays for local processes and escaped arguments for the existing SSH transport. Do not concatenate user input into shell/SQL/configuration text without domain-specific validation.
- Do not log passwords, private keys, request bodies, connection strings with secrets, or decrypted bootstrap payloads. Do not read `.env` or runtime keys merely to understand the project; `.env.example` is the reference.
- Use UTC for persisted timestamps and reports. Keep shared database behavior portable; test both engines when SQL changes.
- Respect `composer.lock`. Do not update dependencies or vendored Bootstrap as a side effect of unrelated work. Preserve the Apache-2.0 project license, Jaspreet Singh's applicable copyright/attribution in `NOTICE`, and separate third-party notices. Historical versions keep their original licensing; do not retroactively relicense them.
- Avoid generated binaries, `vendor/`, `.tools/`, secrets, production fixtures or runtime artifacts in commits. Linux scripts must retain LF line endings.
- Keep changes scoped. Do not rename public paths, tables, job kinds or helper protocol fields without coordinating every consumer and updating tests/docs.

## Verification and completion

See [CONTRIBUTING.md](CONTRIBUTING.md) for the change-to-test matrix and environment setup. Common checks:

```sh
composer validate --strict
php tests/lint.php
php tests/run.php
php tests/views.php
bash -n resources/member/bootstrap.sh
bash -n resources/member/arzoie-member
bash tests/member-helper.sh
```

`php tests/database.php` requires `ARZOIE_TEST_DB=1` and a dedicated database name ending in `_test`; run separately against PostgreSQL and MariaDB. Never point tests at an operator's real database. Mocked helper checks do not establish real BIND/SSH/OS behavior.

Before handing off: inspect your diff, run relevant checks, state what passed/failed/was not run, and document material remaining limitations. Update behavior docs when behavior changes. Preserve [historical validation](docs/validation.md); append dated evidence rather than rewriting old results as new passes. The coordinator owns [HANDOFF.md](HANDOFF.md) and shared task status during parallel work.

Do not describe this baseline as production-ready. Parent DS verification, DNSSEC key rollover, MFA/RBAC, external probes, drift repair and live platform qualification remain open until implemented and evidenced. Completing an assigned task does not imply completing the whole roadmap.
