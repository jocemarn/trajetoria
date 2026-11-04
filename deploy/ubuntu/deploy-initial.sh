#!/usr/bin/env bash
set -euo pipefail
EXPECTED_HOSTNAME="${1:?Informe o nome verificado da máquina}"
[[ "$(hostname)" == "$EXPECTED_HOSTNAME" ]] || { echo "Nome da máquina diferente do destino aprovado."; exit 1; }
[[ $EUID -eq 0 ]] || { echo "Execute com sudo."; exit 1; }
REPOSITORY=/srv/trajetoria/repository
APP=/srv/trajetoria/app
if [[ -f "$APP/private/installed.lock" ]]; then
    echo "Sistema já instalado. Use o atualizador assinado para preservar o fluxo de versões."; exit 1
fi
if [[ ! -d "$REPOSITORY/.git" ]]; then
    install -d -o ubuntu -g ubuntu /srv/trajetoria
    sudo -u ubuntu git clone https://github.com/jocemarn/trajetoria.git "$REPOSITORY"
else
    [[ -z "$(sudo -u ubuntu git -C "$REPOSITORY" status --porcelain)" ]] || { echo "Checkout com alterações locais."; exit 1; }
    sudo -u ubuntu git -C "$REPOSITORY" pull --ff-only origin main
fi
VERSION="$(tr -d '\r\n' < "$REPOSITORY/VERSION")"
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || exit 1
SOURCE="$REPOSITORY/sistema/private/releases/1.0.0"
[[ -f "$SOURCE/app/bootstrap.php" ]] || { echo "Código completo ainda não está disponível."; exit 1; }
install -d -m 0755 "$APP"
install -d -m 0750 "$APP/private" "$APP/private/releases"
install -d -m 0750 "$APP/private/releases/$VERSION"
cp -a "$SOURCE"/. "$APP/private/releases/$VERSION"/
cp -a "$REPOSITORY/sistema/public" "$APP/"
TR_SYSTEM_ROOT="$APP" TR_RELEASE_SOURCE="$APP/private/releases/$VERSION" TR_INITIAL_VERSION="$VERSION" php "$REPOSITORY/tools/prepare-local.php"
install -m 0640 "$REPOSITORY/deploy/ubuntu/update-public-key.txt" "$APP/private/trusted-update-key.pem"
python3 - "$APP" "$VERSION" "$REPOSITORY" <<'PY'
from pathlib import Path
import hashlib,json,sys,subprocess
app=Path(sys.argv[1]);version=sys.argv[2];repo=Path(sys.argv[3]);rel=app/'private/releases'/version
files={}
for folder in ['app','assets','data']:
 for p in (rel/folder).rglob('*'):
  if p.is_file():files[p.relative_to(rel).as_posix()]={'size':p.stat().st_size,'sha256':hashlib.sha256(p.read_bytes()).hexdigest()}
manifest={'app':'trajetoria','version':version,'min_from':'1.0.0','php':'8.2.0','schema_from':1,'schema_to':1,'schema_min':1,'schema_max':1,'migrations':[],'files':files}
(rel/'release-manifest.json').write_text(json.dumps(manifest,separators=(',',':')))
commit=subprocess.check_output(['git','-c',f'safe.directory={repo}','-C',str(repo),'rev-parse','HEAD'],text=True).strip()
(app/'private/runtime/github-deployment.json').write_text(json.dumps({'repository':'jocemarn/trajetoria','commit':commit,'version':version}))
c=json.loads(Path('/etc/trajetoria/database.json').read_text())
text=f"TRAJETORIA - INSTALACAO NOVA\n\nEndereco: https://trajetoria.net.br/instalar\nBanco: {c['name']}\nUsuario MySQL: {c['user']}\nServidor MySQL: localhost\nSenha MySQL: {c['password']}\nChave de instalacao: {(app/'private/install.key').read_text().strip()}\n\nCrie sua conta pessoal no instalador. Nao publique este arquivo.\n"
p=Path('/home/ubuntu/trajetoria-acesso-instalacao.txt');p.write_text(text);p.chmod(0o600)
PY
chown ubuntu:ubuntu /home/ubuntu/trajetoria-acesso-instalacao.txt
chown -R www-data:www-data "$APP/private"
find "$APP/private" -type d -exec chmod 0750 {} +
find "$APP/private" -type f -exec chmod 0640 {} +
chmod 0600 "$APP/private/install.key" "$APP/private/demo-accounts.json"
chown -R root:www-data "$APP/public"
find "$APP/public" -type d -exec chmod 0755 {} +
find "$APP/public" -type f -exec chmod 0644 {} +
chown -R ubuntu:ubuntu "$APP/output"
chmod 0700 "$APP/output"
chmod 0600 "$APP/output/usuarios-demonstracao.json"
echo "Código instalado. Dados de instalação disponíveis somente no arquivo privado do usuário ubuntu."
