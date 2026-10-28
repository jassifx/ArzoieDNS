# Automated installation and certificate management design

Status: **planned, not implemented**. Updated 2026-10-28. This specification incorporates fixed web ports 80/443, an explicit database choice, long-term database maintenance, and later certificate administration from the application. The existing manual installation instructions remain the executable procedure until these stages pass qualification.

## Scope and decisions

- One operator-facing installation script installs the control plane on a fresh supported server. Internally it delegates to versioned, testable modules rather than one large shell script.
- Support Rocky/AlmaLinux/Oracle Linux/RHEL 10 and Debian 13+. Reject Ubuntu before changes. Retain SELinux/AppArmor enforcement.
- Web listeners are **TCP 80 and TCP 443 only**. There is no port prompt, alternate-port flag, database setting or UI setting for web ports. Database ports and member SSH ports remain separate connection parameters.
- HTTPS is the only authenticated administration transport. Ask whether normal HTTP requests should redirect to HTTPS. If not, HTTP serves only ACME challenge files and a fixed non-application response, or is closed when challenges do not need it.
- Ask which database to install: **MariaDB LTS** or **PostgreSQL maintained stable major**. MariaDB is the default when the operator wants an explicitly designated LTS edition. An advanced existing-database connection path is separate from local installation.
- Keep BIND member provisioning separate. Installing the panel installs signing tools, not a public DNS listener on the control host. A combined control/member role is not part of the first installer.
- Initial automated public TLS uses Let's Encrypt. ZeroSSL and custom-certificate administration are a later application feature built on the same certificate activation protocol, not separate ad hoc Nginx editors.
- No installer can promise perfection. Release acceptance requires disposable-machine tests, interrupted-run recovery and explicit evidence for every supported platform/backend combination.

## Database lifecycle policy

