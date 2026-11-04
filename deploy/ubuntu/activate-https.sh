#!/usr/bin/env bash
set -euo pipefail
[[ $EUID -eq 0 ]] || { echo "Execute com sudo."; exit 1; }
EXPECTED_IP="${1:?Informe o IP público aprovado}"
EXPECTED_HOSTNAME="${2:?Informe o nome verificado da máquina}"
[[ "$(hostname)" == "$EXPECTED_HOSTNAME" ]] || { echo "Nome da máquina diferente do destino aprovado."; exit 1; }
python3 - "$EXPECTED_IP" <<'PY'
import socket,sys
expected=sys.argv[1]
try:addresses={v[4][0] for v in socket.getaddrinfo('trajetoria.net.br',80,socket.AF_INET)}
except socket.gaierror:raise SystemExit('DNS de trajetoria.net.br ainda não resolve. Configure o registro A antes do HTTPS.')
if addresses!={expected}:raise SystemExit('DNS ainda não aponta exclusivamente para o IP esperado: '+', '.join(sorted(addresses)))
print('Registro A confirmado.')
PY
certbot certonly --webroot -w /var/www/trajetoria-acme -d trajetoria.net.br --non-interactive --agree-tos --register-unsafely-without-email
CONF=/etc/nginx/sites-available/trajetoria.net.br
APP_CONFIG=/srv/trajetoria/app/private/config.php
BACKUP="${CONF}.before-https-$(date -u +%Y%m%d%H%M%S)"
cp -p "$CONF" "$BACKUP"
CONFIG_BACKUP=""
if [[ -f "$APP_CONFIG" ]]; then
    CONFIG_BACKUP="${APP_CONFIG}.before-https-$(date -u +%Y%m%d%H%M%S)"
    cp -p "$APP_CONFIG" "$CONFIG_BACKUP"
fi
rollback() { cp -p "$BACKUP" "$CONF"; if [[ -n "$CONFIG_BACKUP" ]]; then cp -p "$CONFIG_BACKUP" "$APP_CONFIG"; fi; systemctl reload nginx || true; }
trap rollback ERR
install -m 0644 "$(dirname "$0")/nginx-https.conf" /etc/nginx/sites-available/trajetoria.net.br
nginx -t 2>&1
if [[ -f /srv/trajetoria/app/private/config.php ]]; then
    php "$(dirname "$0")/set-https-origin.php" /srv/trajetoria/app/private/config.php
fi
systemctl reload nginx
trap - ERR
rm -f /srv/trajetoria/app/private/runtime/http-bootstrap.json
install -d -m 0755 /etc/letsencrypt/renewal-hooks/deploy
printf '#!/bin/sh\nsystemctl reload nginx\n' > /etc/letsencrypt/renewal-hooks/deploy/trajetoria-reload
chmod 0755 /etc/letsencrypt/renewal-hooks/deploy/trajetoria-reload
systemctl enable --now certbot.timer
echo "HTTPS configurado. Abra https://trajetoria.net.br/instalar."
