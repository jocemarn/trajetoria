<?php declare(strict_types=1);
$dir=__DIR__.'/.qa-auth-'.bin2hex(random_bytes(5));mkdir($dir,0700);
file_put_contents($dir.'/config.php','<?php return '.var_export(['url'=>'https://example.test','dsn'=>'sqlite:'.$dir.'/test.sqlite','db_user'=>null,'db_password'=>null,'demo_enabled'=>false],true).';');
define('TR_PRIVATE',$dir);require __DIR__.'/../private/releases/1.0.0/app/core.php';
$checks=0;
function check(bool $ok,string $why):void{global $checks;if(!$ok)throw new RuntimeException($why);$checks++;}
function rejected(callable $fn,int $status):bool{try{$fn();return false;}catch(Fault $e){return $e->status===$status;}}
try{
 db()->exec('CREATE TABLE tr_users(id TEXT PRIMARY KEY,active INT,auth_epoch INT,is_demo INT,demo_until TEXT,must_change INT)');
 query('INSERT INTO tr_users VALUES(?,?,?,?,?,?)',['test-user',1,1,0,null,0]);
 $_SERVER['REQUEST_URI']='/login';$_SERVER['HTTP_ORIGIN']='https://example.test';
 $old=str_repeat('a',64);
 foreach(['expired','epoch','inactive','missing'] as $case){
  query('UPDATE tr_users SET active=1 WHERE id=?',['test-user']);
  $_SESSION=['uid'=>$case==='missing'?'missing':'test-user','csrf'=>$old,'epoch'=>$case==='epoch'?0:1,'at'=>$case==='expired'?time()-1801:time(),'started'=>time()];
  if($case==='inactive')query('UPDATE tr_users SET active=0 WHERE id=?',['test-user']);
  check(rejected(fn()=>auth(),401),'Sessão inválida deve ser recusada: '.$case);
  check(!isset($_SESSION['uid']),'Identidade antiga removida');
  check(isset($_SESSION['csrf'])&&preg_match('/^[a-f0-9]{64}$/',$_SESSION['csrf'])===1,'Novo token disponível para renderizar login');
  check($_SESSION['csrf']!==$old,'Token antigo descartado');
  $_SERVER['HTTP_X_CSRF_TOKEN']=$_SESSION['csrf'];csrf();$checks++;
  $_SERVER['HTTP_X_CSRF_TOKEN']=$old;check(rejected(fn()=>csrf(),403),'Token antigo não pode acessar formulário');
 }
 $_SESSION=[];unset($_SERVER['HTTP_X_CSRF_TOKEN']);check(rejected(fn()=>csrf(),403),'Ausência de ambos os tokens deve falhar');
 $_SESSION=['csrf'=>''];$_SERVER['HTTP_X_CSRF_TOKEN']='';check(rejected(fn()=>csrf(),403),'Tokens vazios devem falhar');
 $_SESSION=['csrf'=>str_repeat('b',64)];$_SERVER['HTTP_X_CSRF_TOKEN']=$_SESSION['csrf'];$_SERVER['HTTP_ORIGIN']='https://foreign.test';check(rejected(fn()=>csrf(),403),'Origem externa deve falhar');
 $_SERVER['HTTP_ORIGIN']='https://example.test';csrf();$checks++;
 query('UPDATE tr_users SET active=1 WHERE id=?',['test-user']);
 $_SESSION=['uid'=>'test-user','epoch'=>1,'at'=>time(),'started'=>time(),'csrf'=>$old];
 check(auth()['id']==='test-user','Sessão válida deve permanecer válida');check($_SESSION['csrf']===$old,'Sessão válida preserva token');
 echo "OK: $checks verificações de sessão expirada, token de formulário, origem e sessão válida.\n";
}finally{foreach(glob($dir.'/*') as $file)@unlink($file);@rmdir($dir);}
