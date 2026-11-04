from pathlib import Path
from html.parser import HTMLParser
from urllib.parse import urljoin
import subprocess,shutil,json
R=Path(__file__).resolve().parents[1];rel=R/'private/releases/1.0.0';qa=R/'.runtime/qa/private/releases/1.0.0';shutil.copytree(rel,qa,dirs_exist_ok=True)
php=R/'.runtime/php/php.exe';runner=R/'tools/request-asset-qa.php'
def get(path):
 r=subprocess.run([str(php),str(runner),path],cwd=php.parent,stdout=subprocess.PIPE,stderr=subprocess.PIPE,check=True)
 assert not r.stderr,r.stderr.decode(errors='replace')
 return r.stdout
class Assets(HTMLParser):
 def __init__(self):super().__init__();self.refs=[]
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag in ['script','img']:self.refs.append(a['src'])
  if tag=='link' and a.get('rel')=='stylesheet':self.refs.append(a['href'])
html=get('/ambiente').decode('utf8');p=Assets();p.feed(html);assert len(p.refs)==26,p.refs
for ref in p.refs:
 assert ref.startswith('/workspace/'),ref
 assert urljoin('https://trajetoria.naeeducacao.com.br/',ref)=='https://trajetoria.naeeducacao.com.br'+ref
 expected=(rel/'assets'/ref.lstrip('/')).read_bytes();actual=get(ref)
 assert actual==expected,(ref,actual[:100])
print('OK: ambiente autenticado e 26 arquivos CSS, JavaScript e imagem servidos integralmente pelo PHP com sessão autenticada.')
(R/'output/verificacao-reparo-visual.json').write_text(json.dumps(dict(result='OK',page='/ambiente',assets=p.refs,test='PHP 8.3.35, roteamento real com sessão autenticada; navegador/HostGator ainda não verificados'),ensure_ascii=False,indent=2),encoding='utf8')
