from pathlib import Path
import subprocess,json
R=Path(__file__).resolve().parents[1];php=R/'.runtime/php/php.exe';r=subprocess.run([str(php),str(R/'tools/request-asset-qa.php'),'/api/access/salaries/tables?tenant=DEMONSTRACAO'],cwd=php.parent,stdout=subprocess.PIPE,stderr=subprocess.PIPE,check=True);assert not r.stderr,r.stderr
d=json.loads(r.stdout);assert set(d)=={'frames','jobs','changes'},d.keys();assert len(d['frames'])==3 and len(d['jobs'])==14
(R/'.runtime/qa/tables.json').write_bytes(r.stdout)
subprocess.run(['C:/Program Files/nodejs/node.exe',str(R/'tools/test-salary-tables.cjs')],check=True)
(R/'output/verificacao-quadros-lista.json').write_text(json.dumps({'result':'OK','frames':3,'jobs':14,'noEmployeeData':True,'tableActions':True,'frameDetails':True,'runtime':'PHP 8.3 / SQLite e testes de interface em Node'},ensure_ascii=False,indent=2),encoding='utf8')
