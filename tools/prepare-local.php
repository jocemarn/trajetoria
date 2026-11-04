<?php declare(strict_types=1);
$root=getenv('TR_SYSTEM_ROOT')?:dirname(__DIR__).'/sistema';
$source=getenv('TR_RELEASE_SOURCE')?:$root.'/private/releases/1.0.0';
$version=getenv('TR_INITIAL_VERSION')?:'1.0.0';
if(!preg_match('/^\d+\.\d+\.\d+$/',$version))throw new RuntimeException('Versão inválida.');

$archive=dirname(__DIR__).'/vendor/pdfjs-6.4.299.zip';
$expected=trim(explode(' ',trim((string)file_get_contents(dirname(__DIR__).'/vendor/pdfjs.sha256')))[0]);
if(!is_file($archive)||!hash_equals($expected,hash_file('sha256',$archive)))throw new RuntimeException('Arquivo PDF.js ausente ou com hash inválido.');
if(!is_file($source.'/assets/pdfjs/pdf.worker.min.mjs')){
 $zip=new ZipArchive();if($zip->open($archive)!==true)throw new RuntimeException('Falha ao abrir PDF.js.');
 if(!is_dir($source.'/assets/pdfjs'))mkdir($source.'/assets/pdfjs',0750,true);
 if(!$zip->extractTo($source.'/assets/pdfjs'))throw new RuntimeException('Falha ao preparar PDF.js.');$zip->close();
}
$base=json_decode((string)file_get_contents($source.'/data/base.json'),true,512,JSON_THROW_ON_ERROR);
foreach(['output','.signing','private/runtime/sessions','private/data','private/backups'] as $dir)if(!is_dir($root.'/'.$dir))mkdir($root.'/'.$dir,0700,true);
if(!is_file($root.'/private/active.json'))file_put_contents($root.'/private/active.json',json_encode(['version'=>$version,'previous'=>null]));
if(!is_file($root.'/private/installed.lock')&&!is_file($root.'/private/install.key')){file_put_contents($root.'/private/install.key',bin2hex(random_bytes(32)));@chmod($root.'/private/install.key',0600);}
if(is_file($root.'/private/demo-accounts.json')){echo "Arquivos privados existentes preservados.\n";exit;}
$accounts=[];$used=[];
$add=function(string $login,string $name,string $role,?string $person=null,?string $unit=null,?string $power=null)use(&$accounts,&$used,$base):void{
 $a=['email'=>$login.'@demonstracao.invalid','name'=>$name,'role'=>$role,'person'=>$person,'unit'=>$unit,'power'=>$power,'password'=>'Trj!'.bin2hex(random_bytes(14))];
 if($person){foreach($base['people'] as $p)if($p['personId']===$person){$a['cpf']=$p['cpf'];break;}$used[$person]=true;}
 $accounts[]=$a;
};
$add('admin.geral','Administrador Geral DEMONSTRAÇÃO','platform');
$add('gestor','Gestor Municipal DEMONSTRAÇÃO','admin');
$add('gestor.educacao','Gestor da Educação','manager',null,'Educação','Executivo');
$add('gestor.legislativo','Gestor do Legislativo','manager',null,'Câmara Municipal','Legislativo');
$add('consulta','Consulta Municipal','viewer');
foreach($base['plans'] as $plan){
 $c=array_values(array_filter($base['people'],fn($p)=>$p['planId']===$plan['id']));
 usort($c,fn($a,$b)=>strcmp($a['admission'],$b['admission']));
 $members=[];foreach($c as $p){if(isset($members[$p['personId']]))continue;$members[$p['personId']]=true;$add('comissao.'.$plan['family'].'.'.count($members),$p['name'],'commission',$p['personId']);if(count($members)===3)break;}
}
$counts=array_count_values(array_column($base['people'],'personId'));
foreach($base['people'] as $p)if(($counts[$p['personId']]??0)>1&&!isset($used[$p['personId']])){$add('servidor.multivinculo',$p['name'],'employee',$p['personId']);break;}
foreach($base['people'] as $p)if(str_starts_with($p['admission'],'2025')&&!isset($used[$p['personId']])){$add('servidor.novo',$p['name'],'employee',$p['personId']);break;}
file_put_contents($root.'/output/usuarios-demonstracao.json',json_encode($accounts,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
foreach($accounts as &$a){$a['password_hash']=password_hash($a['password'],PASSWORD_DEFAULT);unset($a['password']);}unset($a);
file_put_contents($root.'/private/demo-accounts.json',json_encode($accounts,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
@chmod($root.'/private/demo-accounts.json',0600);@chmod($root.'/output/usuarios-demonstracao.json',0600);
echo "Preparação local concluída. Credenciais somente em sistema/output/ (ignorado pelo Git).\n";
