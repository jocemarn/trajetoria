<?php declare(strict_types=1);

function setTrajetoriaHttpsOrigin(string $configFile,string $url='https://trajetoria.net.br'):array{
 if($url!=='https://trajetoria.net.br')throw new RuntimeException('Domínio HTTPS diferente do destino aprovado.');
 if(!is_file($configFile)||is_link($configFile))throw new RuntimeException('Configuração privada não encontrada ou incompatível.');
 $cfg=require $configFile;if(!is_array($cfg))throw new RuntimeException('Configuração inválida.');
 if(!in_array($cfg['url']??'',['http://177.93.129.219','http://trajetoria.net.br','https://trajetoria.net.br'],true))throw new RuntimeException('Origem atual não corresponde à implantação.');
 if($cfg['url']===$url&&($cfg['require_https']??false)===true&&!isset($cfg['temporary_http_origin']))return ['changed'=>false,'url'=>$url];
 $original=(string)file_get_contents($configFile);$stat=stat($configFile);$backupDir=dirname($configFile).'/backups/https-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
 if(!mkdir($backupDir,0700,true))throw new RuntimeException('Não foi possível preparar backup.');
 $backup=$backupDir.'/config.php';if(file_put_contents($backup,$original,LOCK_EX)===false)throw new RuntimeException('Backup não gravado.');chmod($backup,0600);
 $cfg['url']=$url;$cfg['require_https']=true;unset($cfg['temporary_http_origin']);
 $tmp=tempnam(dirname($configFile),'.https-');if($tmp===false)throw new RuntimeException('Arquivo temporário indisponível.');
 try{if(file_put_contents($tmp,"<?php\ndeclare(strict_types=1);\nreturn ".var_export($cfg,true).";\n",LOCK_EX)===false)throw new RuntimeException('Falha ao gravar a configuração.');chmod($tmp,$stat['mode']&0777);if(PHP_OS_FAMILY!=='Windows'){if(!chown($tmp,$stat['uid'])||!chgrp($tmp,$stat['gid']))throw new RuntimeException('Não foi possível preservar o proprietário.');}if(!rename($tmp,$configFile))throw new RuntimeException('Não foi possível ativar a configuração.');}finally{if(is_file($tmp))unlink($tmp);}
 return ['changed'=>true,'url'=>$url,'backup'=>$backup];
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){try{$r=setTrajetoriaHttpsOrigin($argv[1]??'/srv/trajetoria/app/private/config.php');echo json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}}
