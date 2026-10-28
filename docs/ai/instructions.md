# AI contributor workflow

This document complements [AGENTS.md](../../AGENTS.md); it does not replace scoped instructions, user direction or tool/platform policies. Humans can use the same process.

## Orient before editing

Read [HANDOFF.md](../../HANDOFF.md), [the project map](../project-structure.md), and the relevant source. Check Git status/diffs to distinguish existing work from your changes. Read architecture/security for infrastructure, persistence, signing or authentication work. Use `.env.example` for configuration understanding; real secrets are unnecessary for source review.

Do not infer capability from a filename or README bullet alone. Trace the route/command to its implementation and test. The repository is a substantial initial implementation with explicit live-integration gaps, not a completed production platform.

## Form a bounded work item

Record:

- User-visible outcome and acceptance criteria.
- Task ID or descriptive identifier, owner, and permitted write paths.
- Read-only dependencies and any shared interface change.
- Required checks, environment needs, and expected limitations.
- Integration dependencies and documentation owner.

Use [the task board](tasks.md) and [parallel protocol](parallel-work.md) when several contributors work. Claims are actual coordination records, not inferred locks. If ownership conflicts, continue non-overlapping analysis and resolve the boundary before editing the same files.

## Implement

Keep the current PHP/MVC/BIND/SSH architecture. Preserve the publication, credentials and privilege invariants in root instructions. Prefer small reviewable changes tied to acceptance criteria. Do not mix unrelated refactors, dependency updates, file moves or formatting churn into a workstream.

When a change spans interfaces, describe producer and consumer changes first. Coordinate route/schema/protocol/lockfile edits through the integrator. No agent should "fix" another contributor's unfinished work without communicating; local files may be temporarily inconsistent during integration.

Do not use mocked results to justify bypassing host pinning, encryption, OS checks, root-helper validation or database guards. Missing tools justify an explicit unrun result, not weaker controls. Do not add speculative modules, fake reports or hardcoded healthy status to make the UI look complete.

## Verify

Use [the contribution matrix](../../CONTRIBUTING.md#verification-matrix) to select checks. Run targeted checks during development and integration checks after shared wiring settles. Do not repeatedly run broad tests without a new change/failure/concern.

Record command, environment/version, result, and what the check does not establish. Preserve logs without secrets. If a check is unavailable, state the missing dependency/service and provide a reproducible next command with safe placeholders.

For documentation-only work, validate links, paths, claims, tree accuracy and commands against source. Do not claim application tests were rerun unless they were.

## Hand off

Use [the handoff template](handoff-template.md). Include exact owned files, contract changes, evidence, unresolved risks and next action. Distinguish:

- **Implemented:** a source path exists for the behavior.
- **Verified locally:** a stated check actually ran in the stated environment.
- **Integration-qualified:** the real engine/service/platform scenario passed.
- **Planned:** specified but not delivered.

The coordinator updates root HANDOFF/task status after integration. Update behavior documentation with code changes, but avoid simultaneous rewrites of shared entry documents. Never claim a commit, deployment, VM qualification or production readiness without evidence.

## Context-window or session continuity

Before losing context, leave a concise handoff with working-tree state, current hypothesis, files changed, checks already run, and the next useful action. Include unresolved user constraints and ownership. Do not restart completed work after resuming; verify the snapshot and continue.

Do not store chat transcripts as the authoritative specification. Stable requirements belong in AGENTS/architecture/security; current status belongs in HANDOFF; executed evidence belongs in validation; task assignments belong in the coordination record.
