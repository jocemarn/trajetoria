#!/usr/bin/env bash
set -euo pipefail
EXPECTED_HOSTNAME="${1:?Informe o nome verificado da máquina}"
[[ "$(hostname)" == "$EXPECTED_HOSTNAME" ]] || { echo "Nome da máquina diferente do destino aprovado."; exit 1; }
[[ $EUID -eq 0 ]] || { echo "Execute com sudo."; exit 1; }
. /etc/os-release
[[ "$ID" == ubuntu && "$VERSION_ID" == 24.04 ]] || { echo "Este roteiro foi preparado para Ubuntu 24.04."; exit 1; }
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y nginx mysql-server php8.3-fpm php8.3-mysql php8.3-sqlite3 php8.3-mbstring php8.3-xml php8.3-zip php8.3-curl git unzip certbot python3
install -d -m 0755 /srv/trajetoria /var/www/trajetoria-acme
install -d -m 0700 /etc/trajetoria
cat > /etc/php/8.3/fpm/conf.d/99-trajetoria.ini <<'INI'
upload_max_filesize=32M
post_max_size=40M
memory_limit=256M
max_execution_time=120
expose_php=Off
session.use_strict_mode=1
session.use_only_cookies=1
INI
python3 - <<'PY'
import json,secrets,subprocess
from pathlib import Path
p=Path('/etc/trajetoria/database.json')
if not p.exists():
    existing=subprocess.check_output(['mysql','-NBe',"SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='trajetoria'"],text=True).strip()
    if existing:raise SystemExit('Banco trajetoria existente sem arquivo de preparação. Revisão manual necessária.')
    c={'host':'localhost','name':'trajetoria','user':'trajetoria','password':secrets.token_hex(32),'initialized':False}
    p.write_text(json.dumps(c));p.chmod(0o600)
c=json.loads(p.read_text())
if not c.get('initialized'):
    sql=f"CREATE DATABASE IF NOT EXISTS trajetoria CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS 'trajetoria'@'localhost' IDENTIFIED BY '{c['password']}'; GRANT ALL PRIVILEGES ON trajetoria.* TO 'trajetoria'@'localhost';"
    subprocess.run(['mysql'],input=sql,text=True,check=True)
    c['initialized']=True;p.write_text(json.dumps(c));p.chmod(0o600)
print('Banco exclusivo preparado. Credenciais preservadas no servidor, fora da pasta pública.')
PY
if [[ ! -f /etc/nginx/sites-available/trajetoria.net.br ]]; then
cat > /etc/nginx/sites-available/trajetoria.net.br <<'NGINX'
server {
    listen 80;
    server_name trajetoria.net.br 177.93.129.219;
    location ^~ /.well-known/acme-challenge/ { root /var/www/trajetoria-acme; }
    location / { default_type text/plain; return 503 "Trajetoria em preparacao.\n"; }
}
NGINX
fi
ln -sfn /etc/nginx/sites-available/trajetoria.net.br /etc/nginx/sites-enabled/trajetoria.net.br
nginx -t
systemctl enable nginx mysql php8.3-fpm
systemctl restart php8.3-fpm
systemctl reload nginx
echo "Serviços preparados. Aplicação ainda em manutenção até concluir implantação e HTTPS."
