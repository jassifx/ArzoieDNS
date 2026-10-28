# Complete project map

This map covers maintained source, configuration, tests and contributor documentation. Ignored dependencies and runtime directories are described separately. It is a map of the current baseline, not a list of planned features that already exist.

```text
ArzoieDNS/
├── AGENTS.md                     Repository-wide contributor/AI rules
├── HANDOFF.md                    Current state, evidence, gaps and next work
├── CONTRIBUTING.md               Human/AI development and review workflow
├── README.md                     Product overview and entry points
├── CHANGELOG.md                  Release/reset history
├── LICENSE                       Apache License 2.0 for the rewritten application
├── NOTICE                        Copyright and author credit: Jaspreet Singh
├── THIRD_PARTY_NOTICES.md         Vendored/dependency attribution
├── .env.example                  Non-secret configuration reference
├── .gitignore                    Dependency/tool/runtime/secret exclusions
├── .gitattributes                Linux line endings and vendored CSS metadata
├── composer.json                 PHP requirements, PSR-4, scripts
├── composer.lock                 Resolved dependency versions
├── bootstrap.php                 Runtime gate, autoload, environment, UTC/umask
├── bin/
│   └── arzoie                    Administrative and worker CLI
├── public/                       ONLY production document root
│   ├── index.php                 HTTP entry point, session/security setup, routes
│   └── assets/
│       ├── app.css               Console styling and responsive layout
│       ├── bootstrap.min.css     Locally served Bootstrap CSS
│       └── bootstrap.LICENSE     Bootstrap license
├── src/
│   ├── AGENTS.md                 Application-layer and contract rules
│   ├── Core/                     Small custom framework primitives
│   ├── Controllers/              HTTP adapters
│   ├── Models/                   Database schema and repositories
│   ├── Dns/                      DNS rules, rendering, signing, planning
│   ├── Infrastructure/           OS profiles and SSH/SFTP transport
│   └── Services/                 Provisioning, reconciliation and worker orchestration
├── views/                        Escaped PHP templates; no persistence/SSH
├── resources/
│   ├── AGENTS.md                 Infrastructure/helper safety and protocol rules
│   ├── member/
│   │   ├── bootstrap.sh          Fresh-member installation template
│   │   └── arzoie-member         Root-owned restricted BIND helper
│   ├── nginx/arzoiedns.conf      HTTPS/private-management vhost template
│   ├── php/arzoiedns-fpm.conf    Separate web identity and FPM pool template
│   └── systemd/
│       ├── arzoiedns-worker.service
│       ├── arzoiedns-scheduler.service
│       └── arzoiedns-scheduler.timer
├── tests/
│   ├── AGENTS.md                 Evidence, fixture and environment rules
│   ├── lint.php                  Syntax-check maintained PHP
│   ├── run.php                   Standalone DNS/security/planning tests
│   ├── views.php                 Render/XSS-escaping checks for six views
│   ├── database.php              Guarded disposable-engine integration tests
│   └── member-helper.sh          Actual helper with mocked system/BIND commands
├── storage/.gitkeep              Empty local artifact placeholder
├── docs/
│   ├── README.md                 Documentation navigation and truth hierarchy
│   ├── project-structure.md      This map and code ownership boundaries
│   ├── architecture.md           Data model, publication and lifecycle design
│   ├── security.md               Trust, privileges, secrets and limitations
│   ├── installation.md           Operator setup, deployment and recovery
│   ├── installer-design.md       Planned automated installation, DB lifecycle and TLS
│   ├── roadmap.md                Delivery stages and acceptance gates
│   ├── validation.md             Dated executed checks and explicit limits
│   └── ai/
│       ├── instructions.md       AI task lifecycle and context discipline
│       ├── parallel-work.md      Ownership, interface and integration protocol
│       ├── tasks.md              Stable backlog IDs and coordination table
│       └── handoff-template.md   Per-task/session handoff format
└── .github/
    ├── pull_request_template.md  Change/evidence/limits review template
    └── workflows/checks.yml      Trusted self-hosted checks with both DB services
```

## Framework and application classes

