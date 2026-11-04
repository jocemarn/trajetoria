"""Empacota apenas os arquivos instaláveis e assina a atualização local."""
from pathlib import Path
import hashlib,json,zipfile,shutil
from cryptography.hazmat.primitives.asymmetric import rsa,padding
from cryptography.hazmat.primitives import serialization,hashes
import base64,argparse
parser=argparse.ArgumentParser();parser.add_argument('--version',default=(Path(__file__).resolve().parents[2]/'VERSION').read_text(encoding='utf8').strip());args=parser.parse_args()
R=Path(__file__).resolve().parents[1];rel=R/'private/releases/1.0.0';out=R/'output';out.mkdir(exist_ok=True)
keypath=R/'.signing/update-private.pem'
if not keypath.exists():
 key=rsa.generate_private_key(public_exponent=65537,key_size=3072)
 keypath.write_bytes(key.private_bytes(serialization.Encoding.PEM,serialization.PrivateFormat.PKCS8,serialization.NoEncryption()))
else:key=serialization.load_pem_private_key(keypath.read_bytes(),password=None)
(R/'private/trusted-update-key.pem').write_bytes(key.public_key().public_bytes(serialization.Encoding.PEM,serialization.PublicFormat.SubjectPublicKeyInfo))
files={p.relative_to(rel).as_posix():p for folder in ['app','assets','data'] for p in (rel/folder).rglob('*') if p.is_file()}
assert not (rel/'assets/demonstracao/base.json').exists()
meta={name:dict(size=p.stat().st_size,sha256=hashlib.sha256(p.read_bytes()).hexdigest()) for name,p in sorted(files.items())}
m=dict(app='trajetoria',version='1.0.0',min_from='1.0.0',php='8.2.0',schema_from=1,schema_to=1,schema_min=1,schema_max=1,migrations=[],files=meta)
(rel/'release-manifest.json').write_text(json.dumps(m,separators=(',',':')),encoding='utf8')
with zipfile.ZipFile(out/'trajetoria-hostgator-1.0.0.zip','w',zipfile.ZIP_DEFLATED) as z:
 for name in ['.htaccess','public/.htaccess','public/index.php','public/install.php','private/.htaccess','private/active.json','private/install.key','private/demo-accounts.json','private/trusted-update-key.pem']:
  z.write(R/name,name)
 for name,p in files.items():z.write(p,'private/releases/1.0.0/'+name)
 z.write(rel/'release-manifest.json','private/releases/1.0.0/release-manifest.json')
 for folder in ['private/runtime/sessions/','private/data/','private/backups/']:z.writestr(folder,'')
m['version']=args.version;raw=json.dumps(m,separators=(',',':')).encode();signature=key.sign(raw,padding.PKCS1v15(),hashes.SHA256())
with zipfile.ZipFile(out/('trajetoria-atualizacao-'+args.version+'.zip'),'w',zipfile.ZIP_DEFLATED) as z:
 z.writestr('manifest.json',raw);z.writestr('signature.b64',base64.b64encode(signature))
 for name,p in files.items():z.write(p,'release/'+name)
for p in out.glob('*.zip'):print(p.name,p.stat().st_size,hashlib.sha256(p.read_bytes()).hexdigest())
