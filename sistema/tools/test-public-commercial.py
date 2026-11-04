from pathlib import Path
import subprocess,json,re
R=Path(__file__).resolve().parents[1];php=R/'.runtime/php/php.exe';rel=R/'private/releases/1.0.0'
def get(path):
 r=subprocess.run([str(php),str(R/'tools/request-public-qa.php'),path],cwd=php.parent,stdout=subprocess.PIPE,stderr=subprocess.PIPE,check=True);assert not r.stderr,r.stderr;return r.stdout
page=get('/').decode('utf8');assert 'data-contact-form' in page and 'Acessar' in page and 'data-carousel' in page;assert 'Carregando conta' not in page
refs=re.findall(r'(?:src|href)="(/(?:commercial/[^"#]+|workspace/logo-trajetoria.png))"',page)
for ref in set(refs):assert get(ref)==(rel/'assets'/ref.lstrip('/')).read_bytes(),ref
for endpoint in ['/api/site-editor','/api/site-leads']:
 d=json.loads(get(endpoint));assert 'error' in d and 'rows' not in d and 'content' not in d
assert b'Editor visual' not in get('/site-preview')
print('OK: site e imagens públicos, editor e contatos protegidos sem sessão.')
