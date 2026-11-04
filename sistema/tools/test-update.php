<?php
declare(strict_types=1);
$root=dirname(__DIR__);define('TR_PRIVATE',$root.'/.runtime/qa/private');$active=json_decode(file_get_contents(TR_PRIVATE.'/active.json'),true);define('APP_VERSION',$active['version']);define('TR_RELEASE',TR_PRIVATE.'/releases/'.APP_VERSION);
require TR_RELEASE.'/app/core.php';require TR_RELEASE.'/app/updates.php';$_SESSION=[];
$u=one('SELECT * FROM tr_users WHERE email=?',['qa.owner@example.invalid']);$_SESSION['uid']=$u['id'];
$metaBefore=query('SELECT id,value FROM tr_meta ORDER BY id')->fetchAll();$before=query('SELECT tenant,kind,id,version,payload FROM tr_records ORDER BY tenant,kind,id')->fetchAll();$docs=[];foreach(glob(TR_PRIVATE.'/data/*.enc') as $p)$docs[basename($p)]=hash_file('sha256',$p);
if(($argv[1]??'')==='apply'){$version=$argv[2]??'1.0.3';$r=applyUpdate($root.'/output/trajetoria-atualizacao-'.$version.'.zip',$u);if($r['version']!==$version)throw new RuntimeException('Versão incorreta');}
else{$r=maintenanceApi($u,'rollback','POST',['password'=>'Qa!Owner2026TestOnly']);if($r['version']!=='1.0.0')throw new RuntimeException('Reversão incorreta');}
if($metaBefore!==query('SELECT id,value FROM tr_meta ORDER BY id')->fetchAll())throw new RuntimeException('Os metadados do site mudaram');if($before!==query('SELECT tenant,kind,id,version,payload FROM tr_records ORDER BY tenant,kind,id')->fetchAll())throw new RuntimeException('Os registros mudaram');foreach($docs as $p=>$hash)if(hash_file('sha256',TR_PRIVATE.'/data/'.$p)!==$hash)throw new RuntimeException('O documento mudou');if(is_file(TR_PRIVATE.'/runtime/maintenance.json'))throw new RuntimeException('Bloqueio não liberado');echo j(['result'=>'OK','operation'=>$argv[1],'version'=>$r['version'],'documentsPreserved'=>count($docs)]);
