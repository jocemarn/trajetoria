<?php declare(strict_types=1);
require __DIR__.'/../private/releases/1.0.0/app/core.php';
require __DIR__.'/../private/releases/1.0.0/app/installation-origin.php';
$dir=__DIR__.'/.qa-auth-origin-'.bin2hex(random_bytes(5));mkdir($dir,0700);$file=$dir.'/http-bootstrap.json';$n=0;
function originCheck(bool $v,string $message):void{global $n;if(!$v)throw new RuntimeException($message);$n++;}
function originDenied(callable $f):bool{try{$f();return false;}catch(Fault){return true;}}
$ip=['HTTP_HOST'=>'177.93.129.219','REMOTE_ADDR'=>'198.51.100.10','HTTPS'=>'off'];
try{
 originCheck(originDenied(fn()=>validateInstallationOrigin('http://177.93.129.219',$ip,$file)),'HTTP público deve exigir habilitação privada');
 file_put_contents($file,json_encode(['allowedOrigin'=>'http://177.93.129.219']));
 $p=validateInstallationOrigin('http://177.93.129.219',$ip,$file);
 originCheck(!$p['require_https']&&$p['temporary_http_origin'],'HTTP autorizado configura protocolo e origem temporários');
 originCheck(defaultInstallationOrigin($ip,$file)==='http://177.93.129.219','Endereço inicial corresponde ao IP habilitado');
 originCheck(originDenied(fn()=>validateInstallationOrigin('http://177.93.129.204',['HTTP_HOST'=>'177.93.129.204','REMOTE_ADDR'=>'198.51.100.10'],$file)),'Outro IP deve falhar');
 originCheck(originDenied(fn()=>validateInstallationOrigin('http://177.93.129.219:8080',['HTTP_HOST'=>'177.93.129.219:8080','REMOTE_ADDR'=>'198.51.100.10'],$file)),'Outra porta deve falhar');
 originCheck(originDenied(fn()=>validateInstallationOrigin('https://177.93.129.219',$ip,$file)),'Protocolo diferente da requisição deve falhar');
 foreach(['http://usuario@177.93.129.219','http://177.93.129.219/instalar','http://177.93.129.219?x=1','http://177.93.129.219#x','ftp://177.93.129.219'] as $url)originCheck(originDenied(fn()=>validateInstallationOrigin($url,$ip,$file)),'URL incompatível aceita');
 $https=['HTTP_HOST'=>'trajetoria.net.br','REMOTE_ADDR'=>'198.51.100.10','HTTPS'=>'on'];
 $p=validateInstallationOrigin('https://trajetoria.net.br',$https,$file);originCheck($p['require_https']&&!$p['temporary_http_origin'],'HTTPS continua obrigatório no domínio');
 originCheck(defaultInstallationOrigin($https,$file)==='https://trajetoria.net.br','HTTPS usa endereço definitivo');
 $p=validateInstallationOrigin('http://localhost',['HTTP_HOST'=>'localhost','REMOTE_ADDR'=>'127.0.0.1'],$dir.'/missing');originCheck(!$p['require_https'],'Desenvolvimento local preservado');
 file_put_contents($file,'{}');originCheck(originDenied(fn()=>validateInstallationOrigin('http://177.93.129.219',$ip,$file)),'Marcador incompleto não autoriza HTTP');
 file_put_contents($file,json_encode(['allowedOrigin'=>'http://example.test']));originCheck(originDenied(fn()=>validateInstallationOrigin('http://example.test',['HTTP_HOST'=>'example.test','REMOTE_ADDR'=>'198.51.100.10'],$file)),'Exceção pública deve limitar-se a IP');
 echo "OK: $n verificações de origem, protocolo e autorização temporária do instalador.\n";
}finally{if(is_file($file))unlink($file);rmdir($dir);}
