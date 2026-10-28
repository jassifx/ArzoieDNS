# Contributing to ArzoieDNS

Humans and AI contributors use the same engineering rules. Start with [AGENTS.md](AGENTS.md), [HANDOFF.md](HANDOFF.md), and [the project map](docs/project-structure.md). The [architecture](docs/architecture.md) defines the service model; the [security model](docs/security.md) defines trust boundaries. [Installation](docs/installation.md) covers operators and production prerequisites.

## Development environment

Use PHP 8.5.12+ and Composer with PDO, the relevant PDO driver, Sodium and mbstring; install OpenSSL/Argon2 support as described in installation. Install locked dependencies with `composer install`, not an incidental dependency update. Core/view tests do not require a database. Database-backed UI development needs PostgreSQL or MariaDB and a private `.env` copied from `.env.example`.

Use Linux on a supported distribution for infrastructure work. Windows can run PHP unit/view checks and Git Bash mocks, but cannot establish Linux service, BIND or enforcing-policy compatibility. Python 3 is used by the isolated helper-test harness; it is not an application runtime dependency.

For UI development only:

```sh
APP_ENV=development php -S 127.0.0.1:8080 -t public
```

Configure a disposable local database first for authenticated routes. Production must use HTTPS/PHP-FPM and `public/` as its only document root. Never copy production secrets into a development checkout.

## Working on a change

1. State the user-visible outcome and the acceptance criteria. Read existing behavior before editing.
2. Pick a narrow task and identify every consumer of an interface you change. For concurrent contributors, record ownership using [the parallel protocol](docs/ai/parallel-work.md).
3. Keep controllers concerned with HTTP, repositories with persistence, DNS classes with domain rules, services with workflows, and transport/platform classes with infrastructure boundaries.
4. Implement failure handling alongside the successful path. Publication and remote operations must tolerate replay and must not acknowledge unsuccessful deployment.
5. Add tests that exercise relevant behavior and regressions; avoid tests that merely repeat implementation details.
6. Update the documentation that describes changed behavior. Document incomplete work and untested assumptions explicitly.
7. Review the complete diff. Preserve unrelated edits and third-party notices. Do not commit generated tools, keys or runtime data.

## Verification matrix

| Change | Required checks/evidence |
| --- | --- |
| PHP application | `php tests/lint.php`; relevant core tests; `composer validate --strict` if manifest/lock changed |
| Names, records, rendering, sync decisions or credential handling | `php tests/run.php`; targeted regression fixtures |
| Views/controllers/routes | `php tests/views.php`; relevant HTTP authentication/CSRF/error-path checks; manual UI review when layout changes |
| Schema/repositories/jobs | Disposable PostgreSQL **and** MariaDB tests; transaction/concurrency/error behavior; migration compatibility |
| Member helper/bootstrap | `bash -n` on both scripts; `bash tests/member-helper.sh`; real disposable-VM scenarios when infrastructure behavior changes |
| Signing/reconciliation/SSH | Core/mock checks plus real BIND/SSH evidence for changed behavior, or clearly state that integration verification remains unrun |
| Platform/systemd/web/FPM policy | Config syntax checks where available; supported-OS qualification; preserve enforcing MAC and operator access |
| Documentation only | Relative links, referenced paths/commands, tree accuracy, and consistency with current source/status; no application test rerun needed unless code changed |

Common checks:

```sh
composer validate --strict
composer check-platform-reqs
php tests/lint.php
composer test
bash -n resources/member/bootstrap.sh
bash -n resources/member/arzoie-member
bash tests/member-helper.sh
```

On Windows/Git Bash, point `PYTHON` at a real Python executable if the `python3` alias is unavailable. Keep this local override out of committed platform assumptions.

For database tests, use a separately created database ending in `_test`; set `ARZOIE_TEST_DB=1` and connection variables as described in [installation](docs/installation.md#database-tests). The guard is intentional. The test creates its schema and removes its generated rows; it is not a production migration tool. Run the two engines independently and record versions and results.

Do not run bootstrap or deboard scripts against a real server as a "test." Use disposable, explicitly designated infrastructure. Source changes alone do not authorize live deployments, registrar edits, key replacement or production deboarding.

## Review and delivery

The rewritten application is licensed under [Apache License 2.0](LICENSE). Retain applicable author/copyright attribution from [NOTICE](NOTICE) and separate [third-party notices](THIRD_PARTY_NOTICES.md). Contributors may add accurate notices for their own changes without removing applicable existing attribution. Historical versions retain their original licenses. Apache 2.0 requires preservation of applicable notices on redistribution; it does not require every fork to keep the same UI footer.

Describe the problem, resulting behavior, affected interfaces, test evidence, and material limits. Distinguish executed checks from proposed checks. Missing tools or infrastructure should be reported precisely; never convert an unrun check into a pass.

For security-sensitive changes, include how the change preserves credentials, host identity, signer key custody, revision semantics, member privilege boundaries and recovery behavior. Do not attach real credentials or raw diagnostic data containing secrets.

Follow [parallel integration rules](docs/ai/parallel-work.md) when several contributors work simultaneously. Only the designated integrator should edit shared routes/schema/entry-point wiring, shared handoff/task state or `composer.lock` during an active parallel session, unless ownership is explicitly transferred.
