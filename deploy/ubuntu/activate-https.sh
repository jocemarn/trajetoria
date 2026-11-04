#!/usr/bin/env bash
set -euo pipefail
[[ $EUID -eq 0 ]] || { echo "Execute com sudo."; exit 1; }
EXPECTED_IP="${1:-177.93.129.219}"
python3 - "$EXPECTED_IP" <<'PY'
import socket,sys
expected=sys.argv[1]
try:addresses={v[4][0] for v in socket.getaddrinfo('trajetoria.net.br',80,socket.AF_INET)}
except socket.gaierror:raise SystemExit('DNS de trajetoria.net.br ainda não resolve. Configure o registro A antes do HTTPS.')
if addresses!={expected}:raise SystemExit('DNS ainda não aponta exclusivamente para o IP esperado: '+', '.join(sorted(addresses)))
print('Registro A confirmado.')
PY
certbot certonly --webroot -w /var/www/trajetoria-acme -d trajetoria.net.br --non-interactive --agree-tos --register-unsafely-without-email
install -m 0644 "$(dirname "$0")/nginx-https.conf" /etc/nginx/sites-available/trajetoria.net.br
nginx -t
systemctl reload nginx
install -d -m 0755 /etc/letsencrypt/renewal-hooks/deploy
printf '#!/bin/sh\nsystemctl reload nginx\n' > /etc/letsencrypt/renewal-hooks/deploy/trajetoria-reload
chmod 0755 /etc/letsencrypt/renewal-hooks/deploy/trajetoria-reload
systemctl enable --now certbot.timer
echo "HTTPS configurado. Abra https://trajetoria.net.br/instalar."
