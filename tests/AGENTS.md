# Test and evidence instructions

Inherits [root instructions](../AGENTS.md). Tests must prove behavior rather than mirror code or claim unexecuted integration results.

## Existing checks

| File | Scope | Does not prove |
| --- | --- | --- |
| `lint.php` | Syntax of maintained PHP | Runtime behavior, service compatibility |
| `run.php` | DNS validation/rendering/planning, platform policy and credential encryption | Real BIND, SSH or persistence |
| `views.php` | Populated template rendering and output escaping | Browser layout, authenticated end-to-end flows |
| `database.php` | Guarded real-engine repository/publication/transaction fixtures | All concurrency, full queue lifecycle or SSH behavior |
| `member-helper.sh` | Actual helper with isolated filesystem and mocked commands | Root/MAC policy, real validation/locking, network reachability |

Database tests must retain **both** guards: `ARZOIE_TEST_DB=1` and a database name ending in `_test`. Never weaken them for convenience. Use disposable PostgreSQL and MariaDB separately, clean up generated rows, and do not rely on SQLite for dialect/transaction compatibility.

The helper harness modifies privilege/path settings only in a temporary copy. Keep production scripts unchanged by tests. Any cleanup path must stay within test-owned storage. Do not invoke real provisioning or deboard as part of routine tests.

Use synthetic names, documentation IP ranges and generated ephemeral secrets. No captured production credentials, host keys, private signing keys or secret-bearing logs in fixtures.

Add regressions for failures, interrupted work and replay when changing deployment logic. Report exact commands, runtime/engine versions and results. An existing fixture or configured CI job is not a passed run. Historical evidence belongs in a dated validation record; future acceptance criteria belong in the roadmap.
