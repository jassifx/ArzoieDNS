# Installation and operation

## Qualification status

The code includes explicit EL 10 and Debian 13+ member paths. No live member, database service, or external infrastructure was provided during the initial rewrite. Use disposable VMs for the acceptance matrix before serving real delegated domains. There is deliberately no Ubuntu setup path.

## Control host prerequisites

Use Rocky/AlmaLinux/Oracle Linux/RHEL 10 or Debian 13+. Install Nginx, a trusted PHP 8.5.12+ build with CLI/FPM, Composer, PDO driver, Sodium, mbstring, OpenSSL and Argon2id support. Install BIND utilities providing `named-checkzone`, `dnssec-keygen`, `dnssec-signzone`, `dnssec-verify` and `dnssec-dsfromkey`. Member machines do not require PHP, Composer or database access.

PHP 8.5 packages must come from your approved repositories/build pipeline. Default distro PHP may be too old; this project does not add third-party repositories or downgrade its runtime requirement. Example utility package names:

```sh
# EL 10, after configuring your approved repositories/subscription:
dnf install nginx bind-utils sudo openssh-clients
# Debian 13+:
apt-get update
apt-get install nginx bind9-utils dnsutils sudo openssh-client
```

Keep PHP patched within 8.5; verify `php -v`, `php -m`, and `php -r 'var_dump(defined("PASSWORD_ARGON2ID"));'`. Composer enforces the runtime/extensions. Supply `pdo_pgsql` for PostgreSQL or `pdo_mysql` for MariaDB. The application explicitly rejects other DB drivers.

## Database

Create a dedicated database and service login. Examples below are templates; replace secrets interactively or through your secret manager.

PostgreSQL (16+):

```sql
CREATE ROLE arzoiedns LOGIN;
-- Set password with psql \password arzoiedns, not in shell history.
CREATE DATABASE arzoiedns OWNER arzoiedns;
```

MariaDB (10.11+, InnoDB default):

```sql
CREATE DATABASE arzoiedns CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;
CREATE USER 'arzoiedns'@'localhost' IDENTIFIED BY 'REPLACE_WITH_RANDOM_SECRET';
GRANT ALL PRIVILEGES ON arzoiedns.* TO 'arzoiedns'@'localhost';
```

Run initial migrations using an account with schema privileges; reduce runtime grants after installation if you separate migration and service accounts. Use a local database or encrypted transport; native PDO TLS options are not yet exposed by this application, so remote database TLS configuration is a prerequisite implementation task before a remote DB deployment.

## Files and identities

Install the application at `/opt/arzoiedns`; source should be owned by root or a release account, not PHP-FPM or the worker. Install Composer dependencies before applying final ownership:

```sh
composer install --no-dev --prefer-dist --classmap-authoritative
cp .env.example .env
php bin/arzoie key:generate
# Set APP_KEY from the output and configure .env using a protected editor.
```

Create `arzoie` (worker), `arzoie-web` (FPM), and a shared config-read group. Allow these two identities to read `.env`, but no other users. The worker must have its own home/state directory; it needs no sudo on the control host.

```sh
useradd --system --home-dir /var/lib/arzoiedns --shell /usr/sbin/nologin arzoie
useradd --system --no-create-home --shell /usr/sbin/nologin arzoie-web
groupadd --system arzoie-config
usermod -aG arzoie-config arzoie
usermod -aG arzoie-config arzoie-web
chown root:arzoie-config /opt/arzoiedns/.env
chmod 0640 /opt/arzoiedns/.env
install -d -m 0700 -o arzoie -g arzoie /var/lib/arzoiedns
install -d -m 0700 -o arzoie-web -g arzoie-web /var/lib/arzoiedns-web-sessions
```

The FPM session directory is separate from the worker's private state. Adjust `session.save_path` in the FPM template accordingly. No web identity should traverse `/var/lib/arzoiedns` or read the signer/SSH directories. Bootstrap temp scripts use the worker's private temporary namespace under systemd.

```sh
cd /opt/arzoiedns
sudo -u arzoie php bin/arzoie migrate
sudo -u arzoie php bin/arzoie user:create admin@example.com
sudo -u arzoie php bin/arzoie ssh:keygen
```

The administrator password is read from an interactive Linux terminal with echo disabled. No open web installer exists. All users currently have administrator access.

## Web service

Configure PHP-FPM with `resources/php/arzoiedns-fpm.conf`. Use the actual PHP 8.5 FPM binary and correct Nginx identity (`nginx` on EL, usually `www-data` on Debian). Create the socket runtime directory, e.g. a tmpfiles rule `d /run/arzoiedns 0755 root root -`, and ensure the FPM session path is web-owned.

