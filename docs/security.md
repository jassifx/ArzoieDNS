# Security model

## Trust boundaries

The panel manages public DNS content and bootstrap credentials; compromise is serious. Put it on a management VPN or explicitly allowlisted HTTPS endpoint. Bind PHP-FPM to a local socket and expose only `public/`. Serve Bootstrap locally. Do not serve the repository root, `.env`, private keys, job data or signer artifacts over HTTP.

Use separate OS identities: `arzoie-web` runs PHP-FPM; `arzoie` runs the worker/signer. Neither runs as root. The web identity needs application configuration and the database but must not be able to read SSH/DNSSEC private keys or write application source. Both currently need APP_KEY to enqueue/decrypt encrypted bootstrap credentials. A separate credential broker and remote signer would strengthen separation and are future work.

All current panel accounts are administrators. Per-zone RBAC, MFA, login-source rate limiting, recovery codes and delegated read-only reporting are not implemented. Per-account lockouts are present, but put source rate limits at the management reverse proxy. Use a strong unique administrator password and a management VPN until these additional controls are delivered.

## Credentials

- Root or sudo-user SSH passwords are accepted only for pending members.
- Credentials are encrypted using Sodium secretbox and a random nonce before database storage.
- The encrypted bootstrap job expires after 15 minutes. Workers scrub expired secrets and erase credentials after each onboarding attempt, including failures.
- Passwords are not put in shell command arguments, job payloads, audit metadata, or exception logs. For sudo, the password travels in the encrypted SSH channel's stdin. A PTY is used only during bootstrap escalation.
- PHP memory is not a secure enclave; database backups containing encrypted credentials still require APP_KEY secrecy. Encryption does not protect against a compromised web process holding the same key.
- The panel requires a preverified SHA256 SSH host fingerprint. It checks it before SSH password/key authentication. Obtain it from the provider console or existing trusted access; do not blindly trust `ssh-keyscan` over the same untrusted network.
- The current worker has one SSH key for the fleet. Per-member keys, automated rotation, and an audited key-revocation workflow are pending.

## Member privilege policy

`arzoie-sync` belongs to wheel on EL or sudo on Debian to meet the administration model, but a user-specific sudo rule denies general elevation and allows only `/usr/local/sbin/arzoie-member`. Bootstrap tests that arbitrary `sudo true` is denied. The helper is root-owned and only accepts enumerated operations with validated domain names, numeric revisions and SHA256 digests. It is not a generic command runner.

Authorized keys use `restrict` (disables port/agent/X11 forwarding, PTYs and user rc); optional `CONTROL_CIDR` further limits the source. Unprivileged SFTP and shell access remain available because transport needs SFTP. A forced-command-only transport with a separate upload subsystem is future work. Avoid adding any other sudo policy granting this account broad access.

The helper refuses include/generate directives, stages into a root-owned file before checks, verifies signatures, installs signed files atomically, and only operates below fixed directories. Operators must also protect mount points and ownership. The helper assumes the root-owned base, receipt and zone directories have not been tampered with. SFTP staging is untrusted input; DNSSEC verification is not an independent authorization signature because the control plane is already the trusted publisher. Detached deployment-manifest signatures are future work.

RNDC binds to loopback only. Its key never leaves the member and is not readable by the automation user. Recursion, dynamic DNS updates and zone transfers are disabled. BIND response rate limiting is enabled. SSH publication deliberately replaces DNS transfer protocols in this version.

## Operating systems and network controls

Only explicit EL 10 IDs and Debian 13+ pass preflight. Unsupported systems, including Ubuntu, are rejected before package or configuration changes. SELinux/AppArmor are retained; provisioning configures managed paths rather than disabling them. Full enforcing-policy qualification on each target distribution remains required.

Bootstrap adds the DNS service to active firewalld or active UFW. It does not flush firewalls, change SSH daemon authentication policy, replace custom nftables rules, open cloud/provider firewalls or configure upstream routing. The operator must permit public TCP/UDP 53 and restrict SSH to management sources. External reachability checks are still a release gate.

## DNSSEC key custody

Private keys exist only in the worker-owned signer directory, mode 0700 with restrictive umask. Back up KSK/ZSK material encrypted and offline along with the database, APP_KEY and SSH key. A database-only backup cannot restore signing. Public members retain signed data, not private keys.

Do not publish a parent DS until all delegated members answer the signed zone. Do not remove old DNSKEYs while a parent DS or cached signatures still require them. The initial implementation generates stable keys and refreshes signatures; it does not automate safe key rollover or validate registrar DS records. These limitations must remain visible in release notes.

## Audit and reporting

The database records administrator actions and deployment acknowledgments. Audit rows are append-only by application convention, not cryptographically tamper evident and not protected against database administrators. Forward independent logs to protected storage for high-assurance deployments. Query logging is left to BIND/dnstap and is not on the request path through the panel database.

UI "Synced" means the expected revision was acknowledged after a member-local authoritative SOA query. It is not proof of present public reachability, matching every RRset, a validated parent DS, or absence of subsequent manual drift. Health freshness and signature expiration must be monitored separately.
