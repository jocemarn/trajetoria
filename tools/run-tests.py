import sys
if hasattr(sys.stdout, "reconfigure"): sys.stdout.reconfigure(encoding="utf-8", errors="replace")
from pathlib import Path
import os,shutil,subprocess,sys
R=Path(__file__).resolve().parents[1]
php=os.environ.get("PHP_BIN") or shutil.which("php")
node=os.environ.get("NODE_BIN") or shutil.which("node")
if not php or not node:raise SystemExit("PHP e Node.js são necessários. Defina PHP_BIN e NODE_BIN para runtimes portáteis.")
php=str(Path(php).resolve());node=str(Path(node).resolve())
def run(args,cwd=R):
    r=subprocess.run([str(x) for x in args],cwd=cwd,capture_output=True,text=True,encoding="utf8",errors="replace")
    if r.returncode:
        print(r.stdout,r.stderr);raise SystemExit(r.returncode)
    return r.stdout.strip()
files=list((R/"sistema/private/releases/1.0.0/app").glob("*.php"))+list((R/"sistema/public").glob("*.php"))+[R/"tools/prepare-local.php"]
for p in files:run([php,"-l",p],Path(php).parent)
print(f"OK: sintaxe de {len(files)} arquivos PHP.",flush=True)
print(run([php,R/"sistema/tools/test-login-session.php"],Path(php).parent),flush=True)
for test in ["test-installation-origin.php","test-https-origin.php"]:
    print(run([php,R/"sistema/tools"/test],Path(php).parent),flush=True)
for name in ["test-commission-board.mjs","test-commission-board-ui.mjs","test-employee-portal-ui.mjs","test-career-path-ui.mjs","test-login-client.mjs"]:
    print(run([node,R/"sistema/tools"/name]),flush=True)
print("OK: testes básicos concluídos. Testes de banco/HTTP completos exigem a fixture documentada.",flush=True)
