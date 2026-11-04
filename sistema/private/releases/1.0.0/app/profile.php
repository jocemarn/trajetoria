<?php
declare(strict_types=1);
// Preferências da conta ficam criptografadas, separadas dos dados funcionais.
function profileExtra(string $id):array{$r=one('SELECT value FROM tr_meta WHERE id=?',['profile:'.$id]);return $r?json_decode(unseal($r['value']),true,32,JSON_THROW_ON_ERROR):['phone'=>'','photo'=>null];}
function saveProfileExtra(string $id,array $data):void{$key='profile:'.$id;$value=seal(j($data));if(strlen($value)>60000)throw new Fault('A foto excede o tamanho permitido.');if(one('SELECT id FROM tr_meta WHERE id=?',[$key]))query('UPDATE tr_meta SET value=? WHERE id=?',[$value,$key]);else query('INSERT INTO tr_meta(id,value) VALUES(?,?)',[$key,$value]);}
function profileApi(array $u,string $method,array $b):array{
 if($method==='GET'){$p=profileExtra($u['id']);return ['name'=>$u['name'],'email'=>$u['email'],'phone'=>$p['phone']??'','photo'=>$p['photo']??null,'role'=>$u['role']];}
 if($method!=='POST')throw new Fault('Método não permitido.',405);
 if(array_diff(array_keys($b),['name','email','phone','currentPassword','photo']))throw new Fault('Campo de conta não permitido.');
 $name=need($b['name']??'','nome de exibição',120);$email=mb_strtolower(need($b['email']??'','e-mail',190));if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new Fault('E-mail inválido.');$phone=trim((string)($b['phone']??''));if(strlen($phone)>30||$phone&&!preg_match('/^[0-9+() .-]+$/',$phone))throw new Fault('Telefone inválido.');
 $emailChanged=$email!==$u['email'];if($emailChanged&&!password_verify((string)($b['currentPassword']??''),$u['password_hash']))throw new Fault('Confirme sua senha atual para alterar o e-mail.',403);
 $extra=profileExtra($u['id']);$extra['phone']=$phone;
 if(array_key_exists('photo',$b)){$photo=$b['photo'];if($photo!==null){if(!is_string($photo)||!preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$#D',$photo,$m))throw new Fault('Use uma foto JPG, PNG ou WebP.');$bytes=base64_decode($m[2],true);$info=$bytes===false?false:@getimagesizefromstring($bytes);if(!$info||strlen($bytes)>24000||$info[0]>512||$info[1]>512||$info[0]<1||$info[1]<1||$info['mime']!=='image/'.$m[1])throw new Fault('Foto inválida. Envie pela opção de foto do perfil para ajustar tamanho e formato.');}$extra['photo']=$photo;}
 try{txn(function()use($u,$name,$email,$emailChanged,$extra){$sql='SELECT id FROM tr_users WHERE id=?'.(db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'');one($sql,[$u['id']]);$other=one('SELECT id FROM tr_users WHERE email=? AND id<>?',[$email,$u['id']]);if($other)throw new Fault('Este e-mail já está associado a outra conta.',409);query('UPDATE tr_users SET name=?,email=?,auth_epoch=auth_epoch+? WHERE id=?',[$name,$email,$emailChanged?1:0,$u['id']]);saveProfileExtra($u['id'],$extra);audit($u['tenant']??'SYSTEM','account.profile.update',$u['id']);});}catch(PDOException $e){if(in_array((string)$e->getCode(),['23000','23505'],true))throw new Fault('Este e-mail já está associado a outra conta.',409);throw $e;}
 if($emailChanged){$_SESSION['epoch']=(int)$u['auth_epoch']+1;if(session_status()===PHP_SESSION_ACTIVE)session_regenerate_id(true);}
 return ['ok'=>true,'name'=>$name,'email'=>$email,'phone'=>$phone,'photo'=>$extra['photo']??null];
}
