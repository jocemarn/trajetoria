<?php
declare(strict_types=1);
require __DIR__.'/test-stages-456.php';
$workflowBefore=$n;
function wfCall(array $who,string $action,array $body=[],string $method='POST'):array {return stageCall($who,'workflow/'.$action,$body,$method);}
$employee=['id'=>'WF-EMP','tenant'=>'T1','role'=>'employee','person'=>'WF-PERSON','unit'=>null,'power'=>null,'is_demo'=>0,'name'=>'Pessoa WF'];
$other=array_replace($employee,['id'=>'WF-OTHER','person'=>'OUTRO']);
$wperson=record('T1','links','LT');$wperson=array_replace($wperson,['id'=>'WF-LINK','registration'=>'WF-001','name'=>'Pessoa WF','personId'=>'WF-PERSON','active'=>true,'deleted'=>false,'processes'=>[],'events'=>[],'enrollmentHistory'=>[]]);unset($wperson['version']);
$wx=['id'=>'WF-PROC','type'=>'Promoção vertical','start'=>'2024-01-01','end'=>'2025-12-31','fromLevel'=>$wperson['level'],'fromStage'=>$wperson['stage'],'targetLevel'=>'III','targetStage'=>'A','state'=>'Em análise','reviewAppointment'=>record('T1','appointments',$a['id']),'opinions'=>[['member'=>'old','decision'=>'Favorável']]];$wperson['processes']=[$wx];save('T1','links',$wperson);
$policy=['planId'=>'P','from'=>'2020-01-01','until'=>'','counting'=>'business','trigger'=>'receipt','holidays'=>['2026-11-23'],'diligenceDays'=>5,'appealDays'=>10,'analysisDays'=>7,'basis'=>'Regulamento fictício QA','confirmed'=>true];
check(workflowDue('2026-11-20',1,['counting'=>'business','holidays'=>['2026-11-23']])==='2026-11-24','Prazo útil exclui fim de semana e feriado');
check(workflowDue('2024-02-28',1,['counting'=>'calendar','holidays'=>[]])==='2024-02-29','Prazo corrido considera ano bissexto');
denied(fn()=>wfCall($employee,'policy',$policy),'Servidor não configura calendário');
wfCall($admin,'policy',$policy);$policySnapshot=workflowPolicy('T1','P',today());
denied(fn()=>wfCall($admin,'policy',$policy),'Vigência de calendário não sobrepõe');
$open=['targetKind'=>'process','target'=>'WF-PROC','linkVersion'=>1,'subject'=>'Conferência QA','request'=>'Esclareça a documentação apresentada.','basis'=>'Fundamento QA'];
denied(fn()=>wfCall($employee,'open',$open),'Servidor não abre diligência contra si');
$cId=wfCall($members['M1'],'open',$open)['id'];$c=record('T1','workflow_cases',$cId);check($c['due']===null&&$c['receipt']===null,'Prazo aguarda ciência expressa');
check(count(workflowBlocking('T1','process','WF-PROC'))===1,'Diligência impede conclusão');
denied(fn()=>wfCall($admin,'open',array_replace($open,['linkVersion'=>2])),'Não duplica diligência ativa');
$employeeContext=wfCall($employee,'context',[],'GET');check(count($employeeContext['cases'])===1&&count($employeeContext['notices'])===1,'Servidor vê tramitação e notificação próprias');
check(!wfCall($other,'context',[],'GET')['cases'],'Outro CPF não acessa a tramitação');
$foreign=array_replace($admin,['tenant'=>'T2']);denied(fn()=>wfCall($foreign,'detail',['id'=>$cId]),'Outro município não acessa a tramitação');
$notice=$employeeContext['notices'][0];wfCall($employee,'read',['id'=>$notice['id']]);check(record('T1','workflow_cases',$cId)['receipt']===null,'Leitura da notificação não registra ciência');
denied(fn()=>wfCall($other,'read',['id'=>$notice['id']]),'Outro CPF não marca notificação');
denied(fn()=>wfCall($employee,'respond',['id'=>$cId,'version'=>$c['version'],'response'=>'Resposta prematura']),'Resposta exige ciência');
denied(fn()=>wfCall($employee,'receipt',['id'=>$cId,'version'=>$c['version'],'confirmed'=>false]),'Ciência exige confirmação expressa');
wfCall($employee,'receipt',['id'=>$cId,'version'=>$c['version'],'confirmed'=>true]);$c=record('T1','workflow_cases',$cId);check($c['due']===workflowDue(today(),5,$policySnapshot),'Ciência inicia prazo da versão correta');
$oldVersion=$c['version'];$privatePath=TR_PRIVATE.'/data';if(!is_dir($privatePath))mkdir($privatePath,0700,true);
try{
$pdf="%PDF-1.7\n1 0 obj <</Type/Catalog>> endobj\n%%EOF";
$file=txn(fn()=>workflowStorePdf($employee,$c,record('T1','links','WF-LINK'),$pdf,'comprovante-qa.pdf'))['id'];$c=record('T1','workflow_cases',$cId);
check(unseal(file_get_contents($privatePath.'/'.$file.'.enc'))===$pdf,'PDF protegido com criptografia');
denied(fn()=>workflowFile($other,$file),'PDF não é visível a outro CPF');
denied(fn()=>txn(fn()=>workflowStorePdf($employee,$c,record('T1','links','WF-LINK'),$pdf,'outro.pdf')),'PDF duplicado na tramitação bloqueado');
denied(fn()=>wfCall($employee,'respond',['id'=>$cId,'version'=>$oldVersion,'response'=>'Resposta QA']),'Anexo altera a versão da conferência');
wfCall($employee,'respond',['id'=>$cId,'version'=>$c['version'],'response'=>'Resposta com comprovação QA']);$c=record('T1','workflow_cases',$cId);check($c['state']==='Aguardando análise'&&$c['due']===workflowDue(today(),7,$policySnapshot),'Resposta inicia prazo da análise');
$p=record('T1','links','WF-LINK');denied(fn()=>stageCall($admin,'links/WF-LINK',['version'=>$p['version'],'operation'=>'decision','data'=>['processId'=>'WF-PROC','checks'=>[true,true,true,true],'decision'=>'Indeferir','reason'=>'QA']],'PUT'),'Não indefere enquanto tramitação pendente');
$newDue=careerDate($c['due'])->modify('+3 days')->format('Y-m-d');wfCall($admin,'extend',['id'=>$cId,'version'=>$c['version'],'due'=>$newDue,'reason'=>'Dispositivo QA de prorrogação']);$c=record('T1','workflow_cases',$cId);check($c['due']===$newDue&&count($c['history'])===2,'Prorrogação preserva prazo anterior e fundamento');
wfCall($admin,'resolve',['id'=>$cId,'version'=>$c['version'],'outcome'=>'Complementação aceita','reason'=>'Comprovação aceita para nova análise.','act'=>'Despacho QA']);$c=record('T1','workflow_cases',$cId);
check($c['state']==='Concluída'&&!workflowBlocking('T1','process','WF-PROC'),'Conclusão libera nova análise');
$p=record('T1','links','WF-LINK');check(!$p['processes'][0]['opinions']&&count($p['processes'][0]['reviewHistory'])===1,'Complementação reinicia pareceres do processo');
denied(fn()=>workflowRequireReads($members['M1'],'process','WF-PROC',true),'Todos os documentos precisam de leitura por integrante');
save('T1','workflow_reads',['id'=>'READ-WF','file'=>$file,'member'=>$members['M1']['id'],'at'=>time()]);workflowRequireReads($members['M1'],'process','WF-PROC',true);check(true,'Leitura registrada libera integrante');
denied(fn()=>workflowRequireReads($members['MS'],'process','WF-PROC',true),'Leitura de um integrante não libera outro');
denied(fn()=>wfCall($admin,'resolve',['id'=>$cId,'version'=>$c['version'],'outcome'=>'Cancelar diligência','reason'=>'QA','act'=>'QA']),'Tramitação concluída não é sobrescrita');
$p=record('T1','links','WF-LINK');stageCall($admin,'links/WF-LINK',['version'=>$p['version'],'operation'=>'decision','data'=>['processId'=>'WF-PROC','checks'=>[true,true,true,true],'decision'=>'Indeferir','reason'=>'Fundamentação fictícia QA']],'PUT');
$d=wfCall($employee,'context',[],'GET')['decisions'][0];check($d['denied']&&$d['reason']==='Fundamentação fictícia QA','Indeferimento gera decisão e fundamento no portal');
denied(fn()=>wfCall($employee,'appeal',['decisionId'=>$d['id'],'version'=>$d['version'],'reason'=>'Razões QA']),'Recurso exige ciência');
wfCall($employee,'decision-receipt',['decisionId'=>$d['id'],'version'=>$d['version'],'confirmed'=>true]);$d=record('T1','workflow_decisions',$d['id']);$d['due']=careerDate(today())->modify('-1 day')->format('Y-m-d');save('T1','workflow_decisions',$d,$d['version']);$d=record('T1','workflow_decisions',$d['id']);
$appeal=wfCall($employee,'appeal',['decisionId'=>$d['id'],'version'=>$d['version'],'reason'=>'Razões do recurso QA']);check(str_starts_with($appeal['timeliness'],'Fora do prazo'),'Recurso tardio é protocolado com admissibilidade pendente');
denied(fn()=>wfCall($employee,'appeal',['decisionId'=>$d['id'],'version'=>$d['version'],'reason'=>'QA']),'Não duplica recurso da mesma decisão');
$ac=record('T1','workflow_cases',$appeal['id']);denied(fn()=>wfCall($members['M1'],'resolve',['id'=>$ac['id'],'version'=>$ac['version'],'outcome'=>'Reabrir análise','reason'=>'QA','act'=>'QA','admissible'=>true,'admissibilityReason'=>'QA']),'Recurso só é decidido pelo gestor municipal');
wfCall($admin,'resolve',['id'=>$ac['id'],'version'=>$ac['version'],'outcome'=>'Reabrir análise','reason'=>'Fundamentos autorizam reanálise QA.','act'=>'Decisão QA','admissible'=>true,'admissibilityReason'=>'Admissão motivada QA']);
$p=record('T1','links','WF-LINK');check($p['processes'][0]['state']==='Em análise'&&$p['level']===$wperson['level']&&$p['stage']===$wperson['stage'],'Recurso aceito reabre sem conceder evolução');
check(count($p['processes'][0]['reviewHistory'])>=1&&count($p['processes'][0]['decisionHistory'])===1,'Decisão anterior preservada no histórico');
check(!wfCall($employee,'context',[],'GET')['decisions'],'Decisão superada sai da lista de recursos disponíveis');

$appointment=record('T1','appointments',$a['id']);$cert=['id'=>'WF-CERT','link'=>'WF-LINK','appointment'=>$appointment['id'],'name'=>'Certificado QA','hash'=>'original-hash','reviewers'=>$appointment['members'],'votes'=>[['member'=>$members['M1']['id'],'decision'=>'Aprovar','reason'=>'QA']],
'category'=>'Capacitação QA','kind'=>'Capacitação','plan'=>'P','hours'=>40,'completed'=>'2025-01-01','createdDay'=>'2025-02-01','targetLevel'=>'','employee'=>'Pessoa WF','registration'=>'WF-001'];save('T1','certificates',$cert);
$p=record('T1','links','WF-LINK');$cc=wfCall($members['M1'],'open',['targetKind'=>'certificate','target'=>'WF-CERT','linkVersion'=>$p['version'],'subject'=>'PDF legível','request'=>'Envie cópia legível.','basis'=>'QA'])['id'];$cc=record('T1','workflow_cases',$cc);wfCall($employee,'receipt',['id'=>$cc['id'],'version'=>$cc['version'],'confirmed'=>true]);$cc=record('T1','workflow_cases',$cc['id']);
$replacement=txn(fn()=>workflowStorePdf($employee,$cc,record('T1','links','WF-LINK'),$pdf.'\nQA2','novo-qa.pdf'))['id'];$cc=record('T1','workflow_cases',$cc['id']);wfCall($employee,'respond',['id'=>$cc['id'],'version'=>$cc['version'],'response'=>'Cópia legível anexada.']);$cc=record('T1','workflow_cases',$cc['id']);
$resolve=['id'=>$cc['id'],'version'=>$cc['version'],'outcome'=>'Complementação aceita','replacementFile'=>$replacement,'reason'=>'Novo PDF aceito para conferência.','act'=>'Despacho QA'];denied(fn()=>wfCall($admin,'resolve',$resolve),'Substituir PDF exige visualizar o documento');
$read=save('T1','workflow_reads',['id'=>'REPLACEMENT-READ','file'=>$replacement,'member'=>$admin['id'],'at'=>time()]);wfCall($admin,'resolve',$resolve+['readId'=>$read['id']]);$newCert=record('T1','certificates','WF-CERT');
check($newCert['documentId']===$replacement&&!$newCert['votes']&&certificateStatus($newCert)==='Em análise','Substituição exige novos pareceres de todos');
check($newCert['hours']===40&&$newCert['completed']==='2025-01-01'&&$newCert['createdDay']==='2025-02-01','Complementação preserva horas e datas originais');
check($newCert['documentHistory'][0]['hash']==='original-hash'&&count($newCert['documentHistory'][0]['votes'])===1,'PDF e parecer anterior preservados');
foreach(['M1','MS'] as $member){save('T1','workflow_reads',['id'=>'WF-READ-'.$member,'file'=>$replacement,'member'=>$members[$member]['id'],'at'=>time()]);$ticket=save('T1','reads',['id'=>'WF-CERT-READ-'.$member,'certificate'=>'WF-CERT','member'=>$members[$member]['id'],'at'=>time()]);stageCall($members[$member],'access/certificates/WF-CERT/vote',['readId'=>$ticket['id'],'verified'=>true,'decision'=>'Aprovar','reason'=>'Nova análise QA']);if($member==='M1')check(certificateStatus(record('T1','certificates','WF-CERT'))==='Em análise','Primeiro parecer não computa crédito sozinho');}
check(certificateStatus(record('T1','certificates','WF-CERT'))==='Aprovado','Aprovação exige ambos os titulares');
$casePolicy=record('T1','workflow_cases',$cId)['policy'];wfCall($admin,'policy-end',['id'=>$policySnapshot['id'],'version'=>$policySnapshot['version'],'until'=>today(),'basis'=>'Encerramento QA']);check(j(record('T1','workflow_cases',$cId)['policy'])===j($casePolicy),'Alterar vigência não altera prazos de casos abertos');
echo 'OK: '.($n-$workflowBefore).' verificações de ciência, prazos, diligências, recursos, notificações e PDFs protegidos.'.PHP_EOL;
}finally{foreach(glob($privatePath.'/*.enc')?:[] as $file)unlink($file);if(is_dir($privatePath))rmdir($privatePath);if(is_dir(TR_PRIVATE))rmdir(TR_PRIVATE);}
