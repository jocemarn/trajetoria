<?php
declare(strict_types=1);
require_once __DIR__.'/career-path.php';
require_once __DIR__.'/employee-salary-paths.php';
function initialCareerCell(string $tenant,array $p):array{
 $map=$p['salary']??null;if(!$map)throw new Fault('Vincule a tabela do cargo antes de conferir o ingresso.');
 $job=record($tenant,'salary_jobs',$map['jobId']);if(!$job)throw new Fault('Tabela do cargo indisponível.');
 $cell=salaryCellFromInitialMap($job['source'],$map['groupId']);
 return ['jobId'=>$map['jobId'],'groupId'=>$map['groupId'],'rowIndex'=>$cell['index'],'level'=>(string)$cell['row'][0],'reference'=>(string)$cell['row'][1]];
}
function salaryCellFromInitialMap(string $sourceId,string $groupId):array{
 // O grupo já foi conferido no mapa atual. A origem é obtida sem consulta global.
 foreach(salaryCatalog()['sources'] as $source)if($source['id']===$sourceId)foreach($source['jobs'] as $group)if($group['id']===$groupId){
  $data=json_decode((string)file_get_contents(TR_RELEASE.'/data/salary/'.basename($group['data'])),true);$matches=[];
  foreach($data['rows'] as $i=>$row)if(in_array((string)$row[0],['1','01','I'],true))$matches[]=['index'=>$i,'row'=>$row];
  usort($matches,fn($a,$b)=>strnatcmp((string)$a['row'][1],(string)$b['row'][1])?:((float)$a['row'][2]<=>(float)$b['row'][2]));
  if($matches)return $matches[0];
 }
 throw new Fault('A tabela vinculada não contém referência inicial do nível 1.');
}
function initialCareerQuote(string $tenant,array $p):array{
 $initial=$p['initialCareer']??null;
 if(!$initial)throw new Fault('O gestor precisa conferir a data da posse e a referência do ingresso.');
 if($initial['planId']!==$p['planId']||$initial['role']!==$p['role'])throw new Fault('Plano ou cargo alterado. Reconfira o ingresso.');
 $at=day($initial['possessionDate']);$historic=$p;$historic['workload']=$initial['workload'];$historic['level']=$initial['level'];$historic['stage']=$initial['reference'];
 $map=['jobId'=>$initial['jobId'],'groupId'=>$initial['groupId'],'rowIndex'=>$initial['rowIndex'],'context'=>salaryContextKey($historic)];
 try{$q=salaryQuote($tenant,$map,$historic,$at);}catch(Fault $e){throw new Fault('Histórico salarial na posse indisponível: '.$e->getMessage());}
 return ['date'=>$at,'basicCents'=>$q['cents'],'level'=>$initial['level'],'reference'=>$initial['reference'],'law'=>$q['law'],'basis'=>$initial['basis'],'workload'=>$initial['workload']];
}
function employeeCareer(array $u,string $method):array{
 if($method!=='GET')throw new Fault('Método não permitido.',405);
 if(!in_array($u['role'],['employee','commission'])||empty($u['person']))throw new Fault('Consulta restrita às próprias matrículas.',403);
 $rows=[];
 foreach(records($u['tenant'],'links') as $p){
  if($p['personId']!==$u['person']||!visibleLink($u,$p))continue;
  try{$initial=initialCareerQuote($u['tenant'],$p);}catch(Fault $e){$initial=['error'=>$e->getMessage()];}
  try{$q=salaryQuote($u['tenant'],$p['salary']??null,$p,today());$current=['basicCents'=>$q['cents'],'date'=>today(),'law'=>$q['law']];}
  catch(Fault $e){$current=['error'=>$e->getMessage()];}
  $projection=null;
  foreach($p['processes']??[] as $x)if(in_array($x['state'],['Em análise','Aprovado · efeito futuro'])&&isset($x['salaryTarget'])){
   $effect=$x['effect']??$x['salaryTarget']['forecastDate']??null;
   if(!$effect)continue;
   try{$target=salaryQuote($u['tenant'],$x['salaryTarget'],$p,$effect,$x);$baseline=salaryQuote($u['tenant'],$p['salary']??null,$p,$effect);$projection=['effect'=>$effect,'targetBasicCents'=>$target['cents'],'baselineBasicCents'=>$baseline['cents'],'promotionBasicDeltaCents'=>$target['cents']-$baseline['cents']];}catch(Fault $e){$projection=['error'=>$e->getMessage()];}
   $projection+=['targetLevel'=>$x['targetLevel'],'targetStage'=>$x['targetStage'],'type'=>$x['type'],'state'=>$x['state']];break;
  }
  $roadmap=employeeRoadmap($u['tenant'],$p);$rows[]=['linkId'=>$p['id'],'roadmap'=>$roadmap,'salaryOptions'=>employeeSalaryPaths($u['tenant'],$p,$roadmap),'initial'=>$initial,'current'=>$current,'projection'=>$projection];
 }
 return ['rows'=>$rows,'date'=>today(),'coverage'=>'Somente salário-base do cargo. Não inclui funções gratificadas, horas extras, adicional noturno, insalubridade, outras vantagens ou descontos. Não representa a remuneração do mês.'];
}
function saveInitialCareer(array $u,array $b):array{
 fullManager($u);$p=linkFor($u,$b['linkId']??'');
 if($p['version']!==($b['version']??null))throw new Fault('Cadastro alterado. Atualize antes de salvar.',409);
 $date=day($b['possessionDate']??'');if($date>today()||$date>$p['admission'])throw new Fault('A data da posse não pode ser futura nem posterior à admissão.');
 if(($b['checked']??false)!==true)throw new Fault('Confira cargo, jornada e referência inicial.');
 salaryQuote($u['tenant'],$p['salary']??null,$p,today());$cell=initialCareerCell($u['tenant'],$p);
 $prior=$p['initialCareer']??null;if($prior)$p['initialCareerHistory'][]=$prior;
 $p['initialCareer']=$cell+['possessionDate'=>$date,'planId'=>$p['planId'],'role'=>$p['role'],'workload'=>$p['workload'],'basis'=>need($b['basis']??'','documento / fundamento do ingresso',1000),'actor'=>$u['id'],'createdAt'=>date(DATE_ATOM)];
 event($p,$u,'Referência salarial do ingresso conferida','Data da posse, cargo, jornada e nível 1 documentados.');
 updateLink($u,$p,$p['version'],'salary.initial.confirm');return ['ok'=>true];
}
