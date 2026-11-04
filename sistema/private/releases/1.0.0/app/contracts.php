<?php
declare(strict_types=1);
function contractManagers(string $tenant):array{return query('SELECT id,name,email,active,must_change FROM tr_users WHERE tenant=? AND role=? AND unit IS NULL AND power IS NULL ORDER BY email',[$tenant,'admin'])->fetchAll();}
function initialContractManager(string $tenant,array $b):array{if(contractManagers($tenant))throw new Fault('O município já possui administrador. Use a redefinição de senha da conta existente.',409);$name=need($b['managerName']??'','nome do Gestor Municipal',120);$email=mb_strtolower(need($b['managerEmail']??'','e-mail do Gestor Municipal',190));if(one('SELECT id FROM tr_users WHERE email=?',[$email]))throw new Fault('Este e-mail já pertence a outra conta.',409);$m=newUser($tenant,$email,$name,'admin');audit($tenant,'contract.manager.create',$m['id']);return $m;}
function contractAdminApi(array $u,string $tenant,string $method,array $b):array{
 platformOwner($u);if($method!=='POST')throw new Fault('Método não permitido.',405);
 return txn(function()use($u,$tenant,$b){lockTenant($tenant);if(($b['action']??'')==='create')return initialContractManager($tenant,$b);
 if(($b['action']??'')!=='reset')throw new Fault('Ação de acesso inválida.');
 if(!password_verify((string)($b['password']??''),$u['password_hash']))throw new Fault('Confirme sua senha atual para redefinir o acesso municipal.',403);
 $m=one('SELECT * FROM tr_users WHERE id=? AND tenant=? AND role=? AND unit IS NULL AND power IS NULL',[$b['memberId']??'',$tenant,'admin']);if(!$m||!$m['active'])throw new Fault('Administrador municipal ativo não encontrado.',404);
 $password=bin2hex(random_bytes(12)).'aA!';query('UPDATE tr_users SET password_hash=?,must_change=1,auth_epoch=auth_epoch+1 WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$m['id']]);audit($tenant,'contract.manager.password.reset',$m['id']);return ['id'=>$m['id'],'temporaryPassword'=>$password];});
}
function contractsApi(array $u,array $tenant,string $method,array $b):array{
 $t=$tenant['id'];if($u['role']!=='platform'||$u['is_demo']&&!$tenant['is_demo'])throw new Fault('Contratos restritos à administração geral autorizada.',403);
 if($method==='GET')return ['rows'=>array_map(fn($r)=>json_decode($r['payload'],true),query('SELECT payload FROM tr_contracts WHERE tenant=?',[$t])->fetchAll()),'managers'=>contractManagers($t)];
 platformOwner($u);if(!in_array($method,['POST','PUT']))throw new Fault('Método não permitido.',405);
 $scope=need($b['scope']??'','abrangência',24);$status=need($b['status']??'','situação',24);$start=day($b['start']??'');$end=day($b['end']??'');if(!in_array($scope,['Executivo','Legislativo','Integrado'])||!in_array($status,['Ativo','Suspenso','Encerrado'])||$end<$start)throw new Fault('Contrato inválido.');
 return txn(function()use($u,$t,$method,$b,$scope,$status,$start,$end){lockTenant($t);$id=$method==='PUT'?need($b['id']??'','contrato',64):uid();$old=$method==='PUT'?one('SELECT payload FROM tr_contracts WHERE id=? AND tenant=?',[$id,$t]):null;if($method==='PUT'&&!$old)throw new Fault('Contrato não encontrado neste município.',404);
 $c=['id'=>$id,'number'=>need($b['number']??'','número',80),'scope'=>$scope,'start'=>$start,'end'=>$end,'status'=>$status];if($old){$prior=json_decode($old['payload'],true);$c['history']=$prior['history']??[];unset($prior['history']);$c['history'][]=['previous'=>$prior,'actor'=>$u['id'],'at'=>date(DATE_ATOM)];query('UPDATE tr_contracts SET payload=? WHERE id=? AND tenant=?',[j($c),$id,$t]);}else query('INSERT INTO tr_contracts(id,tenant,payload) VALUES(?,?,?)',[$id,$t,j($c)]);
 $result=['id'=>$id];if($method==='POST'&&!empty($b['managerEmail'])){$m=initialContractManager($t,$b);$result['temporaryPassword']=$m['temporaryPassword'];}audit($t,$old?'contract.update':'contract.create',$id);return $result;});
}
