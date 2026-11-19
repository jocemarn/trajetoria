<?php
declare(strict_types=1);

function assessmentModelAt(string $tenant,string $plan,string $at): ?array {
    $found=[];
    foreach(records($tenant,'assessment_models') as $model) if($model['plan']===$plan && $model['status']==='Ativa' && $model['from']<=$at && (!$model['until']||$model['until']>=$at)&&(!isset($model['endedOn'])||$model['endedOn']>=$at)) {$model['version']=$model['formVersion']??$model['version'];unset($model['formVersion'],$model['endedOn'],$model['closedBy'],$model['closeBasis']);$found[]=$model;}
    if(count($found)>1) throw new Fault('Há formulários sobrepostos. Revise as vigências.');
    return $found[0]??null;
}
function assessmentModelDraft(array $u,array $b): array {
    fullManager($u);$t=$u['tenant'];$plan=record($t,'plans',$b['planId']??'');if(!$plan)throw new Fault('Plano não encontrado.');
    $factors=[];$ids=[];
    foreach($b['factors']??[] as $f){
        $id=need($f['id']??'','identificador do fator',40);if(!preg_match('/^[a-zA-Z0-9_-]+$/',$id)||in_array($id,$ids,true))throw new Fault('Identificadores dos fatores devem ser distintos.');$ids[]=$id;
        $min=num($f['min']??'',0,100,'nota mínima');$max=num($f['max']??'',0,100,'nota máxima');if($max<=$min)throw new Fault('Nota máxima deve superar a mínima.');
        $factors[]=['id'=>$id,'name'=>need($f['name']??'','fator',160),'weight'=>num($f['weight']??'',0.01,100,'peso'),'min'=>$min,'max'=>$max,'passing'=>num($f['passing']??$min,$min,$max,'nota mínima por fator')];
    }
    if(!$factors||count($factors)>30)throw new Fault('Informe de 1 a 30 fatores.');
    $from=day($b['from']??'');$until=empty($b['until'])?null:day($b['until']);if($until&&$until<$from)throw new Fault('Vigência inválida.');
    $months=careerInteger($b['periodMonths']??'',1,60,'periodicidade em meses');$aggregation=$b['aggregation']??'';if(!in_array($aggregation,['mean','minimum'],true))throw new Fault('Consolidação inválida.');
    $r=save($t,'assessment_models',['id'=>uid(),'plan'=>$plan['id'],'name'=>need($b['name']??'','formulário',160),'basis'=>need($b['basis']??'','fundamento',1000),
        'from'=>$from,'until'=>$until,'periodMonths'=>$months,'aggregation'=>$aggregation,'passing'=>num($b['passing']??'',0,100,'nota mínima consolidada'),
        'factors'=>$factors,'status'=>'Rascunho','actor'=>$u['id'],'created'=>date(DATE_ATOM)]);
    audit($t,'assessment.model.draft',$r['id']);return ['id'=>$r['id']];
}
function assessmentActivate(array $u,array $b):array {
    fullManager($u);$t=$u['tenant'];$m=record($t,'assessment_models',$b['id']??'');
    if(!$m||$m['status']!=='Rascunho'||$m['version']!==($b['version']??null))throw new Fault('Rascunho alterado ou indisponível.',409);
    if(($b['confirmed']??false)!==true)throw new Fault('Confirme a revisão do formulário e da legislação.');
    foreach(records($t,'assessment_models') as $other)if($other['plan']===$m['plan']&&$other['status']==='Ativa'&&$other['from']<=($m['until']??'9999-12-31')&&$m['from']<=min($other['until']??'9999-12-31',$other['endedOn']??'9999-12-31'))throw new Fault('Outra versão ativa cobre esta vigência. Copie o formulário com datas sem sobreposição.');
    $m['status']='Ativa';$m['activatedBy']=$u['id'];$m['activatedAt']=date(DATE_ATOM);save($t,'assessment_models',$m,$m['version']);audit($t,'assessment.model.activate',$m['id']);return ['ok'=>true];
}
function assessmentSubmit(array $u,array $b):array {
    if($u['role']!=='commission')throw new Fault('Preenchimento restrito ao integrante designado.',403);
    $t=$u['tenant'];$p=linkFor($u,$b['linkId']??'');$a=record($t,'appointments',$b['appointmentId']??'');
    if(!$a||!reviewerAllowed($u,$a,$p)||($a['purpose']??'evaluation')!=='evaluation')throw new Fault('Comissão avaliadora indisponível.',403);
    $start=day($b['start']??'');$end=day($b['end']??'');if($end<$start||$start<$p['admission']||$end>today())throw new Fault('Período de avaliação inválido.');
    $model=assessmentModelAt($t,$p['planId'],$start);if(!$model||$model['id']!==($b['modelId']??''))throw new Fault('Selecione o formulário vigente no início do período.');
    if($end!==careerDate(careerMonthsAfter($start,$model['periodMonths']))->modify('-1 day')->format('Y-m-d'))throw new Fault('O período deve conter exatamente '.$model['periodMonths'].' meses.');
    $scores=$b['scores']??[];$evidence=$b['evidence']??[];if(array_diff(array_keys($scores),array_column($model['factors'],'id'))||count($scores)!==count($model['factors'])||array_diff(array_keys($evidence),array_keys($scores))||count($evidence)!==count($scores))throw new Fault('Preencha todos os fatores desta versão.');
    $total=0;$weights=0;$passed=true;
    foreach($model['factors'] as $f){$value=num($scores[$f['id']]??'',$f['min'],$f['max'],'nota de '.$f['name']);need($evidence[$f['id']]??'','evidência',800);$total+=(($value-$f['min'])/($f['max']-$f['min'])*100)*$f['weight'];$weights+=$f['weight'];if($value<$f['passing'])$passed=false;}
    foreach($p['evaluations'] as $e)if(($e['start']??'')===$start&&($e['end']??'')===$end){
        if(($e['actor']??'')===$u['id'])throw new Fault('Parecer já registrado neste período.',409);
        if(($e['model']['id']??'')!==$model['id']||($e['model']['version']??0)!==$model['version']||j($e['appointment']['members'])!==j($a['members']))throw new Fault('O período já iniciado usa outra composição ou versão. Preserve a consolidação original.',409);
    }
    $score=round($total/$weights,4);$e=['id'=>uid(),'period'=>$start.' a '.$end,'start'=>$start,'end'=>$end,'date'=>today(),'scores'=>$scores,'evidence'=>$evidence,'score'=>$score,'factorPassed'=>$passed,'conclusion'=>need($b['conclusion']??'','parecer',2000),'actor'=>$u['id'],'evaluator'=>$u['name'],'appointment'=>$a,'model'=>$model];
    array_unshift($p['evaluations'],$e);event($p,$u,'Avaliação de desempenho registrada',$model['name']);updateLink($u,$p,(int)($b['version']??0),'assessment.complete');return ['score'=>$score,'consolidation'=>assessmentConsolidate(array_values(array_filter($p['evaluations'],fn($r)=>($r['start']??'')===$start&&($r['end']??'')===$end)))];
}
function assessmentConsolidate(array $evaluations):array {
    if(!$evaluations)return ['state'=>'Pendente','score'=>null];$first=$evaluations[0];$expected=array_column($first['appointment']['members'],'memberId');$actors=array_column($evaluations,'actor');
    if(!$expected||in_array(null,$expected,true)||count($actors)!==count(array_unique($actors))||array_diff($expected,$actors)||array_diff($actors,$expected))return ['state'=>'Pendente','score'=>null];
    foreach($evaluations as $e)if(j($e['appointment']['members'])!==j($first['appointment']['members'])||j($e['model']??null)!==j($first['model']??null))return ['state'=>'Pendente','score'=>null];
    $model=$first['model']??null;$values=array_column($evaluations,'score');$score=($model['aggregation']??'mean')==='mean'?array_sum($values)/count($values):min($values);
    $pass=$score>=($model['passing']??0);foreach($evaluations as $e)if(($e['factorPassed']??true)!==true)$pass=false;
    return ['state'=>$pass?'Atende':'Não atende','score'=>round($score,4),'members'=>count($expected),'model'=>$model['id']??null];
}
function assessmentApi(array $u,string $action,string $method,array $b):array {
    $t=$u['tenant'];if($action==='context'&&$method==='GET'){
        if(!in_array($u['role'],['admin','manager','commission']))throw new Fault('Perfil não autorizado.',403);
        $plans=array_filter(records($t,'plans'),fn($p)=>!$u['power']||$p['power']===$u['power']);
        if($u['role']==='commission'){$ids=[];foreach(records($t,'appointments') as $a)if(($a['purpose']??'evaluation')==='evaluation')foreach($a['members'] as $m)if(commissionMemberMatches($u,$m))$ids[]=$a['plan'];$plans=array_filter($plans,fn($p)=>in_array($p['id'],$ids,true));}
        return ['models'=>array_values(array_filter(records($t,'assessment_models'),fn($m)=>in_array($m['plan'],array_column($plans,'id'),true)))];
    }
    if($method!=='POST')throw new Fault('Método não permitido.',405);
    if($action==='end'){
        fullManager($u);$model=record($t,'assessment_models',$b['id']??'');if(!$model||$model['status']!=='Ativa'||$model['version']!==($b['version']??null))throw new Fault('Formulário alterado.',409);
        $end=day($b['endedOn']??'');if($end<today()||$end<$model['from']||isset($model['endedOn']))throw new Fault('Encerre a vigência a partir de hoje, uma única vez.');
        $model['formVersion']=$model['version'];$model['endedOn']=$end;$model['closedBy']=$u['id'];$model['closeBasis']=need($b['basis']??'','fundamento do encerramento',1000);save($t,'assessment_models',$model,$model['version']);audit($t,'assessment.model.end',$model['id']);return ['ok'=>true];
    }
    if($action==='draft')return assessmentModelDraft($u,$b);if($action==='activate')return assessmentActivate($u,$b);if($action==='submit')return assessmentSubmit($u,$b);throw new Fault('Operação não encontrada.',404);
}
