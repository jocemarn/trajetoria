<?php
declare(strict_types=1);
$root=dirname(__DIR__);define('TR_PRIVATE',$root.'/.runtime/qa/private');define('TR_RELEASE',$root.'/private/releases/1.0.0');define('APP_VERSION','1.0.9');
foreach(['core','domain','api','salaries','annual','updates','profile','contracts'] as $module)require TR_RELEASE.'/app/'.$module.'.php';
require_once TR_RELEASE.'/app/employee-portal.php';
$_SESSION=[];$_GET=['tenant'=>'DEMONSTRACAO'];$n=0;
function epCheck(bool $ok,string $name):void{global $n;if(!$ok)throw new RuntimeException($name);$n++;}
function epUser(string $email):array{$u=one('SELECT * FROM tr_users WHERE email=?',[$email]);if(!$u)throw new RuntimeException('Fixture ausente');$_SESSION['uid']=$u['id'];return $u;}
function epDenied(callable $f,int $status):void{try{$f();}catch(Fault $e){epCheck($e->status===$status,'Status de bloqueio: '.$e->getMessage());return;}throw new RuntimeException('Operação não bloqueada');}
$e=epUser('servidor.multivinculo@demonstracao.invalid');$own=routeApi($e,'workspace','GET',[])['links'];$r=routeApi($e,'access/my-career','GET',[]);
epCheck(count($r['rows'])===2,'Duas matrículas próprias');epCheck(!array_diff(array_column($r['rows'],'linkId'),array_column($own,'id')),'Apenas IDs próprios');
epCheck(!isset($r['frames'])&&!isset($r['jobs']),'Não expõe catálogo salarial geral');
$road=$r['rows'][0]['roadmap'];epCheck($road['configured'],'Documento do Magistério reconhecido');
$horizontal=array_values(array_filter($road['paths'],fn($x)=>$x['type']==='Progressão horizontal'))[0];$vertical=array_values(array_filter($road['paths'],fn($x)=>$x['type']==='Promoção vertical'))[0];
epCheck($horizontal['score']===70&&$horizontal['hours']===40,'Metas horizontais do Magistério');epCheck($vertical['score']===null&&$vertical['hours']===null,'Habilitação vertical não herda pontuação ou horas da horizontal');
foreach($r['rows'] as $row)foreach($row['salaryOptions'] as $option){epCheck(isset($option['targetBasicCents'])&&!isset($option['targetCents']),'Proposta usa salário-base');epCheck($option['mode']==='reference','Proposta identificada como referência, sem concessão');}
foreach($r['rows'] as $row){epCheck(!isset($row['current']['error']),'Salário-base atual calculado');epCheck(!isset($row['current']['totalCents'])&&!isset($row['current']['functionCents']),'Não inclui gratificações ou remuneração total');}
epDenied(fn()=>routeApi($e,'access/my-career','POST',[]),405);epDenied(fn()=>routeApi($e,'access/initial-career','POST',[]),403);
$c=epUser('comissao.geral.1@demonstracao.invalid');$cw=routeApi($c,'workspace','GET',[])['links'];$other=array_values(array_filter($cw,fn($p)=>$p['personId']!==$c['person']))[0];
$cr=routeApi($c,'access/my-career','GET',[]);$ownIds=array_column(array_filter($cw,fn($p)=>$p['personId']===$c['person']),'id');epCheck(!array_diff(array_column($cr['rows'],'linkId'),$ownIds),'Comissão consulta somente sua própria remuneração');
$cOwn=record('DEMONSTRACAO','links',$cr['rows'][0]['linkId']);$q=salaryQuote('DEMONSTRACAO',$cOwn['salary'],$cOwn,today());epCheck($cr['rows'][0]['current']['basicCents']===$q['cents'],'Salário-base não substituído pelo valor da função');
$m=epUser('gestor@demonstracao.invalid');epDenied(fn()=>routeApi($m,'access/my-career','GET',[]),403);
query('SAVEPOINT ep_portal_test');
try{
 $p=record('DEMONSTRACAO','links',$other['id']);$t='DEMONSTRACAO';
 $b=['linkId'=>$p['id'],'version'=>$p['version'],'possessionDate'=>$p['admission'],'checked'=>true,'basis'=>'Documento fictício de ingresso'];
 saveInitialCareer($m,$b);$changed=record($t,'links',$p['id']);epCheck($changed['initialCareer']['level']==='1','Referência inicial no nível 1');epCheck($changed['initialCareer']['possessionDate']===$p['admission'],'Data da posse informada explicitamente');
 $b['version']=$changed['version'];$b['basis']='Retificação documentada';saveInitialCareer($m,$b);epCheck(count(record($t,'links',$p['id'])['initialCareerHistory'])===1,'Histórico da retificação preservado');
 $c=epUser('comissao.geral.1@demonstracao.invalid');$row=array_values(array_filter(routeApi($c,'workspace','GET',[])['links'],fn($r)=>$r['id']===$p['id']))[0];epCheck(!isset($row['initialCareer'])&&!isset($row['initialCareerHistory']),'Comissão não recebe referência salarial privada do avaliado');
 // Tabela de teste isolada: base em 2000, correções antes e depois da posse.
 $frame=save($t,'salary_frames',['id'=>uid(),'plan'=>$p['planId'],'name'=>'Fixture temporária do portal']);$job=record($t,'salary_jobs',$p['salary']['jobId']);unset($job['version']);$job['id']=uid();$job['frame']=$frame['id'];$job['base_from']='2000-01-01';$job['groups']['baseSequence']=0;$job=save($t,'salary_jobs',$job);
 foreach([['2001-01-01',100000],['2004-01-01',200000],['2006-01-01',500000]] as $i=>$change)save($t,'salary_changes',['id'=>uid(),'frame'=>$frame['id'],'job'=>null,'kind'=>'annual','rate'=>$change[1],'effective'=>$change[0],'sequence'=>$i+1]);
 $fixture=$p;$fixture['salary']['jobId']=$job['id'];$cell=initialCareerCell($t,$fixture);$fixture['initialCareer']=$cell+['possessionDate'=>'2005-01-01','planId'=>$p['planId'],'role'=>$p['role'],'workload'=>$p['workload'],'basis'=>'Fixture'];
 $base=salaryCell($job['source'],$cell['groupId'],$cell['rowIndex'])['cents'];$initial=initialCareerQuote($t,$fixture);$expected=roundRate(roundRate($base,1100000),1200000);
 epCheck($initial['basicCents']===$expected,'Somente correções vigentes até a posse entram no nível 1');epCheck($initial['basicCents']!==roundRate($expected,1500000),'Correção posterior à posse excluída');
 $fixture['initialCareer']['possessionDate']='1999-01-01';epDenied(fn()=>initialCareerQuote($t,$fixture),400);
 $leg=null;foreach(records($t,'links') as $link)if(record($t,'plans',$link['planId'])['family']==='legislativo'){$leg=$link;break;}
 $legRules=employeeRoadmap($t,$leg);epCheck($legRules['paths'][0]['score']===75&&$legRules['paths'][0]['hours']===30,'Legislativo tem pontuação e capacitação próprias');
 $plan=record($t,'plans',$p['planId']);$plan['basis']='Documento de teste diferente';save($t,'plans',$plan);$unconfigured=employeeRoadmap($t,$p);epCheck(!$unconfigured['configured']&&!$unconfigured['paths'],'Documento alterado não herda regras genéricas');
}finally{query('ROLLBACK TO ep_portal_test');query('RELEASE ep_portal_test');}
$_GET=['tenant'=>'OTHER'];epDenied(fn()=>routeApi($e,'access/my-career','GET',[]),403);
echo "OK: $n verificações de salário-base, evolução anual na posse, isolamento, histórico e proteção das referências dos avaliados.\n";
