#!/bin/bash
set -Eeuo pipefail
export PATH=/usr/sbin:/usr/bin:/sbin:/bin
export LC_ALL=C
umask 077
trap 'echo ARZOIE_BOOTSTRAP_FAILED >&2' ERR
[[ $EUID == 0 ]] || exit 77
. /etc/os-release
major=${VERSION_ID%%.*}
case "$ID:$major" in
debian:*)
    (( major >= 13 )) || { echo 'Debian 13+ required' >&2; exit 65; }
    family=debian; admin_group=sudo; bind_group=bind
    config=/etc/bind/named.conf
    ;;
rocky:10|almalinux:10|ol:10|rhel:10)
    family=el; admin_group=wheel; bind_group=named
    config=/etc/named.conf
    ;;
*) echo 'Unsupported operating system; Ubuntu is not supported' >&2; exit 65 ;;
esac
# Only take ownership of a fresh member. Reruns are allowed after our marker exists.
if [[ ! -e /etc/arzoiedns/managed && ! -e /etc/arzoiedns/provisioning ]]; then
    if pgrep -x named >/dev/null || [[ -f $config ]]; then
        echo 'BIND already configured. Use a fresh server; existing DNS configuration is not overwritten.' >&2
        exit 65
    fi
    if id arzoie-sync >/dev/null 2>&1; then echo 'Reserved user already exists' >&2; exit 65; fi
fi
install -d -m 0750 /etc/arzoiedns
touch /etc/arzoiedns/provisioning
if [[ $family == debian ]]; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get update
    apt-get install -y bind9 bind9-utils dnsutils sudo openssh-server
else
    dnf install -y bind bind-utils sudo openssh-server policycoreutils-python-utils
fi
install -d -m 0750 /etc/arzoiedns
chown root:"$bind_group" /etc/arzoiedns
printf '%s\n' "$bind_group" > /etc/arzoiedns/bind-group
install -d -m 0750 -o root -g "$bind_group" /var/lib/arzoiedns /var/lib/arzoiedns/zones /var/lib/arzoiedns/receipts
chmod 0751 /var/lib/arzoiedns
install -d -m 0770 -o "$bind_group" -g "$bind_group" /var/lib/arzoiedns/runtime
if ! id arzoie-sync >/dev/null 2>&1; then useradd -m -s /bin/bash -G "$admin_group" arzoie-sync; fi
usermod -p '*' arzoie-sync
install -d -m 0700 -o arzoie-sync -g arzoie-sync /home/arzoie-sync/.ssh
install -d -m 0700 -o arzoie-sync -g arzoie-sync /var/lib/arzoiedns/incoming
key=$(printf '%s' '@@PUBLIC_KEY@@' | base64 -d)
cidr=$(printf '%s' '@@CONTROL_CIDR@@' | base64 -d)
if [[ -n $cidr ]]; then printf 'restrict,from="%s" %s\n' "$cidr" "$key" > /home/arzoie-sync/.ssh/authorized_keys
else printf 'restrict %s\n' "$key" > /home/arzoie-sync/.ssh/authorized_keys; fi
chown arzoie-sync:arzoie-sync /home/arzoie-sync/.ssh/authorized_keys
chmod 0600 /home/arzoie-sync/.ssh/authorized_keys
printf '%s' '@@HELPER_BASE64@@' | base64 -d > /usr/local/sbin/arzoie-member
chown root:root /usr/local/sbin/arzoie-member
chmod 0755 /usr/local/sbin/arzoie-member
cat > /etc/sudoers.d/zz-arzoie-sync <<'SUDOERS'
# Override wheel/sudo group privileges for the automation account.
Defaults:arzoie-sync !requiretty
arzoie-sync ALL=(ALL:ALL) !ALL
arzoie-sync ALL=(root) NOPASSWD: /usr/local/sbin/arzoie-member
SUDOERS
chmod 0440 /etc/sudoers.d/zz-arzoie-sync
visudo -cf /etc/sudoers >/dev/null
if runuser -u arzoie-sync -- sudo -n /usr/bin/true 2>/dev/null; then
    echo 'Unrestricted sudo detected; refusing activation' >&2; exit 65
fi
# RNDC is local only. Never expose its key to the panel or automation user.
if [[ ! -f /etc/arzoiedns/rndc.key ]]; then rndc-confgen -a -c /etc/arzoiedns/rndc.key; fi
chown root:"$bind_group" /etc/arzoiedns/rndc.key
chmod 0640 /etc/arzoiedns/rndc.key
cat > /etc/rndc.conf <<'RNDC'
include "/etc/arzoiedns/rndc.key";
options { default-server 127.0.0.1; default-key "rndc-key"; };
RNDC
cat > "$config" <<'NAMED'
include "/etc/arzoiedns/rndc.key";
controls { inet 127.0.0.1 port 953 allow { 127.0.0.1; } keys { "rndc-key"; }; };
options {
    directory "/var/lib/arzoiedns/runtime";
    pid-file "/var/lib/arzoiedns/runtime/named.pid";
    session-keyfile "/var/lib/arzoiedns/runtime/session.key";
    listen-on { any; }; listen-on-v6 { any; };
    recursion no; allow-query { any; }; allow-transfer { none; };
    allow-new-zones yes;
    dnssec-validation no;
    version "not disclosed";
    minimal-responses yes;
    rate-limit { responses-per-second 20; window 5; };
};
NAMED
chown root:"$bind_group" "$config"
chmod 0640 "$config"
if [[ $family == el ]]; then
    semanage fcontext -a -t named_conf_t '/etc/arzoiedns(/.*)?' 2>/dev/null || semanage fcontext -m -t named_conf_t '/etc/arzoiedns(/.*)?'
    semanage fcontext -a -t named_zone_t '/var/lib/arzoiedns(/.*)?' 2>/dev/null || semanage fcontext -m -t named_zone_t '/var/lib/arzoiedns(/.*)?'
    semanage fcontext -a -t named_cache_t '/var/lib/arzoiedns/runtime(/.*)?' 2>/dev/null || semanage fcontext -m -t named_cache_t '/var/lib/arzoiedns/runtime(/.*)?'
    restorecon -RF /var/lib/arzoiedns /etc/arzoiedns /home/arzoie-sync/.ssh /usr/local/sbin/arzoie-member
else
    if [[ -f /etc/apparmor.d/usr.sbin.named ]]; then
        install -d /etc/apparmor.d/local
        cat > /etc/apparmor.d/local/usr.sbin.named <<'APPARMOR'
/etc/arzoiedns/ r,
/etc/arzoiedns/** r,
/var/lib/arzoiedns/ r,
/var/lib/arzoiedns/zones/ r,
/var/lib/arzoiedns/zones/** r,
/var/lib/arzoiedns/runtime/ rw,
/var/lib/arzoiedns/runtime/** rwk,
APPARMOR
        apparmor_parser -r /etc/apparmor.d/usr.sbin.named
    fi
fi
named-checkconf "$config"
systemctl enable --now named
# Firewall rules are additive. SSH access and provider firewall policy are preserved.
if systemctl is-active --quiet firewalld; then
    firewall-cmd --permanent --add-service=dns
    firewall-cmd --reload
elif command -v ufw >/dev/null && ufw status | grep -q '^Status: active'; then
    ufw allow 53/udp; ufw allow 53/tcp
fi
rndc status >/dev/null
touch /etc/arzoiedns/managed
rm -f /etc/arzoiedns/provisioning
echo ARZOIE_BOOTSTRAP_OK
