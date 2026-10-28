# Infrastructure and member-helper instructions

Inherits [root instructions](../AGENTS.md). These files can change root-level configuration on DNS members; read [security](../docs/security.md) and [architecture](../docs/architecture.md) first. Editing scripts/templates is distinct from running them on infrastructure.

## Protocol and privilege boundaries

The member helper currently accepts:

```text
health
apply ZONE REVISION SERIAL SHA256
delete ZONE REVISION
deboard
```

Successful apply/delete output is a single JSON receipt with `ok: true`, matching `zone`, and numeric `revision`. Health output is JSON consumed by PHP. Keep diagnostics on stderr and structured results on stdout. Coordinate changes with `SshTransport`, `Provisioner`, `Reconciler`, `Worker`, views and tests.

Bootstrap expands `@@PUBLIC_KEY@@`, `@@CONTROL_CIDR@@`, and `@@HELPER_BASE64@@`; the values are base64-decoded by the shell template. Do not add ad hoc quoting or credential interpolation. Bootstrap escalation sends sudo credentials through SSH channel stdin, not argv.

- Preserve fixed root-owned helper paths and strict validation of operations, names, serials, revisions and digests.
- Treat staging as hostile. Copy it into root-owned storage before validating and installing. Reject directives that could read additional files.
- Validate zone/signatures before activation. Acknowledge only after the expected authoritative serial is served. Preserve replay safety and stale-revision rejection.
- Updates reload only the named zone. Do not use a service restart or global reload as an error workaround.
- Do not distribute DNSSEC private keys or RNDC keys to the automation account.
- Keep the automation account's broad sudo permissions denied, even after deboard. Do not delete the deny override and restore inherited group privileges.
- Preserve operator SSH policy/access, SELinux/AppArmor enforcement, unrelated services and firewall rules. First enrollment refuses configured BIND; interrupted managed bootstrap reruns must remain possible.
- Keep EL 10 and Debian 13+ paths explicit. No Ubuntu fallback, silent third-party repository enrollment, or unapproved package-source replacement.
- Templates require site-specific identities, TLS/socket paths and management allowlists; do not insert real production addresses or secrets into defaults.

## Verification

Run `bash -n` on both member scripts and `bash tests/member-helper.sh`. Review each helper mutation for path containment, privilege escalation, interruption/replay, cleanup and rollback behavior. Mock commands do not test actual BIND crypto, systemd, firewalls, sudo or MAC policy. Changes affecting those require designated disposable supported-OS VM evidence before release.

Changes to systemd/Nginx/FPM also require applicable syntax checks and account/path consistency. Never disable security controls merely to make a test pass. Record any missing integration environment explicitly.
