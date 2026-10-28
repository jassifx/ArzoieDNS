# Parallel contributor protocol

Multiple humans/AI agents can develop ArzoieDNS simultaneously. This is a development workflow; it does not enable multiple live workers or controllers. A coordinator/integrator owns shared state and final verification. Do not spawn or delegate work merely because this document exists; follow the user's current instructions and available collaboration policy.

## Roles and write ownership

- **Coordinator/integrator:** defines outcomes, assigns disjoint write sets, owns shared interfaces, maintains task status/HANDOFF, integrates and runs final checks.
- **Workstream owner:** edits only the agreed write set, communicates cross-boundary needs, runs appropriate checks and hands off evidence.
- **Reviewer:** analyzes without editing unless ownership is explicitly assigned. Findings are not automatically permission to overwrite active work.

Every assignment needs an owner, task ID, write paths, acceptance criteria and dependencies. A backlog row is not a lock. The coordinator records an actual claim before parallel edits begin. In a shared checkout, a live assignment can establish ownership; mirror it in the board when useful. Only the coordinator edits the board during that session.

## Candidate workstreams

These are suggested boundaries, **not active assignments**:

| Workstream | Typical write set | Shared interfaces to request from integrator |
| --- | --- | --- |
| Persistence/migrations | `src/Models/`, new DB fixtures | Table/column contracts, transaction semantics, job kinds |
| DNS correctness | `src/Dns/Names.php`, `Record.php`, `ZoneRenderer.php`, dedicated fixtures | Record normalization fields, SOA/source format |
| DNSSEC | `src/Dns/Signer.php`, dedicated signing fixtures | Revision metadata, key lifecycle, artifact/expiry fields |
| Remote member | `resources/member/`, `src/Infrastructure/`, dedicated fixtures | Helper receipt/health schema, SSH behavior, OS profile |
| UI/reports | `views/`, `public/assets/app.css`, dedicated UI fixtures | Controller data contracts, routes and report SQL |
| Integration/recovery | Dedicated integration fixtures and environment manifests | Production source fixes must transfer to its owner or be reassigned |
| Documentation | Assigned documents only | Shared README/HANDOFF/project map updates go through integrator |

Do not assign all of `src/Dns/` to one owner and a file inside it to another. Shared test runners and overlapping fixture files need the same ownership discipline as production files. Use new task-specific fixtures where sensible; the integrator wires shared runners.

## Shared hot spots

By default the integrator owns these during concurrent work:

```text
AGENTS.md and scoped AGENTS.md
README.md, HANDOFF.md, docs/project-structure.md, docs/ai/tasks.md
composer.json, composer.lock, bootstrap.php, public/index.php
src/Models/Schema.php
shared test runner and CI wiring
```

Ownership can be transferred explicitly. A helper-protocol change may cross several otherwise disjoint paths: agree on the contract, split producer/consumer work, then integrate them together. Do not silently change fields/types/commands while another owner implements the previous contract.

## Checkout choices

**Separate worktrees/branches:** preferred for independently reviewable changes when Git operations are authorized and the working baseline is available in Git. The coordinator establishes the integration base first. A new worktree does not automatically include uncommitted files from this checkout; do not branch from the old Python tree and assume the PHP reset is present. Do not copy secrets to worktrees or reset the main checkout to create one. Each worktree uses its own dependencies, temporary state and disposable database.

**Shared checkout:** supported when the environment shares a working tree. File edits are immediately visible. Use disjoint write sets; do not switch branches, run `git reset`/`clean`, broadly format, update dependencies, restore files, or stage/commit everyone else's changes. Review relevant diffs before touching a file. A transient failure caused by an unfinished adjacent change should be reported to its owner rather than overwritten.

The filesystem does not enforce this ownership protocol. Claims and communication prevent collisions; they are not a substitute for isolated worktrees or a real distributed lock.

## Task states

```text
unclaimed → claimed → in_progress → review → integrated
                         ↘ blocked → in_progress
```

`review` means the owner delivered code/evidence, not that integration checks passed. `integrated` requires the integrator to inspect the combined diff and run relevant combined checks. Track release qualification separately; integrated local work may still lack live VM evidence.

## Communication and contract changes

Before changing an interface, send the coordinator:

```text
Task: TASK-ID
Current contract: producer/consumer and fields/types/commands
Proposed change: exact new behavior
Affected paths: ...
Compatibility / rollout: ...
Tests needed: ...
Requested owner transfer or dependent tasks: ...
```

Report an overlap as soon as it appears. Preserve both contributors' work while resolving it; do not use force or resets to "win" a conflict. For an urgent correctness/security finding, document impact and propose a bounded fix; coordinate ownership before overlapping edits.

## Integration sequence

1. Confirm each owner has stopped editing its delivered files or explicitly identify ongoing work.
2. Review changed files, contract compatibility, secret exposure and unintentional modifications.
3. Apply shared wiring/schema/docs changes; resolve conflicts with the relevant owners' intent preserved.
4. Run targeted combined checks, then relevant integration checks. Record unavailable environments explicitly.
5. Update project map, behavior docs, task states and HANDOFF once the combined result is known.
6. Deliver a human-readable summary of behavior, evidence and remaining risk. Commit/publish/deploy only within the user's authorized scope.

Tests run against the same disposable database can interfere. Use per-task database names and isolated runtime paths. Do not run two workers against shared production state for development testing.
