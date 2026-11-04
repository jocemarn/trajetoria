<?php
declare(strict_types=1);
$root=dirname(__DIR__);define('TR_PRIVATE',$root.'/.runtime/qa/private');define('TR_RELEASE',TR_PRIVATE.'/releases/1.0.0');
foreach(['runtime/sessions','data','backups'] as $dir)if(!is_dir(TR_PRIVATE.'/'.$dir))mkdir(TR_PRIVATE.'/'.$dir,0700,true);
$cfg=['url'=>'http://127.0.0.1:8188','dsn'=>'sqlite:'.TR_PRIVATE.'/qa.sqlite','db_user'=>null,'db_password'=>null,'key'=>base64_encode(random_bytes(32)),'demo_enabled'=>true,'require_https'=>false];
if(is_file(TR_PRIVATE.'/qa.sqlite'))throw new RuntimeException('Use uma pasta QA nova.');
file_put_contents(TR_PRIVATE.'/config.php','<?php return '.var_export($cfg,true).';');
require TR_RELEASE.'/app/core.php';require TR_RELEASE.'/app/schema.php';require TR_RELEASE.'/app/domain.php';require TR_RELEASE.'/app/seed.php';
$_SESSION=[];createSchema(db());txn(function(){seedDemo();$u=newUser('','qa.owner@example.invalid','Administrador QA','platform',null,null,null,false,'Qa!Owner2026TestOnly');query('UPDATE tr_users SET must_change=0 WHERE id=?',[$u['id']]);query('INSERT INTO tr_tenants(id,name,state,is_demo) VALUES(?,?,?,0)',['OTHER','Município isolado QA','QA']);$u=newUser('OTHER','qa.other@example.invalid','Gestor QA','admin',null,null,null,false,'Qa!Other2026TestOnly');query('UPDATE tr_users SET must_change=0 WHERE id=?',[$u['id']]);});
file_put_contents(TR_PRIVATE.'/installed.lock','qa');file_put_contents(TR_PRIVATE.'/active.json',j(['version'=>'1.0.0','previous'=>null]));
$links=records('DEMONSTRACAO','links');$plans=records('DEMONSTRACAO','plans');$count=0;foreach($plans as $p)foreach($p['jobs'] as $job)$count+=count($job['cells']);
echo j(['people'=>count(array_unique(array_column($links,'personId'))),'links'=>count($links),'plans'=>count($plans),'jobs'=>count(records('DEMONSTRACAO','salary_jobs')),'cells'=>$count,'units'=>count(records('DEMONSTRACAO','units')),'commissions'=>count(records('DEMONSTRACAO','appointments')),'documents'=>count(records('DEMONSTRACAO','certificates')),'accounts'=>(int)query('SELECT COUNT(*) FROM tr_users WHERE is_demo=1')->fetchColumn()]);
