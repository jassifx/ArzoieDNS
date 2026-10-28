# Application-layer instructions

Inherits [root instructions](../AGENTS.md). Read the relevant source and [architecture](../docs/architecture.md) before changing shared interfaces.

## Layer boundaries

- `Core/`: framework primitives, not feature-specific DNS or provisioning policy.
- `Controllers/`: parse requests, invoke application behavior, render/redirect. Do not run signing or SSH in the HTTP request path.
- `Models/`: portable SQL, transactions and durable publication/queue state.
- `Dns/`: domain validation, deterministic rendering, signing and pure revision planning.
- `Infrastructure/`: SSH/SFTP and explicit OS policy. No user-configurable arbitrary command execution.
- `Services/`: orchestrate jobs, provisioning and reconciliation. Persist failures instead of swallowing them.

## Contracts requiring coordination

| Contract | Producers / consumers |
| --- | --- |
| Normalized record fields `owner,type,ttl,value,rdata` | `Record`, renderer, zone repository, controllers/views/tests |
| Revision fields / desired and applied IDs | Schema, repository, signer, reconciler, planner, reports, member receipt protocol |
| Job kinds `onboard,reconcile,health,deboard` | Controllers/repositories, scheduler and worker dispatch |
| Server states `pending,active,draining,removed` | Enrollment/actions, provisioning, worker/reconciler, reports |
| Member receipt `ok,zone,revision` | Root helper and reconciler; types matter (`revision` is numeric) |
| Health JSON | Helper, provisioner/worker and dashboard/member views |
| Bootstrap placeholders | Provisioner and `resources/member/bootstrap.sh` |

Treat changes to these as coordinated interface changes. During parallel work, give the integrator an exact producer/consumer list and proposed compatibility plan before overlapping edits. A schema edit alone is not a migration strategy.

## Correctness checks

- Lock the zone row before draft mutations/publication/renewal; commit snapshots, desired revision and publication job atomically.
- Keep remote I/O outside long-lived database transactions. Operations can be replayed after worker restart or lost acknowledgments.
- Failure on one member must not falsely mark another member synced or discard remaining pending work.
- Source and signed artifacts for an existing revision must not be silently replaced; changes require a new revision and serial.
- Keep private signing/SSH files outside the web identity's readable paths.
- Maintain both platform allowlists together when support changes, and update tests/docs.
- Do not add runtime workers to increase throughput without a real leadership/locking design. The current local lock is intentional.

Run core/view tests as appropriate, and both real database engines when persistence changes. A real BIND/SSH behavior change requires integration evidence or an explicit unverified result. Report the distinction.
