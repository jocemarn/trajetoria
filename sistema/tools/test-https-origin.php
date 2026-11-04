<?php declare(strict_types=1);
require __DIR__.'/../../deploy/ubuntu/set-https-origin.php';
$dir=__DIR__.'/.qa-auth-https-'.bin2hex(random_bytes(5));mkdir($dir,0700);$file=$dir.'/config.php';$n=0;
function httpsCheck(bool $ok,string $message):void{global $n;if(!$ok)throw new RuntimeException($message);$n++;}
function httpsDenied(callable $f):bool{try{$f();return false;}catch(RuntimeException){return true;}}
try{
 $cfg=['url'=>'http://177.93.129.219','require_https'=>false,'temporary_http_origin'=>true,'dsn'=>'mysql:host=localhost;dbname=fixture','db_user'=>'fixture','db_password'=>bin2hex(random_bytes(8)),'key'=>base64_encode(random_bytes(32)),'demo_enabled'=>true,'installed_at'=>'2026-11-04'];
 file_put_contents($file,'<?php return '.var_export($cfg,true).';');chmod($file,0600);$before=file_get_contents($file);
 $r=setTrajetoriaHttpsOrigin($file);$updated=require $file;
 httpsCheck($updated['url']==='https://trajetoria.net.br'&&$updated['require_https']===true,'Ativa origem e HTTPS');
 httpsCheck(!isset($updated['temporary_http_origin']),'Remove indicador temporário');
 foreach(['dsn','db_user','db_password','key','demo_enabled','installed_at'] as $key)httpsCheck($updated[$key]===$cfg[$key],'Preserva '.$key);
 httpsCheck(file_get_contents($r['backup'])===$before,'Backup preserva configuração original');
 httpsCheck(setTrajetoriaHttpsOrigin($file)['changed']===false,'Transição repetida não altera configuração');
 httpsCheck(httpsDenied(fn()=>setTrajetoriaHttpsOrigin($file,'https://other.invalid')),'Outro destino deve ser recusado');
 echo "OK: $n verificações de transição HTTPS, backup e preservação dos dados privados.\n";
}finally{
 if(is_file($file))unlink($file);
 foreach(glob($dir.'/backups/*/config.php') as $p){unlink($p);rmdir(dirname($p));}
 if(is_dir($dir.'/backups'))rmdir($dir.'/backups');rmdir($dir);
}