| Path under `src/` | Responsibility |
| --- | --- |
| `Core/Env.php` | Load simple `.env` configuration without replacing process-provided values |
| `Core/Database.php` | PDO connection, prepared execution and transactions |
| `Core/Crypto.php` | Authenticated encryption of queued bootstrap credentials |
| `Core/Process.php` | Bounded local argv-based process execution |
| `Core/Audit.php` | Administrative/worker event persistence |
| `Core/Router.php` | Route lookup, authentication gate, CSRF checks, redirects |
| `Core/View.php` | Template rendering, layout and escaping helper |
| `Controllers/AuthController.php` | Sign-in/out and per-account lockout behavior |
| `Controllers/DashboardController.php` | Overview and report queries |
| `Controllers/ZoneController.php` | Domain/record HTTP actions and publication requests |
| `Controllers/ServerController.php` | Enrollment, credential retry, health/sync/drain/deboard actions |
| `Models/Schema.php` | Initial portable table creation; not versioned upgrades |
| `Models/ZoneRepository.php` | Draft persistence, publication transactions, tombstones and published-snapshot renewal |
| `Models/JobRepository.php` | Queue insertion, claim, completion and retry scheduling |
| `Dns/Names.php` | Domain/owner normalization and zone containment |
| `Dns/Record.php` | Typed RDATA normalization and RRset/CNAME validation |
| `Dns/ZoneRenderer.php` | Deterministic unsigned BIND zone with coherent SOA/NS checks |
| `Dns/SyncPlanner.php` | Pure decision: none/add/update/delete for desired/applied revisions |
| `Dns/Signer.php` | BIND key/sign/verify commands and immutable signed artifact metadata |
| `Infrastructure/Platform.php` | Explicit supported-OS profile policy |
| `Infrastructure/SshTransport.php` | Ed25519 host-key pinning, SSH/SFTP and bootstrap escalation channel |
| `Services/Provisioner.php` | Encrypted credential use, OS preflight, bootstrap expansion and active transition |
| `Services/Reconciler.php` | Sign desired revisions, deploy missing revisions, validate receipts, persist acknowledgments |
| `Services/Worker.php` | Single-worker lock, job dispatch/recovery, maintenance scheduling |

## Views

`layout.php` provides navigation, security-compatible local assets, flash messages and the common page shell. `login.php` handles sign-in. `dashboard.php` displays network summaries. `zones.php` manages domain/record drafts and signing details. `servers.php` exposes membership operations. `reports.php` displays revision status, jobs and audit. `error.php` shows safe operation failures.

## Request and command flows

```text
HTTP request → public/index.php → Router → Controller
                                      → Repository/Database or queued job
                                      → View → layout → response

bin/arzoie worker → Worker → Provisioner or Reconciler
Reconciler → Signer → SshTransport → arzoie-member → BIND
bin/arzoie schedule → queue health/reconcile + renew published snapshots
```

The CLI currently exposes `key:generate`, `migrate`, `user:create EMAIL`, `ssh:keygen`, `worker [--once]`, `schedule`, and `jobs:retry ID`. See `bin/arzoie` for exact behavior; do not invent additional commands from roadmap ideas.

## Database entities

`users`, `zones`, `records`, `zone_revisions`, `servers`, `deployments`, `jobs`, and `audit_events` are created by `Schema`. `records` are drafts. `zone_revisions` contain publication snapshots. `deployments` are acknowledgments for a particular member/zone pair. No API-token, RBAC, rollover-state, external-probe or migration-history tables exist yet.

## Runtime locations and ignored directories

| Location | Owner / purpose |
| --- | --- |
| `.env` | Private control-plane config; never commit |
| `vendor/` | Composer-installed libraries; rebuild from lock |
| `.tools/` | Optional ignored local tools/cache; not a deployment dependency |
| `STATE_PATH` | Worker-owned state and the host-local worker lock |
| `SIGNER_PATH/keys/DOMAIN` | Private central KSK/ZSK material |
| `SIGNER_PATH/artifacts/REVISION` | Unsigned snapshot and signed zone artifact |
| `SSH_KEY_PATH` and `.pub` | Worker fleet enrollment identity |
| `/var/lib/arzoiedns-web-sessions` | Separate web-owned FPM session path in the supplied template |
| Member `/etc/arzoiedns/` | Root-controlled config, RNDC key, bootstrap markers |
| Member `/var/lib/arzoiedns/incoming/` | Untrusted automation-owned SFTP staging |
| Member `/var/lib/arzoiedns/zones/` | Root-installed signed zone files and previous-file copy |
| Member `/var/lib/arzoiedns/receipts/` | Root-owned revision/active receipts |
| Member `/var/lib/arzoiedns/runtime/` | BIND-owned runtime and persistent dynamic-zone data |

Do not create empty speculative module trees to imply future capabilities. Add modules with their implementation/tests and update this map when responsibilities or paths change.
