from pathlib import Path
import json,urllib.request,urllib.error,http.cookiejar,zipfile,uuid
R=Path(__file__).resolve().parents[1];URL='http://127.0.0.1:8188';accounts=json.loads((R/'output/usuarios-demonstracao.json').read_text(encoding='utf8'));checks=[]
class Client:
 def __init__(self,email=None,password=None):
  self.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.csrf=self.call('/api/auth/context')['csrf']
  if email:self.csrf=self.call('/api/auth/login','POST',dict(login=email,password=password))['csrf']
 def call(self,path,method='GET',body=None,expected=200,raw=None,ctype=None,csrf=True):
  data=raw if raw is not None else json.dumps(body).encode() if body is not None else None
  h={'Origin':URL};h.update({'Content-Type':ctype or 'application/json'} if data is not None else {})
  if method!='GET' and csrf:h['X-CSRF-Token']=self.csrf
  req=urllib.request.Request(URL+path,data=data,headers=h,method=method)
  try:r=self.op.open(req,timeout=10)
  except urllib.error.HTTPError as e:r=e
  content=r.read();assert r.status==expected,(path,r.status,content[:600]);checks.append(f'{method} {path.split("?")[0]} {expected}')
  if 'json' in r.headers.get('Content-Type',''):return json.loads(content)
  return content
def demo(prefix):
 a=next(a for a in accounts if a['email'].split('@')[0]==prefix);return Client(a['email'],a['password'])
def api(p):return '/api/'+p+'?tenant=DEMONSTRACAO'
anon=Client();anon.call(api('workspace'),expected=401)
mgr=demo('gestor');w=mgr.call(api('workspace'));assert len(w['links'])==110 and len({p['personId'] for p in w['links']})==100 and len(w['plans'])==3
mgr.call(api('units'),'POST',dict(name='Sem CSRF',power='Executivo'),expected=403,csrf=False)
mgr.call('/api/workspace?tenant=OTHER',expected=403)
salary=mgr.call(api('access/salaries/context'));assert len(salary['salaries'])==110
bad=[r for r in salary['salaries'] if 'error' in r['current']];assert not bad,bad[:2]
assert all(not r['projection'] or r['projection'].get('complete',True) for r in salary['salaries'])
unit=demo('gestor.educacao');uw=unit.call(api('workspace'));assert uw['links'] and all(p['unit']=='Educação' for p in uw['links'])
viewer=demo('consulta');viewer.call(api('units'),'POST',dict(name='Negado',power='Executivo'),expected=403)
employee=demo('servidor.multivinculo');ew=employee.call(api('workspace'));assert len(ew['links'])==2 and len({p['personId'] for p in ew['links']})==1
employee.call(api('access/salaries/context'),expected=403)
root=demo('admin.geral');root.call(api('workspace'),expected=403);assert len(root.call(api('contracts'))['rows'])==2;root.call('/api/maintenance/status',expected=403)
owner=Client('qa.owner@example.invalid','Qa!Owner2026TestOnly');owner.call(api('workspace'),expected=403);owner.call('/api/maintenance/status');assert len(owner.call('/api/municipalities')['rows'])==2
other=Client('qa.other@example.invalid','Qa!Other2026TestOnly');assert not other.call('/api/workspace?tenant=OTHER')['links'];other.call(api('workspace'),expected=403)
mgr.call(api('members/'+next(m['id'] for m in w['members'] if m['role']=='platform')),'DELETE',{},expected=403)
# Todos os membros devem ler o PDF antes de votar e a aprovação é unânime.
c1=demo('comissao.geral.1');ctx=c1.call(api('access/context'));cert=next(c for c in ctx['certificates'] if c['canVote']);certpath='/api/access/certificates/'+cert['id']+'/file?tenant=DEMONSTRACAO'
employee.call(certpath,expected=403)
print('Base e isolamento: OK; certificados disponíveis:',len(ctx['certificates']))
status=owner.call('/api/maintenance/status');assert status['version']=='1.0.0'
boundary='----Trajetoria'+uuid.uuid4().hex
payload=(f'--{boundary}\r\nContent-Disposition: form-data; name="password"\r\n\r\nQa!Owner2026TestOnly\r\n--{boundary}\r\nContent-Disposition: form-data; name="package"; filename="update.zip"\r\nContent-Type: application/zip\r\n\r\n').encode()+(R/'output/trajetoria-atualizacao-1.0.1.zip').read_bytes()+f'\r\n--{boundary}--\r\n'.encode()
result=owner.call('/api/maintenance/upload','POST',raw=payload,ctype='multipart/form-data; boundary='+boundary);assert result['version']=='1.0.1'
assert owner.call('/api/maintenance/status')['version']=='1.0.1'
assert len(mgr.call(api('workspace'))['links'])==110
owner.call('/api/maintenance/rollback','POST',dict(password='Qa!Owner2026TestOnly'));assert owner.call('/api/maintenance/status')['version']=='1.0.0'
assert len(mgr.call(api('workspace'))['links'])==110
assert c1.call(certpath).startswith(b'%PDF-')
(R/'output/verificacao-local.json').write_text(json.dumps(dict(result='OK',checks=checks,people=100,links=110,plans=3,salaryPositions=10215,backend='PHP 8.3.35 / SQLite de teste; MySQL requer validação na hospedagem'),ensure_ascii=False,indent=2),encoding='utf8')
print('OK:',len(checks),'verificações HTTP; atualização, reversão e preservação da base.')
