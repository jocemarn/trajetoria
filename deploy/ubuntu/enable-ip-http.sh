#!/usr/bin/env bash
set -euo pipefail
EXPECTED_IP="${1:?Informe o IP aprovado}"
EXPECTED_HOSTNAME="${2:?Informe a máquina aprovada}"
[[ $EUID -eq 0 && "$(hostname)" == "$EXPECTED_HOSTNAME" ]] || exit 41
[[ "$EXPECTED_IP" == 177.93.129.219 ]] || { echo "IP diferente da implantação aprovada."; exit 42; }
ip -4 -o addr show scope global | grep -F '172.18.3.18/' >/dev/null || exit 43
APP=/srv/trajetoria/app
CONF=/etc/nginx/sites-available/trajetoria.net.br
[[ -f "$APP/public/index.php" ]] || exit 44
if [[ -f "$APP/private/installed.lock" ]]; then
    php -r '$c=require $argv[1];if(($c["url"]??"")!=="http://177.93.129.219"||($c["require_https"]??true))exit(1);' "$APP/private/config.php" || { echo "Instalação existente usa outro endereço. Não será alterada."; exit 45; }
fi
BACKUP="$CONF.before-http-ip-$(date -u +%Y%m%d%H%M%S)"
cp -p "$CONF" "$BACKUP"
install -m 0644 "$(dirname "$0")/nginx-ip-http.conf" "$CONF"
if ! nginx -t 2>&1; then cp -p "$BACKUP" "$CONF"; exit 46; fi
python3 - <<'PY'
from pathlib import Path
import json,os,pwd,tempfile
p=Path('/srv/trajetoria/app/private/runtime/http-bootstrap.json')
fd,tmp=tempfile.mkstemp(prefix='.http-bootstrap-',dir=str(p.parent))
with os.fdopen(fd,'w') as f:json.dump({'allowedOrigin':'http://177.93.129.219'},f)
u=pwd.getpwnam('www-data');os.chown(tmp,u.pw_uid,u.pw_gid);os.chmod(tmp,0o640);os.replace(tmp,p)
p=Path('/home/ubuntu/trajetoria-acesso-instalacao.txt')
if p.exists():p.write_text(p.read_text().replace('https://trajetoria.net.br/instalar','http://177.93.129.219/instalar'))
PY
if ! systemctl reload nginx; then cp -p "$BACKUP" "$CONF"; exit 47; fi
echo "ACESSO_TEMPORARIO_IP=OK"
