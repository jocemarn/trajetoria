import sys
if hasattr(sys.stdout, "reconfigure"): sys.stdout.reconfigure(encoding="utf-8", errors="replace")
from pathlib import Path
import re,subprocess,sys
R=Path(__file__).resolve().parents[1]
def private(name):
    if name == "vendor/pdfjs-6.4.299.zip": return False
    parts=Path(name).parts
    return any(p in {".git",".runtime",".signing",".secrets","node_modules","__pycache__","output","tmp"} or p.startswith(".qa-auth-") for p in parts) or name in {"sistema/private/config.php","sistema/private/install.key","sistema/private/installed.lock","sistema/private/demo-accounts.json","sistema/private/active.json","sistema/private/trusted-update-key.pem"} or any(name.startswith(p) for p in ["sistema/private/runtime/","sistema/private/data/","sistema/private/backups/"]) or Path(name).suffix.lower() in {".pem",".key",".sqlite",".db",".sql",".enc",".log",".zip"} or Path(name).name in {".env",".dev.vars"} or Path(name).name.startswith(".env.")
tracked=False
if (R/".git").exists():
    r=subprocess.run(["git","-C",str(R),"ls-files","--cached","--others","--exclude-standard","-z"],capture_output=True)
    if r.returncode:raise SystemExit("Não foi possível conferir os arquivos do Git.")
    files=[x.decode("utf8") for x in r.stdout.split(b"\0") if x];tracked=True
else:
    files=[p.relative_to(R).as_posix() for p in R.rglob("*") if p.is_file() and not private(p.relative_to(R).as_posix())]
errors=[]
marker=re.compile(rb"-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----")
for name in files:
    if private(name):
        errors.append("Caminho privado: "+name);continue
    data=(R/name).read_bytes()
    if marker.search(data):errors.append("Chave privada: "+name)
    if re.search(rb"gh[pousr]_[A-Za-z0-9]{30,}",data):errors.append("Possível token GitHub: "+name)
    if len(data)>50*1024*1024:errors.append("Arquivo muito grande: "+name)
if errors:print("\n".join(errors));raise SystemExit(1)
print(f"OK: {len(files)} arquivos publicáveis conferidos. Dados privados e marcadores de chaves não encontrados.")
