<?php
declare(strict_types=1);
function processFind(array $p,string $id):int {
    $idx=array_search($id,array_column($p['processes'],'id'),true);if($idx===false)throw new Fault('Processo não encontrado.',404);return $idx;
}
function processDossier(array $u,array $p,array $x,string $at,string $effect):array {
    $plan=record($u['tenant'],'plans',$p['planId']);$check=null;$error=null;
    try{$check=careerEvaluate($u['tenant'],$p,$x,$at,null,false,$effect);}catch(Fault $e){$error=$e->getMessage();}
    $opinions=$x['opinions']??[];$review=$x['reviewAppointment']??null;$reviewState='Não solicitado';
    if($review){$expected=array_column($review['members'],'memberId');$actors=array_column($opinions,'member');$reviewState=$expected&&!in_array(null,$expected,true)&&count($actors)===count(array_unique($actors))&&!array_diff($expected,$actors)&&!array_diff($actors,$expected)?'Favorável':'Pendente';foreach($opinions as $opinion)if($opinion['decision']==='Desfavorável')$reviewState='Desfavorável';}
    if($review){$current=record($u['tenant'],'appointments',$review['id']);if(!$current||!$current['active']||($current['purpose']??'evaluation')!=='evaluation'||$current['start']>today()||$current['end']<today()||j($current['members'])!==j($review['members']))$reviewState='Pendente';}
    $out=['linkId'=>$p['id'],'linkVersion'=>$p['version'],'processId'=>$x['id'],'family'=>$plan['family'],'process'=>$x,'at'=>$at,'effect'=>$effect,'check'=>$check,'error'=>$error,'reviewState'=>$reviewState,'opinions'=>$opinions];
    $out['token']=hash_hmac('sha256',j([$u['tenant'],$out]),config()['key']);return $out;
}
function processApi(array $u,string $action,string $method,array $b):array {
    $t=$u['tenant'];
    if($action==='context'&&$method==='GET'){
        if(!in_array($u['role'],['admin','manager','commission']))throw new Fault('Perfil não autorizado.',403);
        $rows=[];foreach(records($t,'links') as $p)if(visibleLink($u,$p))foreach($p['processes']??[] as $x){if($u['role']==='commission'){ $a=$x['reviewAppointment']??null;if(!$a||!commissionSnapshotContains($u,$a['members'])||!reviewerAllowed($u,$a,$p))continue;}
            if($u['role']==='commission')unset($x['salaryTarget'],$x['careerCheck'],$x['reviewHistory'],$x['dossierHash'],$x['decisionHistory']);
            $rows[]=['linkId'=>$p['id'],'registration'=>$p['registration'],'name'=>$p['name'],'role'=>$p['role'],'planId'=>$p['planId'],'process'=>$x,'version'=>$p['version']];}
        return ['rows'=>$rows];
    }
    if($method!=='POST')throw new Fault('Método não permitido.',405);
    $p=linkFor($u,$b['linkId']??'');$idx=processFind($p,$b['processId']??'');$x=$p['processes'][$idx];
    if($action==='opinion'){workflowAssertClear($t,'process',$x['id']);workflowRequireReads($u,'process',$x['id'],($b['verified']??false)===true);
        $a=$x['reviewAppointment']??null;$current=$a?record($t,'appointments',$a['id']):null;
        if($x['state']!=='Em análise'||!$a||!$current||!reviewerAllowed($u,$current,$p)||!commissionSnapshotContains($u,$a['members'])||j($current['members'])!==j($a['members']))throw new Fault('Sem designação vigente para este parecer.',403);
        foreach($x['opinions']??[] as $opinion)if($opinion['member']===$u['id'])throw new Fault('Parecer já registrado.',409);
        $decision=$b['decision']??'';if(!in_array($decision,['Favorável','Desfavorável']))throw new Fault('Parecer inválido.');
        $p['processes'][$idx]['opinions'][]=['member'=>$u['id'],'name'=>$u['name'],'decision'=>$decision,'reason'=>need($b['reason']??'','parecer',2000),'at'=>date(DATE_ATOM)];
        updateLink($u,$p,(int)($b['version']??0),'process.opinion');return ['ok'=>true];
    }
    fullManager($u);
    if($action==='assign-review'){
        if($x['state']!=='Em análise')throw new Fault('Processo encerrado.');$a=record($t,'appointments',$b['appointmentId']??'');
        if(!$a||($a['purpose']??'evaluation')!=='evaluation'||!$a['active']||$a['start']>today()||$a['end']<today()||$a['plan']!==$p['planId']||$a['unit']!==$p['unitId']||($a['lotation']&&$a['lotation']!==$p['lotation']))throw new Fault('Comissão fora do escopo ou do mandato.');
        foreach($a['members'] as $m)if(!$m['memberId']||$m['personId']===$p['personId']||$m['admission']>=$p['admission'])throw new Fault('Confira contas de acesso, antiguidade e impedimentos de todos os titulares.');
        $reason=need($b['reason']??'','fundamento da designação',1000);$x['reviewHistory'][]=['appointment'=>$x['reviewAppointment']??null,'opinions'=>$x['opinions']??[],'reason'=>$reason,'actor'=>$u['id'],'at'=>date(DATE_ATOM)];$x['reviewAppointment']=$a;$x['opinions']=[];$p['processes'][$idx]=$x;updateLink($u,$p,(int)($b['version']??0),'process.review.assign');return ['ok'=>true];
    }
    $at=day($b['at']??$x['end']);$effect=day($b['effect']??today());
    if($at>$x['end'])throw new Fault('A apuração não pode ultrapassar o fechamento do processo.');
    $dossier=processDossier($u,$p,$x,$at,$effect);
    if($action==='dossier')return $dossier;
    if($action==='decide'){workflowAssertClear($t,'process',$x['id']);
        if($x['state']!=='Em análise'||$p['version']!==($b['version']??null)||($b['confirmed']??false)!==true||!hash_equals($dossier['token'],(string)($b['token']??'')))throw new Fault('Processo alterado. Refaça e confirme a conferência do dossiê atual.',409);
        if($dossier['family']==='geral')throw new Fault('Use a classificação e a portaria anual do Quadro Geral.');
        $decision=$b['decision']??'';if(!in_array($decision,['Aprovar','Indeferir'],true))throw new Fault('Decisão inválida.');$reason=need($b['reason']??'','parecer fundamentado',2000);
        if($decision==='Aprovar'){
            if($dossier['error']||($dossier['check']['outcome']??'')!=='Habilitado para análise'||in_array($dossier['reviewState'],['Pendente','Desfavorável'],true))throw new Fault('Há requisitos ou pareceres pendentes/desfavoráveis.');
            $published=day($b['published']??'');if($published>today()||$published>$effect||$effect<$x['end']||[$p['level'],$p['stage']]!==[$x['fromLevel'],$x['fromStage']])throw new Fault('Confira publicação, efeito e enquadramento de origem.');
            $c=record($t,'rules',$dossier['check']['ruleId'])['config'];if($c['calendar']==='annual'&&$published>substr($at,0,4).'-'.$c['publication'])throw new Fault('Publicação posterior ao prazo previsto na regra.');
            $x['act']=need($b['act']??'','ato de concessão',1000);$x['published']=$published;$x['careerCheck']=$dossier['check'];$x['effect']=$effect;$future=$effect>today();$x['state']=$future?'Aprovado · efeito futuro':'Aprovado';
            if(!$future){careerRememberEnrollment($p,$effect,$u);$p['level']=$x['targetLevel'];$p['stage']=$x['targetStage'];$p['enrollmentDate']=$effect;$p['enrollmentAct']=$x['act'];$p['salary']=array_replace($x['salaryTarget'],['context'=>salaryContextKey($p),'target'=>null]);}
            $p['status']=$future?'Concessão futura':'Regular';
        }else{$x['state']='Indeferido';$p['status']='Regular';}
        $x['reason']=$reason;$x['decidedBy']=$u['id'];$x['decidedAt']=date(DATE_ATOM);$x['dossierHash']=hash('sha256',j($dossier));$p['processes'][$idx]=$x;
        event($p,$u,'Processo de evolução concluído',$decision.' · '.$reason);updateLink($u,$p,$p['version'],'process.decision');workflowDecision($u,$p,'process',$x);return ['state'=>$x['state']];
    }
    throw new Fault('Operação não encontrada.',404);
}
