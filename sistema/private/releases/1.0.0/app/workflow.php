<?php
declare(strict_types=1);
require_once __DIR__.'/workflow-actions.php';
require_once __DIR__.'/workflow-documents.php';

function workflowDue(string $start,int $days,array $policy):string {
    $date=careerDate($start);$n=0;
    while($n<$days){$date=$date->modify('+1 day');if($policy['counting']==='business'&&((int)$date->format('N')>5||in_array($date->format('Y-m-d'),$policy['holidays'],true)))continue;$n++;}
    return $date->format('Y-m-d');
}
function workflowPolicy(string $tenant,string $plan,string $at):?array {
    $found=[];foreach(records($tenant,'workflow_policies') as $p)if($p['plan']===$plan&&$p['from']<=$at&&(!$p['until']||$p['until']>=$at))$found[]=$p;
    if(count($found)>1)throw new Fault('Há calendários de prazos sobrepostos.');return $found[0]??null;
}
function workflowPolicySave(array $u,array $b):array {
    fullManager($u);$t=$u['tenant'];$plan=record($t,'plans',$b['planId']??'');if(!$plan)throw new Fault('Plano não encontrado.');
    if(($b['confirmed']??false)!==true)throw new Fault('Confirme a validação municipal dos prazos.');
    $from=day($b['from']??'');$until=empty($b['until'])?null:day($b['until']);if($until&&$until<$from)throw new Fault('Vigência inválida.');
    $counting=$b['counting']??'';$trigger=$b['trigger']??'';if(!in_array($counting,['calendar','business'],true)||!in_array($trigger,['receipt','issued'],true))throw new Fault('Defina a contagem e o início do prazo.');
    $holidays=$b['holidays']??[];if(!is_array($holidays)||count($holidays)>1000)throw new Fault('Calendário de feriados inválido.');foreach($holidays as $h)day($h);$holidays=array_values(array_unique($holidays));sort($holidays);
    $r=['id'=>uid(),'plan'=>$plan['id'],'from'=>$from,'until'=>$until,'counting'=>$counting,'trigger'=>$trigger,'holidays'=>$holidays,'basis'=>need($b['basis']??'','fundamento legal',2000),'actor'=>$u['id'],'created'=>date(DATE_ATOM)];
    foreach(['diligenceDays','appealDays','analysisDays'] as $key)$r[$key]=careerInteger($b[$key]??'',1,365,$key);
    foreach(records($t,'workflow_policies') as $other)if($other['plan']===$plan['id']&&$other['from']<=($until??'9999-12-31')&&$from<=($other['until']??'9999-12-31'))throw new Fault('Outra versão cobre esta vigência. Encerre-a antes de cadastrar a próxima.');
    $r=save($t,'workflow_policies',$r);audit($t,'workflow.policy.create',$r['id']);return ['id'=>$r['id']];
}
function workflowTarget(string $tenant,string $kind,string $id):array {
    if($kind==='certificate'){$x=record($tenant,'certificates',$id);$p=$x?record($tenant,'links',$x['link']):null;}
    elseif($kind==='process'){$x=null;$p=null;foreach(records($tenant,'links') as $candidate)foreach($candidate['processes']??[] as $process)if($process['id']===$id){$x=$process;$p=$candidate;break 2;}}
    else throw new Fault('Tipo de objeto inválido.');
    if(!$x||!$p)throw new Fault('Registro não encontrado neste município.',404);return [$p,$x];
}
function workflowOwn(array $u,array $p):bool {return in_array($u['role'],['employee','commission'],true)&&$u['person']===$p['personId'];}
function workflowReviewer(array $u,string $kind,array $p,array $x):bool {
    if(in_array($u['role'],['admin','manager'],true))return canScope($u,$p);
    if($u['role']!=='commission'||workflowOwn($u,$p))return false;
    if($kind==='certificate'){$a=record($u['tenant'],'appointments',$x['appointment']);return $a&&reviewerAllowed($u,$a,$p)&&commissionSnapshotContains($u,$x['reviewers']);}
    $snapshot=$x['reviewAppointment']??null;$a=$snapshot?record($u['tenant'],'appointments',$snapshot['id']):null;
    return $a&&reviewerAllowed($u,$a,$p)&&commissionSnapshotContains($u,$snapshot['members'])&&j($a['members'])===j($snapshot['members']);
}
function workflowAccess(array $u,array $case,bool $review=false):array {
    [$p,$x]=workflowTarget($u['tenant'],$case['targetKind'],$case['target']);
    if($case['link']!==$p['id']||!workflowReviewer($u,$case['targetKind'],$p,$x)&&($review||!workflowOwn($u,$p)))throw new Fault('Registro não encontrado neste acesso.',404);
    return [$p,$x];
}
function workflowBlocking(string $tenant,string $kind,string $target):array {
    return array_values(array_filter(records($tenant,'workflow_cases'),fn($c)=>$c['targetKind']===$kind&&$c['target']===$target&&!in_array($c['state'],['Concluída','Cancelada'],true)));
}
function workflowAssertClear(string $tenant,string $kind,string $target):void {
    if(workflowBlocking($tenant,$kind,$target))throw new Fault('Há diligência ou recurso pendente. Conclua a tramitação antes da decisão.',409);
}
function workflowTouch(array $u,array $p,string $title):void {event($p,$u,$title,'Tramitação registrada com ciência, prazos e histórico.');updateLink($u,$p,$p['version'],'workflow.case.change');}
function workflowNotice(string $tenant,array $p,string $title,string $audience,string $kind,string $target,?string $case=null,?string $decision=null):void {
    save($tenant,'workflow_notices',['id'=>uid(),'link'=>$p['id'],'person'=>$p['personId'],'title'=>$title,'audience'=>$audience,'targetKind'=>$kind,'target'=>$target,'case'=>$case,'decision'=>$decision,'created'=>date(DATE_ATOM),'readBy'=>[]]);
}
function workflowCasePublic(array $u,array $c):array {
    [$p,$x]=workflowAccess($u,$c);$c['employee']=$p['name'];$c['registration']=$p['registration'];$c['plan']=$p['planId'];
    $c['own']=workflowOwn($u,$p);$c['canReview']=workflowReviewer($u,$c['targetKind'],$p,$x);$c['canResolve']=$c['kind']==='appeal'?($u['role']==='admin'&&!$u['unit']&&!$u['power']):$c['canReview'];
    $c['overdue']=!in_array($c['state'],['Concluída','Cancelada'],true)&&!empty($c['due'])&&$c['due']<today();
    $c['files']=array_map(fn($f)=>array_intersect_key($f,array_flip(['id','name','created','size','round'])),array_values(array_filter(records($u['tenant'],'workflow_files'),fn($f)=>$f['case']===$c['id'])));
    return $c;
}
function workflowContext(array $u):array {
    $t=$u['tenant'];$cases=[];$notices=[];$decisions=[];$targets=[];
    foreach(records($t,'workflow_cases') as $c){try{$cases[]=workflowCasePublic($u,$c);}catch(Fault){}}
    foreach(records($t,'workflow_notices') as $notice){try{[$p,$x]=workflowTarget($t,$notice['targetKind'],$notice['target']);$allowed=$notice['audience']==='person'?workflowOwn($u,$p):workflowReviewer($u,$notice['targetKind'],$p,$x);if(!$allowed)continue;$notice['read']=in_array($u['id'],$notice['readBy'],true);unset($notice['readBy']);$notices[]=$notice;}catch(Fault){}}
    foreach(records($t,'workflow_decisions') as $d){try{[$p,$x]=workflowTarget($t,$d['targetKind'],$d['target']);if(!workflowOwn($u,$p)||!workflowCurrentDecision($t,$d,$x))continue;unset($d['snapshot']);$d['registration']=$p['registration'];$d['appealed']=(bool)array_filter(records($t,'workflow_cases'),fn($c)=>($c['decision']??'')===$d['id']);$decisions[]=$d;}catch(Fault){}}
    foreach(records($t,'links') as $p)if(visibleLink($u,$p))foreach($p['processes']??[] as $x)if($x['state']==='Em análise'&&workflowReviewer($u,'process',$p,$x))$targets[]=['kind'=>'process','id'=>$x['id'],'linkId'=>$p['id'],'linkVersion'=>$p['version'],'label'=>$p['name'].' · '.$p['registration'].' · '.$x['type']];
    foreach(records($t,'certificates') as $x){$p=record($t,'links',$x['link']);if($p&&certificateStatus($x)==='Em análise'&&workflowReviewer($u,'certificate',$p,$x))$targets[]=['kind'=>'certificate','id'=>$x['id'],'linkId'=>$p['id'],'linkVersion'=>$p['version'],'label'=>$p['name'].' · '.$p['registration'].' · '.$x['name']];}
    usort($cases,fn($a,$b)=>strcmp($b['created'],$a['created']));usort($notices,fn($a,$b)=>strcmp($b['created'],$a['created']));
    $full=$u['role']==='admin'&&!$u['unit']&&!$u['power'];return ['cases'=>$cases,'notices'=>$notices,'decisions'=>$decisions,'targets'=>$targets,'policies'=>$full?records($t,'workflow_policies'):[],'canConfigure'=>$full,'serverDay'=>today(),'notificationChannel'=>'Dentro da plataforma'];
}
function workflowApi(array $u,string $action,string $method,array $b):array {
    if(!in_array($u['role'],['admin','manager','employee','commission'],true))throw new Fault('Perfil não autorizado.',403);
    if($action==='context'&&$method==='GET')return workflowContext($u);
    if($method!=='POST')throw new Fault('Método não permitido.',405);
    if($action==='policy')return workflowPolicySave($u,$b);
    if($action==='policy-end'){
        fullManager($u);$p=record($u['tenant'],'workflow_policies',$b['id']??'');if(!$p||$p['version']!==($b['version']??null)||$p['until'])throw new Fault('Versão alterada ou já encerrada.',409);
        $end=day($b['until']??'');if($end<today()||$end<$p['from'])throw new Fault('Encerre a vigência a partir de hoje.');$p['until']=$end;$p['endBasis']=need($b['basis']??'','fundamento',1000);save($u['tenant'],'workflow_policies',$p,$p['version']);audit($u['tenant'],'workflow.policy.end',$p['id']);return ['ok'=>true];
    }
    if($action==='read'){
        $found=null;foreach(workflowContext($u)['notices'] as $n)if($n['id']===($b['id']??''))$found=$n;if(!$found)throw new Fault('Notificação não encontrada.',404);
        $n=record($u['tenant'],'workflow_notices',$found['id']);if(!in_array($u['id'],$n['readBy'],true)){$n['readBy'][]=$u['id'];save($u['tenant'],'workflow_notices',$n,$n['version']);}return ['ok'=>true];
    }
    if($action==='upload')return workflowUpload($u);
    return workflowAction($u,$action,$b);
}

function workflowRequireReads(array $u,string $kind,string $target,bool $confirmed):void {
    $ids=[];foreach(records($u['tenant'],'workflow_cases') as $case)if($case['targetKind']===$kind&&$case['target']===$target&&$case['state']==='Concluída')$ids=array_merge($ids,$case['files']);
    if(!$ids)return;if(!$confirmed)throw new Fault('Confirme a conferência dos documentos complementares.');
    $read=array_column(array_filter(records($u['tenant'],'workflow_reads'),fn($r)=>$r['member']===$u['id']),'file');if(array_diff($ids,$read))throw new Fault('Visualize todos os PDFs complementares da tramitação antes do parecer.');
}