"LTS" is not interchangeable with "latest stable." MariaDB distinguishes long-term and rolling releases. PostgreSQL has no separately named LTS edition; its project supports each major for five years. Do not label PostgreSQL as an official LTS product. Sources: [MariaDB maintenance policy](https://mariadb.org/about/#maintenance-policy), [PostgreSQL versioning policy](https://www.postgresql.org/support/versioning/).

The installer ships a versioned, signed release catalog with tested database series, OS/architecture package mappings, repository signing fingerprints, verified maintenance endpoints and a review/expiry date. It must not scrape a download page or select a floating `latest` package as root.

Selection rules:

1. MariaDB candidates must be designated LTS, generally available, qualified with ArzoieDNS, and receiving public security/package updates through the selected distribution channel. Rolling, preview and end-of-life series are rejected.
2. PostgreSQL candidates must be generally available, qualified and within their official five-year major support window. Preview/end-of-life majors are rejected. If a formally named LTS edition is mandatory for a deployment, only MariaDB is eligible for local installation.
3. For fresh installation, require at least **24 months of remaining verified security-maintenance coverage** from the chosen channel. Do not count paid enterprise coverage unless that channel and entitlement are explicitly configured. Source-only maintenance does not automatically establish availability of security-fixed packages.
4. Choose the preferred qualified series from that catalog, then the latest available security/bugfix package within that series from the approved signed repository. Validate the actual installed server version afterward.
5. If the catalog is stale, the series lacks enough maintenance runway, or repositories cannot supply an eligible version, stop with a precise reason. Never silently fall back to an older/non-LTS MariaDB series.
6. Pin the series. Patch updates stay within it; major upgrades and engine switches are separate backed-up migration workflows. Rerunning installation never switches the user's engine or regenerates credentials.
7. Existing databases undergo version, engine, schema, privilege and maintenance checks. An ineligible existing server is reported without being upgraded or overwritten.

Current `PostgreSQL 16+` / `MariaDB 10.11+` compatibility minimums are not fresh-installer recommendations. A version can meet the application's syntax minimum and still fail this maintenance-horizon policy. The catalog must record verified dates, not permanently hardcode today's newest series into shell code.

## Operator interview

Collect and validate inputs before destructive/configuration changes. Hide secrets, avoid shell history/argv, and present the resulting plan before applying it. The apply action authorizes that concrete plan; unattended runs use an explicit mode and a protected answer file.

| Prompt | Proposed behavior |
| --- | --- |
| Panel FQDN | Required canonical hostname; validate ASCII/punycode syntax and public A/AAAA routing |
| Management access | VPN/admin CIDRs; prevent accidental public panel exposure; show effective access policy |
| Database | `MariaDB LTS` (default) or `PostgreSQL (five-year supported major)`; show exact selected series/channel and support end |
| Database placement | Local install by default; advanced existing server requires credentials and verified transport |
| Local database credentials | Generate random app/migration credentials; no request for a new generic DB root password unless the engine actually needs one |
| Administrator | Email and hidden password entry/confirmation; no open first-user web enrollment |
| TLS | Let's Encrypt with ACME email/terms acknowledgment; existing custom files or explicitly selected temporary restricted self-signed bootstrap where public issuance is unavailable |
| HTTP redirect | Yes by default; no means no plaintext login, not HTTP application access |
| Package source | Show native eligible packages first; explicitly select an approved repository or verified source-build path when PHP is too old |
| Final review | Versions, repositories, files/services/firewall changes, hostname, DB choice and backup/recovery summary, excluding secrets |

Display fixed ports 80/443 in the plan; do not ask the operator to choose them. Reject a profile containing legacy configurable web-port fields instead of ignoring them. Root credentials are not written into the application database or generated plan report.

## One entry point and safe release delivery

The future entry point is `install.sh` from an authenticated versioned release. Prefer downloading, verifying and then executing the release bundle over executing an unverified network stream as root. Verify the bundle using an independently trusted release key; a checksum downloaded beside an unsigned bundle is not an independent trust anchor.

Proposed modes (interfaces to implement, **not commands available today**):

- `plan`: inspect and show changes without applying them; list any missing bootstrap prerequisites.
- `install`: interactive or explicitly unattended installation using the reviewed configuration.
- `resume`: verify recorded state and continue a partial managed installation.
- `doctor`: read-only configuration/version/service/certificate checks.
- `repair`: restore only recorded managed resources with explicit scope; never reset keys/database.
- `upgrade`: later controlled release/schema upgrade with backup and compatibility gates.

The launcher may rely on Bash/system package tools before PHP exists. Use strict parsing for any answer file; never `source`/`eval` it. Install minimal parsing/download prerequisites only through explicit signed OS package steps. Noninteractive secrets come through a root-readable file or dedicated stdin/file descriptors, never flags. Logs and manifests omit secrets; persistent secret files are root-only and have defined cleanup/expiry.

## Installation phases

| Phase | Work | Completion evidence / failure behavior |
| --- | --- | --- |
| 0. Inspect | OS/architecture, root execution, package locks, disk/RAM, time sync, existing services/config, port owners, repository entitlement | No existing DB/Nginx/BIND is adopted or overwritten implicitly; explain conflicts |
| 1. Plan | Validate interview, lifecycle catalog, DNS routes, access rules, package sources and required downloads | Root-owned non-secret plan and config hash; operator reviews exact changes |
| 2. Prepare | Acquire install lock; create transaction journal and backups of specifically managed targets | Competing installer/cert operations cannot race; resources have ownership markers |
| 3. Dependencies | Install Nginx, PHP CLI/FPM/extensions, Composer, selected DB/client and BIND signing tools | Actual binary versions/extensions checked; no public PHP-FPM listener |
| 4. Accounts/storage | Create separate web, worker, config-read and certificate-service identities/directories | Ownership/traversal checks prove web cannot read signer/SSH/TLS keys |
| 5. Database | Initialize selected local engine if new, bind locally, create DB/least-privilege users, establish backup/migration credentials | Engine/version, transaction and authenticated app connection checks; no global DB security script |
| 6. Application | Install immutable release, locked Composer libraries, private env, initial schema/admin and worker SSH key | Existing APP_KEY, signer/SSH keys, users and database never regenerated on resume |
| 7. Web bootstrap | Generate dedicated FPM pool/socket and managed Nginx fragment; add management restrictions and ACME-only HTTP path | FPM config and `nginx -t` pass; no authenticated HTTP routes |
| 8. TLS | Verify issuance prerequisites; test with CA staging where available, issue public cert, validate cert/key/SAN/chain | Failed issuance preserves restricted bootstrap or existing certificate; no false success |
| 9. Activate | Atomically promote web/TLS configuration, enable worker/scheduler/renewal timers and additive firewall rules | HTTPS hostname/cert/login reachability, redirect choice, ownership and service checks |
| 10. Report | Write sanitized receipt, runtime versions, DB series/support end, installed release, timers and recovery instructions | Success only after acceptance checks; unresolved upstream firewall/DNS problems are reported |

Application release/config pointers switch only after validation. Package installation is not globally atomic: failures may leave installed packages behind. Keep the journal and report partial state; do not blindly uninstall shared packages, reverse arbitrary OS updates or drop databases as rollback.

## PHP and platform provisioning

Verify both CLI and FPM use the same approved PHP release, at least 8.5.12, with required extensions and Argon2 support. Native eligible signed packages are preferred. Default distro package names do not prove the runtime version meets requirements.

When native packages do not qualify, offer a reviewed signed package source or an explicit upstream verified-source build. No hidden third-party repository additions and no network `curl | sh` installers. A source-build adapter must verify release signatures against pinned maintainer keys, build as an unprivileged build identity, and install into a versioned private runtime prefix; it must also define repeatable security patching and FPM service handling. It is not complete merely because compilation succeeds. See [PHP FPM documentation](https://www.php.net/manual/en/install.fpm.php).

Per-OS adapters define package names, service units, Nginx identity, sockets, library dependencies, repository signing/entitlements, and enforcing MAC policy. Fix the supplied FPM/systemd/Nginx templates through generated configuration rather than assuming `/usr/local/bin/php` everywhere. Keep the worker count at one.

For a local DB, enable engine-appropriate authentication and loopback/socket binding; never open 3306/5432 publicly as a side effect. Remote DB support requires explicit verified TLS configuration, which current PDO configuration still lacks; that is an implementation prerequisite, not a deployable installer option yet.

## Fixed-port HTTP and TLS behavior

| Endpoint | Redirect enabled | Redirect disabled |
| --- | --- | --- |
| HTTP `/.well-known/acme-challenge/TOKEN` | Serve only validated token files | Same while HTTP-01 is used |
| Other HTTP GET/HEAD | Redirect to the fixed canonical `https://FQDN` | Fixed 404/closed listener; no PHP |
| Other HTTP methods | Reject without forwarding credentials/body | Reject |
| HTTPS 443 | Admin CIDR policy, PHP-FPM and secure sessions | Same |

Use the configured canonical hostname in redirects, never an untrusted Host header. Challenge responses must bypass the panel's management allowlist only for the isolated token location, with no script execution or secret-file exposure. Reject unknown virtual hosts. Reject credential submissions over HTTP rather than redirecting their bodies.

Let's Encrypt HTTP-01 validation uses port 80, regardless of application settings. If port 80 is unavailable, use an explicitly supported DNS-01 workflow or a restricted self-signed/custom bootstrap; do not pretend local firewall access proves public reachability. Verify all published A/AAAA routes, including incorrect IPv6 records. [Let's Encrypt challenge requirements](https://letsencrypt.org/docs/challenge-types/)

Do not make initial TLS issuance depend on the not-yet-running panel managing its own DNS. First-install HTTP-01 uses already functioning public DNS. Later managed DNS-01 needs a scoped challenge publisher, propagation checks across public members and cleanup of only the matching token; it must preserve draft/published separation and concurrent TXT tokens. Prefer a dedicated delegated ACME validation zone when appropriate. This is follow-on work, not an initial dependency.

HSTS becomes explicit staged configuration: start with no long-lived HSTS during bootstrap, enable after trusted TLS and canonical-host verification, and do not add includeSubDomains/preload automatically. The current unconditional one-year header in `public/index.php` must be changed before shipping the installer. TLS does not downgrade when renewal fails; retain the current certificate and show actionable expiry alerts.

## Certificate ownership and activation protocol

TLS, SSH and DNSSEC use separate key material and lifecycles. TLS/ACME private keys and account/EAB secrets are root/certificate-service owned, outside the web root and inaccessible to normal PHP-FPM. The web process must not edit Nginx configuration or run generic sudo/systemctl/shell commands.

Use a narrow root-owned certificate/web configuration broker with a fixed request schema and allowed operations. Authenticate callers using a protected local socket/OS credentials or an equivalently constrained helper. Only managed hostnames, providers and fixed paths are accepted. Never allow user-supplied shell hooks, arbitrary ACME directory URLs, output paths or configuration snippets. The broker is separate from the DNS member helper and from the ordinary signing worker.

Application TLS workflow:

1. Authorized administrator requests issuance/renewal/provider change/custom import; require CSRF, recent reauthentication, and audit. Persist a request ID and safe metadata, not plaintext private keys.
2. Certificate service serializes the operation with installation/renewal using a shared managed-web lock; root-owned policy independently validates hostname/provider/options.
3. Obtain candidate material in a private staging directory. Validate SAN coverage, validity, cert/private-key match, chain ordering/trust policy and supported key parameters. A custom CA is labeled custom/untrusted until the configured trust policy validates it.
4. Generate the managed configuration against staged paths, run syntax checks, promote the candidate atomically and gracefully reload Nginx. If post-activation checks fail, restore the prior pointer/config and reload it. Keep expiry/error receipts rather than success-only UI messages.
5. Record fingerprints, issuer, validity, provider, next renewal and activation result. Delete transient uploads and redact secrets from diagnostics. Keep a defined private rollback retention policy, not unlimited key copies.

Prefer Certbot `certonly` with an isolated webroot/account configuration and fixed deploy hook, so ArzoieDNS owns only its managed Nginx fragment. Do not let the ACME client rewrite unrelated vhosts. Renewal runs through a systemd timer even if the application database is down; the application displays sanitized service receipts afterward. Use the client's renewal scheduling/CA guidance, not a permanently hardcoded assumption about certificate lifetime. [Certbot issuance and renewal](https://eff-certbot.readthedocs.io/en/stable/using.html)

ZeroSSL uses a provider-specific adapter and protected External Account Binding credentials; collect them only if that provider is selected. Never silently switch CA during a renewal failure or reuse one provider's account secrets for another. [ZeroSSL EAB documentation](https://zerossl.com/documentation/acme/generate-eab-credentials/)

Nginx supports graceful configuration reload, but application activation must still verify the expected certificate is actually served; successful syntax alone is insufficient. [Nginx reload behavior](https://nginx.org/en/docs/control.html)

## Idempotence, recovery and upgrades

Persist an installation ID, release ID, config hash, per-phase verified receipts and managed resource inventory in root-owned state. A phase is complete only if its postconditions still hold, not merely because a marker exists. Keep existing keys/passwords unchanged on reruns. A stale/ambiguous state yields a diagnosis and bounded repair plan, not a fresh destructive installation.

Record backups/checksums for files the installer owns. Never replace the full shared Nginx configuration, disable another vhost, flush firewall policy, reset DB authentication, or delete unrelated services. Preserve SSH access throughout. On custom nftables/provider firewall setups, report exact needed rules without claiming remote reachability or bypassing policy.

Initial release only installs new control planes and resumes its own partial installs. Existing third-party deployments, engine switching, OS-major upgrades, automated uninstall and application/database downgrades are out of initial scope. Upgrade requires numbered migrations, database/key backup and tested restore before it can be advertised as safe.

## Proposed modules and ownership

These paths describe future implementation; they do not exist yet:

```text
install.sh                              Small verified-release launcher / wizard
installer/core/                         Plan, validation, lock, journal, receipts
installer/platforms/{el10,debian}/      OS/repository/service/MAC adapters
installer/database/                    Engine selection, lifecycle policy, provisioning
installer/web/                         Fixed-port Nginx/FPM configuration and activation
installer/tls/                         ACME bootstrap and broker installation
resources/install/                     Versioned policy/catalog schema and templates
src/Services/CertificateService.php     Application request/status adapter (unprivileged)
tests/installer/                       Plans, interruptions, secret handling and VM scenarios
```

The integrator owns config/catalog/helper schemas and release wiring. OS adapters, database provisioning, web/TLS broker and application UI can be separate workstreams only after those contracts are agreed. Existing schema, routes and `composer.lock` remain shared hot spots under the parallel-work protocol.

## Delivery sequence and acceptance

1. **INS-01: contracts/policy.** Finalize wizard/profile schema, signed release/DB catalog, resource inventory, phase journal, lock and sanitized receipts. Test unsupported OS/version/expired catalog/rejected port fields before mutation.
2. **INS-02: platform/runtime/database.** Implement EL/Debian adapters, approved PHP paths and both local engines. Run on every target OS; check DB lifecycle selection, reruns, credential preservation and least privilege. Existing remote DB waits for verified PDO TLS support.
3. **INS-03: fixed-port HTTPS installation.** Implement Nginx/FPM, root-service boundary, ACME bootstrap, redirect options and renewal timer. Test with CA staging and disposable public routing; no production rate-limit abuse.
4. **INS-04: application certificate administration.** Implement provider/status UI and broker requests for renewal, ZeroSSL and custom import; audit, reauthentication, SAN/key/chain validation and rollback required.
5. **INS-05: release qualification.** Interrupt/reboot after every phase, simulate full disks/package locks/download failures, occupied ports, wrong A/AAAA, blocked 80/443, invalid cert/key, expired/revoked/untrusted candidates, renewal downtime and unavailable DB. Test both redirect choices and prove HTTP cannot reach PHP/login.

Release gate: one documented installer execution produces a working restricted HTTPS panel with the selected eligible DB, correct PHP-FPM runtime, worker/scheduler and independent certificate renewal; a second execution preserves state. Failed certificate activation preserves the prior working configuration. No package/repository changes affect unrelated services unexpectedly. No supported-platform or maintenance claim is marked passed without dated evidence.
