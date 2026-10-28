#!/bin/bash
# Exercise the actual helper with fake commands and a temporary filesystem.
set -Eeuo pipefail
export LC_ALL=C
root=$(cd "$(dirname "$0")/.." && pwd)
work=$(mktemp -d)
trap 'rm -rf -- "$work"' EXIT
export ARZOIE_TEST_ROOT="$work"
mkdir -p "$work"/{bin,base/{incoming,zones,receipts},etc}
printf 'root\n' > "$work/etc/bind-group"
export ARZOIE_REAL_PATH="$PATH"
"${PYTHON:-python3}" - "$root/resources/member/arzoie-member" "$work/helper" "$(printf '%s' "$work" | base64)" <<'PY'
import base64, pathlib, sys
source = pathlib.Path(sys.argv[1]).read_text()
work = base64.b64decode(sys.argv[3]).decode()
source = source.replace('export PATH=/usr/sbin:/usr/bin:/sbin:/bin', 'export PATH="' + work + '/bin:$ARZOIE_REAL_PATH"')
source = source.replace('[[ $EUID == 0 ]] || exit 77', ': # root check disabled only in isolated mocked test')
source = source.replace('base=/var/lib/arzoiedns', 'base="' + work + '/base"')
source = source.replace('/run/lock/arzoie-member.lock', work + '/helper.lock')
source = source.replace('/etc/arzoiedns/bind-group', work + '/etc/bind-group')
pathlib.Path(sys.argv[2]).write_text(source)
PY
cat > "$work/bin/rndc" <<'STUB'
#!/bin/bash
set -e
root=$ARZOIE_TEST_ROOT
echo "$*" >> "$root/rndc.log"
case "$1" in
zonestatus) [[ -f "$root/loaded" ]] ;;
addzone|reload) [[ ! -f "$root/fail-load" ]] || exit 1; touch "$root/loaded" ;;
delzone) rm -f "$root/loaded" ;;
status) exit 0 ;;
*) exit 1 ;;
esac
STUB
cat > "$work/bin/dig" <<'STUB'
#!/bin/bash
if [[ -f "$ARZOIE_TEST_ROOT/loaded" ]]; then
    printf ';; flags: qr aa; QUERY: 1\n'
    printf 'example.com. 300 IN SOA ns1.example.net. hostmaster.example.com. 42 3600 600 1209600 300\n'
fi
STUB
for tool in named-checkzone dnssec-verify chown restorecon flock; do
    printf '#!/bin/bash\nexit 0\n' > "$work/bin/$tool"
done
chmod +x "$work/bin/"*
zone='example.com'
printf 'signed test fixture\n' > "$work/base/incoming/$zone.10.zone"
checksum=$(sha256sum "$work/base/incoming/$zone.10.zone" | cut -d' ' -f1)
bash "$work/helper" apply "$zone" 10 42 "$checksum" > "$work/receipt"
grep -q '"revision":10' "$work/receipt"
grep -q '^addzone example.com ' "$work/rndc.log"
[[ -f "$work/base/receipts/$zone.active" ]]
echo 'PASS new zone add and authoritative serial acknowledgment'
cp "$work/base/zones/$zone.zone" "$work/base/incoming/$zone.11.zone"
bash "$work/helper" apply "$zone" 11 42 "$checksum" > /dev/null
grep -q '^reload example.com$' "$work/rndc.log"
echo 'PASS existing zone uses targeted reload'
cp "$work/base/zones/$zone.zone" "$work/base/incoming/$zone.12.zone"
before=$(wc -l < "$work/rndc.log")
if bash "$work/helper" apply "$zone" 12 42 "$(printf '%064d' 0)" >/dev/null 2>&1; then echo 'FAIL bad checksum accepted'; exit 1; fi
[[ $(wc -l < "$work/rndc.log") == "$before" ]]
echo 'PASS bad checksum rejected before any BIND mutation'
if bash "$work/helper" delete '../example.com' 12 >/dev/null 2>&1; then echo 'FAIL path traversal accepted'; exit 1; fi
if bash "$work/helper" delete "$zone" 9 >/dev/null 2>&1; then echo 'FAIL stale revision accepted'; exit 1; fi
echo 'PASS unsafe names and stale revisions rejected'
printf 'replacement data\n' > "$work/base/incoming/$zone.12.zone"
newsum=$(sha256sum "$work/base/incoming/$zone.12.zone" | cut -d' ' -f1)
touch "$work/fail-load"
if bash "$work/helper" apply "$zone" 12 42 "$newsum" >/dev/null 2>&1; then echo 'FAIL failed reload acknowledged'; exit 1; fi
grep -q '^signed test fixture$' "$work/base/zones/$zone.zone"
[[ $(cat "$work/base/receipts/$zone.revision") == 11 ]]
rm "$work/fail-load"
echo 'PASS failed reload restores previous file without acknowledgment'
bash "$work/helper" delete "$zone" 13 > /dev/null
[[ ! -f "$work/loaded" && ! -f "$work/base/receipts/$zone.active" ]]
bash "$work/helper" delete "$zone" 13 > /dev/null
[[ $(cat "$work/base/receipts/$zone.revision") == 13 ]]
echo 'PASS deletion tombstone is durable and repeatable'
if grep -Eq '^reload$|^reconfig$' "$work/rndc.log"; then echo 'FAIL global reload detected'; exit 1; fi
echo 'PASS no global reload or reconfiguration'