Configure Nginx using `resources/nginx/arzoiedns.conf`; replace host/certificates/socket and management allowlist. Provision a valid TLS certificate using your approved process. Only `public/index.php` executes as PHP; the repository root is never a web root. Configure SELinux/AppArmor for your FPM build and app paths without disabling enforcement. Test `nginx -t` and the FPM configuration before reload.

## Worker and scheduler

Copy the files from `resources/systemd/` into `/etc/systemd/system/`. Adjust `/usr/local/bin/php` if your verified PHP runtime is elsewhere. Then:

```sh
systemctl daemon-reload
systemctl enable --now arzoiedns-worker.service arzoiedns-scheduler.timer
systemctl status arzoiedns-worker.service
journalctl -u arzoiedns-worker.service -f
```

Run **one worker on one control host**. The lock is host-local. Keep the scheduler timer enabled: it drives periodic health, retry reconciliation, credential expiry cleanup and pre-expiry signature renewal. A worker restart requeues its running jobs for replay. Service stop can interrupt a remote operation; check/retry deployment status after a restart.

## Member onboarding

Use a fresh supported VM/physical host, with a static IP, working package repositories, clock synchronization, and SSH access. A root password or a password-authenticated sudo user is accepted. Existing BIND config is refused on first enrollment. For root login disabled by local policy, use a sudo-capable user; the project does not weaken sshd policy to enable root passwords.

Obtain the server's host fingerprint over the console:

```sh
ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub -E sha256
```

Enter the Ed25519 host-key fingerprint in the panel. The transport explicitly negotiates Ed25519 host keys so the console fingerprint and negotiated algorithm agree. Provision an Ed25519 SSH host key first if your server lacks one; do not turn off host-key verification. Fill in the member's name, location, literal IP, port, username and password. An optional separate sudo password is supported. The worker expires queued credentials after 15 minutes and erases them after the attempt; retry from the panel if needed.

Successful onboarding installs authoritative BIND, local RNDC and the managed helper/account. It verifies key authentication and records a health response before marking active. Fresh-server setup is automated, but OS package availability, upstream/provider firewalls, custom nftables policies and public routing are operator prerequisites. Verify inbound **both UDP and TCP 53**, outbound package access and NTP. Restrict SSH to the worker address; set `CONTROL_CIDR` before onboarding where possible.

## Domain publication

Create a draft with at least two distinct NS targets. SOA mailbox uses DNS syntax (`hostmaster.example.com`, not an `@` email). Add apex A/AAAA and application/mail records as needed. In-bailiwick nameservers require appropriate A/AAAA records and registrar glue. Click **Sign & publish revision**, wait for every member's acknowledgment, and verify external UDP/TCP queries.

```sh
dig @MEMBER_IP example.com SOA +norecurse
dig @MEMBER_IP example.com SOA +tcp +norecurse
dig @MEMBER_IP example.com DNSKEY +dnssec
```

Set registrar NS delegation and required glue. After all delegated members serve the signed zone, submit the displayed DS records to the registrar. Validate from an independent resolver/tool. Registrar integration and automatic parent-DS confirmation are not implemented.

To change a record, edit the draft and publish once. Existing public records are not affected until publish. Every affected zone gets a new serial and signed artifact; other zones do not reload. Offline members catch up to the latest desired revision when reachable.

## Drain and remove a member

Maintain at least two other healthy synced members. Drain the target; it keeps serving but no longer receives updates. Remove its NS from zone drafts, publish to remaining members, update registrar delegation/glue, and wait all relevant TTLs. The panel requires acknowledgment of both changes and TTL expiry before queuing deboarding. It cannot infer registrar state automatically. Do not deboard merely to recover from a transient network outage.

## Recovery and backups

Back up the database, `.env`/APP_KEY, worker SSH key, signer private keys and immutable artifacts as one recovery set. Encrypt backups, keep off-host copies, and rehearse restoring to an isolated control host. Never run the restored worker alongside the old one. Do not regenerate DNSSEC keys when restoring: published parent DS records depend on them. BIND members are service continuity copies, not backups of private signing keys.

Failed jobs appear in Reports. `php bin/arzoie jobs:retry ID` resets a failed job's attempts; bootstrap retries require fresh credentials from the member page. Do not change a pinned host key just to silence an error: verify any replacement independently. Restoring the database to an older revision than member receipts is rejected by members; reconcile history/serials manually before resuming writes.

## Database tests

Use a dedicated disposable database; the test refuses non-test database names. Configure `ARZOIE_TEST_DB=1` and a DB_DATABASE ending in `_test`, then run `php tests/database.php`. It creates the schema and exercises transactions, record updates, revisions, tombstones, jobs and acknowledgments. It removes only its own generated rows. Run separately against PostgreSQL and MariaDB. These checks do not emulate real BIND or SSH.
