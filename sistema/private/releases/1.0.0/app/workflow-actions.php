<?php
declare(strict_types=1);
function workflowAction(array $u,string $action,array $b):array {
    $t=$u['tenant'];
    if($action==='open'){
        [$p,$x]=workflowTarget($t,$b['targetKind']??'',$b['target']??'');$kind=$b['targetKind'];
        if(!workflowReviewer($u,$kind,$p,$x))throw new Fault('Sem atribuição para solicitar diligência.',403);
        $state=$kind==='certificate'?certificateStatus($x):$x['state'];if($state!=='Em análise')throw new Fault('Diligência exige análise em curso. Para decisão desfavorável, use recurso.');
        workflowAssertClear($t,$kind,$x['id']);$policy=workflowPolicy($t,$p['planId'],today());if(!$policy)throw new Fault('Configure e valide os prazos deste plano antes da diligência.');
        if($p['version']!==($b['linkVersion']??null))throw new Fault('Matrícula alterada. Reabra a conferência.',409);
        $c=save($t,'workflow_cases',['id'=>uid(),'link'=>$p['id'],'person'=>$p['personId'],'targetKind'=>$kind,'target'=>$x['id'],'kind'=>'diligence','state'=>'Aguardando resposta',
            'subject'=>need($b['subject']??'','assunto',180),'request'=>need($b['request']??'','correção solicitada',4000),'basis'=>need($b['basis']??'','fundamento',2000),'policy'=>$policy,
            'created'=>date(DATE_ATOM),'issued'=>today(),'receipt'=>null,'due'=>$policy['trigger']==='issued'?workflowDue(today(),$policy['diligenceDays'],$policy):null,
            'actor'=>$u['id'],'round'=>1,'files'=>[],'responses'=>[],'history'=>[]]);
        workflowTouch($u,$p,'Diligência aberta');workflowNotice($t,$p,'Diligência: '.$c['subject'],'person',$kind,$x['id'],$c['id']);audit($t,'workflow.diligence.open',$c['id']);return ['id'=>$c['id']];
    }
    if($action==='decision-receipt'){
        $d=record($t,'workflow_decisions',$b['decisionId']??'');if(!$d)throw new Fault('Decisão não encontrada.',404);[$p,$x]=workflowTarget($t,$d['targetKind'],$d['target']);
        if(!workflowOwn($u,$p)||!workflowCurrentDecision($t,$d,$x))throw new Fault('Decisão indisponível neste acesso.',404);
        if($d['version']!==($b['version']??null)||$d['receipt'])throw new Fault('Ciência já registrada ou decisão alterada.',409);
        $policy=$d['policy']??workflowPolicy($t,$p['planId'],$d['issued']);if(!$policy)throw new Fault('O gestor precisa configurar os prazos deste plano antes da ciência.');
        if(($b['confirmed']??false)!==true)throw new Fault('Confirme a ciência da decisão.');
        $d['policy']=$policy;$d['receipt']=['actor'=>$u['id'],'at'=>date(DATE_ATOM),'day'=>today()];$d['due']=workflowDue($policy['trigger']==='receipt'?today():$d['issued'],$policy['appealDays'],$policy);
        save($t,'workflow_decisions',$d,$d['version']);audit($t,'workflow.decision.receipt',$d['id']);return ['due'=>$d['due']];
    }
    if($action==='appeal'){
        $d=record($t,'workflow_decisions',$b['decisionId']??'');if(!$d)throw new Fault('Decisão não encontrada.',404);[$p,$x]=workflowTarget($t,$d['targetKind'],$d['target']);
        if(!workflowOwn($u,$p)||!workflowCurrentDecision($t,$d,$x))throw new Fault('Recurso restrito à decisão da própria matrícula.',403);
        if(!$d['receipt']||!$d['policy']||!$d['due'])throw new Fault('Registre a ciência para conferir o prazo do recurso.');
        if($d['version']!==($b['version']??null))throw new Fault('Decisão alterada.',409);
        foreach(records($t,'workflow_cases') as $case)if(($case['decision']??'')===$d['id'])throw new Fault('Já há recurso registrado para esta decisão.',409);
        workflowAssertClear($t,$d['targetKind'],$d['target']);$policy=$d['policy'];
        $c=save($t,'workflow_cases',['id'=>uid(),'link'=>$p['id'],'person'=>$p['personId'],'targetKind'=>$d['targetKind'],'target'=>$d['target'],'kind'=>'appeal','decision'=>$d['id'],
            'state'=>'Aguardando análise','subject'=>'Recurso da decisão','request'=>need($b['reason']??'','razões do recurso',6000),'basis'=>$policy['basis'],'policy'=>$policy,'created'=>date(DATE_ATOM),'issued'=>today(),
            'receipt'=>$d['receipt'],'due'=>workflowDue(today(),$policy['analysisDays'],$policy),'appealDue'=>$d['due'],'timeliness'=>today()<=$d['due']?'Dentro do prazo':'Fora do prazo — admissibilidade a decidir',
            'actor'=>$u['id'],'round'=>1,'files'=>[],'responses'=>[],'history'=>[]]);
        workflowTouch($u,$p,'Recurso protocolado');workflowNotice($t,$p,'Recurso aguardando análise','reviewers',$d['targetKind'],$d['target'],$c['id']);audit($t,'workflow.appeal.open',$c['id']);return ['id'=>$c['id'],'timeliness'=>$c['timeliness']];
    }
    $c=record($t,'workflow_cases',$b['id']??'');if(!$c)throw new Fault('Tramitação não encontrada.',404);[$p,$x]=workflowAccess($u,$c);
    if($action==='detail')return ['case'=>workflowCasePublic($u,$c)];
    if($c['version']!==($b['version']??null))throw new Fault('Tramitação alterada. Atualize antes de continuar.',409);
    if(in_array($c['state'],['Concluída','Cancelada'],true))throw new Fault('Tramitação encerrada. O histórico foi preservado.',409);
    if($action==='receipt'){if(($b['confirmed']??false)!==true||$c['receipt'])throw new Fault('Confirme a ciência uma única vez.');
        if($c['kind']!=='diligence'||!workflowOwn($u,$p)||$c['receipt'])throw new Fault('Ciência indisponível.',403);
        if(($b['confirmed']??false)!==true)throw new Fault('Confirme a ciência da diligência.');
        $c['receipt']=['actor'=>$u['id'],'at'=>date(DATE_ATOM),'day'=>today()];if(!$c['due'])$c['due']=workflowDue(today(),$c['policy']['diligenceDays'],$c['policy']);
        $c=save($t,'workflow_cases',$c,$c['version']);audit($t,'workflow.diligence.receipt',$c['id']);return ['due'=>$c['due']];
    }
    if($action==='respond'){
        if($c['kind']!=='diligence'||$c['state']!=='Aguardando resposta'||!workflowOwn($u,$p)||!$c['receipt'])throw new Fault('Registre ciência e confira a diligência antes da resposta.',403);
        $c['responses'][]=['actor'=>$u['id'],'text'=>need($b['response']??'','resposta',6000),'at'=>date(DATE_ATOM),'round'=>$c['round'],'files'=>$c['files'],'late'=>!empty($c['due'])&&today()>$c['due']];
        $c['history'][]=['action'=>'Resposta protocolada','due'=>$c['due'],'actor'=>$u['id'],'at'=>date(DATE_ATOM)];$c['state']='Aguardando análise';$c['due']=workflowDue(today(),$c['policy']['analysisDays'],$c['policy']);
        save($t,'workflow_cases',$c,$c['version']);workflowTouch($u,$p,'Resposta à diligência protocolada');workflowNotice($t,$p,'Resposta à diligência recebida','reviewers',$c['targetKind'],$c['target'],$c['id']);audit($t,'workflow.diligence.respond',$c['id']);return ['ok'=>true];
    }
    workflowAccess($u,$c,true);
    if($action==='extend'){
        fullManager($u);$due=day($b['due']??'');if(!$c['due']||$due<=$c['due']||$due<today())throw new Fault('A prorrogação deve ampliar um prazo já iniciado.');
        $c['history'][]=['action'=>'Prorrogação','oldDue'=>$c['due'],'newDue'=>$due,'reason'=>need($b['reason']??'','justificativa e dispositivo autorizador',2000),'actor'=>$u['id'],'at'=>date(DATE_ATOM)];$c['due']=$due;save($t,'workflow_cases',$c,$c['version']);workflowTouch($u,$p,'Prazo prorrogado');workflowNotice($t,$p,'Prazo da tramitação prorrogado','person',$c['targetKind'],$c['target'],$c['id']);audit($t,'workflow.case.extend',$c['id']);return ['ok'=>true];
    }
    if($action==='resolve'){
        $outcome=$b['outcome']??'';$reason=need($b['reason']??'','decisão fundamentada',4000);$act=need($b['act']??'','ato ou referência da decisão',1000);
        if($c['kind']==='appeal'){
            fullManager($u);if(!in_array($outcome,['Manter decisão','Reabrir análise'],true))throw new Fault('Resultado do recurso inválido.');
            if(($b['admissible']??null)!==true&&($b['admissible']??null)!==false)throw new Fault('Informe a admissibilidade do recurso.');
            $c['admissible']=$b['admissible'];$c['admissibilityReason']=need($b['admissibilityReason']??'','fundamento da admissibilidade',2000);
            if(!$c['admissible']&&$outcome==='Reabrir análise')throw new Fault('Recurso inadmitido não pode reabrir a análise.');
            if($outcome==='Reabrir análise'){workflowReopen($u,$c,$p,$x,$reason);if(($b['replacementFile']??''))workflowReplaceCertificate($u,$c,$p,record($t,'certificates',$x['id']),$b['replacementFile'],(string)($b['readId']??''));}
        }else{
            if(!in_array($outcome,['Complementação aceita','Nova diligência','Cancelar diligência'],true))throw new Fault('Resultado da diligência inválido.');
            if($outcome==='Complementação aceita'&&$c['state']!=='Aguardando análise')throw new Fault('Aguarde a resposta do servidor.');
            if($outcome==='Complementação aceita'&&$c['targetKind']==='certificate'&&!($b['replacementFile']??'')){workflowResetCertificateVotes($u,$c,$p,$x,$reason);}
            if($outcome==='Complementação aceita'&&($b['replacementFile']??''))workflowReplaceCertificate($u,$c,$p,$x,$b['replacementFile'],(string)($b['readId']??''));
        }
        if($c['kind']==='diligence'&&$c['targetKind']==='process'&&$outcome==='Complementação aceita'){
            $idx=processFind($p,$x['id']);$x['reviewHistory'][]=['opinions'=>$x['opinions']??[],'appointment'=>$x['reviewAppointment']??null,'case'=>$c['id'],'reason'=>$reason,'at'=>date(DATE_ATOM)];$x['opinions']=[];$p['processes'][$idx]=$x;updateLink($u,$p,$p['version'],'workflow.process.new-review');
        }
        $c['history'][]=['action'=>$outcome,'request'=>$c['request'],'round'=>$c['round'],'receipt'=>$c['receipt'],'due'=>$c['due'],'reason'=>$reason,'act'=>$act,'actor'=>$u['id'],'at'=>date(DATE_ATOM)];
        if($outcome==='Nova diligência'){$c['round']++;$c['request']=need($b['request']??'','nova correção solicitada',4000);$c['issued']=today();$c['receipt']=null;$c['state']='Aguardando resposta';$c['due']=$c['policy']['trigger']==='issued'?workflowDue(today(),$c['policy']['diligenceDays'],$c['policy']):null;}
        else{$c['state']=$outcome==='Cancelar diligência'?'Cancelada':'Concluída';$c['outcome']=$outcome;$c['resolvedBy']=$u['id'];$c['resolvedAt']=date(DATE_ATOM);}
        save($t,'workflow_cases',$c,$c['version']);$fresh=record($t,'links',$p['id']);workflowTouch($u,$fresh,'Tramitação: '.$outcome);workflowNotice($t,$p,$outcome,'person',$c['targetKind'],$c['target'],$c['id']);audit($t,'workflow.case.resolve',$c['id']);return ['state'=>$c['state']];
    }
    throw new Fault('Operação não encontrada.',404);
}
function workflowDecision(array $u,array $p,string $kind,array $x):void {
    $state=$kind==='certificate'?certificateStatus($x):$x['state'];$denied=in_array($state,['Rejeitado','Indeferido'],true);
    if(!$denied){if($state==='Aprovado'||$state==='Aprovado · efeito futuro')workflowNotice($u['tenant'],$p,'Decisão registrada: '.$state,'person',$kind,$x['id']);return;}
    $fingerprint=workflowDecisionFingerprint($kind,$x);$id=hash('sha256',j([$kind,$x['id'],$fingerprint]));if(record($u['tenant'],'workflow_decisions',$id))return;
    $policy=workflowPolicy($u['tenant'],$p['planId'],today());$d=save($u['tenant'],'workflow_decisions',['id'=>$id,'link'=>$p['id'],'targetKind'=>$kind,'target'=>$x['id'],'fingerprint'=>$fingerprint,'denied'=>true,'state'=>$state,'reason'=>$kind==='certificate'?($x['votes'][array_key_last($x['votes'])]['reason']??''):($x['reason']??''),'issued'=>today(),'created'=>date(DATE_ATOM),'receipt'=>null,'policy'=>$policy,'due'=>$policy&&$policy['trigger']==='issued'?workflowDue(today(),$policy['appealDays'],$policy):null,'snapshot'=>$x]);
    workflowNotice($u['tenant'],$p,'Decisão desfavorável: confira a ciência e o prazo de recurso','person',$kind,$x['id'],null,$d['id']);
}
function workflowDecisionFingerprint(string $kind,array $x):string {
    return hash('sha256',j($kind==='certificate'?[$x['id'],$x['votes'],$x['hash'],$x['documentId']??null,$x['reviewRound']??1]:[$x['id'],$x['state'],$x['reason']??null,$x['decidedAt']??null,count($x['decisionHistory']??[])]));
}
function workflowCurrentDecision(string $t,array $d,array $x):bool {return hash_equals($d['fingerprint'],workflowDecisionFingerprint($d['targetKind'],$x))&&($d['targetKind']==='certificate'?certificateStatus($x)==='Rejeitado':$x['state']==='Indeferido');}
function workflowReopen(array $u,array $c,array $p,array $x,string $reason):void {
    $d=record($u['tenant'],'workflow_decisions',$c['decision']);if(!$d||!workflowCurrentDecision($u['tenant'],$d,$x))throw new Fault('A decisão original foi alterada. Confira o histórico.');
    if($c['targetKind']==='process'){foreach($p['processes'] as $other)if($other['id']!==$x['id']&&in_array($other['state'],['Em análise','Aprovado · efeito futuro'],true))throw new Fault('Há outro processo em andamento nesta matrícula. Regularize antes da reabertura.');
        if([$p['level'],$p['stage']]!==[$x['fromLevel'],$x['fromStage']])throw new Fault('Enquadramento alterado. A revisão exige conferência funcional.');
        $idx=processFind($p,$x['id']);$previous=$x;unset($previous['decisionHistory']);$x['decisionHistory'][]=['previous'=>$previous,'case'=>$c['id'],'actor'=>$u['id'],'reason'=>$reason,'at'=>date(DATE_ATOM)];$x['state']='Em análise';$x['opinions']=[];unset($x['decidedAt'],$x['decidedBy'],$x['reason'],$x['careerCheck'],$x['dossierHash']);$p['processes'][$idx]=$x;$p['status']='Em análise';updateLink($u,$p,$p['version'],'workflow.process.reopen');
    }else{
        $a=record($u['tenant'],'appointments',$x['appointment']);if(!$a||!$a['active']||($a['purpose']??'evaluation')!=='evaluation'||$a['start']>today()||$a['end']<today()||$a['unit']!==$p['unitId']||$a['plan']!==$p['planId']||($a['lotation']&&$a['lotation']!==$p['lotation']))throw new Fault('Regularize a comissão avaliadora antes de reabrir o certificado.');
        foreach($a['members'] as $m)if(!$m['memberId']||$m['personId']===$p['personId']||$m['admission']>=$p['admission'])throw new Fault('Confira os integrantes e seus impedimentos.');
        $x['reviewHistory'][]=['reviewers'=>$x['reviewers'],'votes'=>$x['votes'],'case'=>$c['id'],'reason'=>$reason,'actor'=>$u['id'],'at'=>date(DATE_ATOM)];$x['reviewers']=$a['members'];$x['votes']=[];$x['reviewRound']=($x['reviewRound']??1)+1;save($u['tenant'],'certificates',$x,$x['version']);
    }
}
