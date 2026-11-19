<?php
declare(strict_types=1);
require __DIR__.'/test-career-foundation.php';
$before=$n;$admin=$user;
function stageCall(array $who,string $path,array $body=[],string $method='POST'):array{$_GET['tenant']='T1';return routeApi($who,$path,$method,$body);}
function stageCsv(string $mode,array $rows):string{$f=fopen('php://temp','r+');fputcsv($f,staffImportHeaders($mode),';','"','');foreach($rows as $r)fputcsv($f,array_map(fn($h)=>$r[$h]??'',staffImportHeaders($mode)),';','"','');rewind($f);return stream_get_contents($f);}
save('T1','units',['id'=>'U','name'=>'Unidade QA','power'=>'Executivo']);
$draft=['planId'=>'P','name'=>'Formulário QA','basis'=>'Regulamento fictício QA','from'=>'2024-01-01','until'=>'2025-12-31','periodMonths'=>12,'aggregation'=>'mean','passing'=>70,
'factors'=>[['id'=>'a','name'=>'Qualidade','weight'=>3,'min'=>0,'max'=>10,'passing'=>5],['id'=>'b','name'=>'Pontualidade','weight'=>1,'min'=>0,'max'=>5,'passing'=>2]]];
$model=stageCall($admin,'assessments/draft',$draft);$m=record('T1','assessment_models',$model['id']);
check($m['status']==='Rascunho','Formulário inicia em rascunho');
stageCall($admin,'assessments/activate',['id'=>$m['id'],'version'=>$m['version'],'confirmed'=>true]);$m=record('T1','assessment_models',$m['id']);
$dup=stageCall($admin,'assessments/draft',$draft);denied(fn()=>stageCall($admin,'assessments/activate',['id'=>$dup['id'],'version'=>1,'confirmed'=>true]),'Vigências sobrepostas');
$basePerson=record('T1','links','L');$person=array_replace($basePerson,['id'=>'LT','personId'=>'PT','cpf'=>'52998224725','name'=>'Pessoa Avaliada QA','registration'=>'QA-200','admission'=>'2024-01-01','enrollmentDate'=>'2024-01-01','level'=>'II','stage'=>'A','evaluations'=>[],'processes'=>[],'paidFunctions'=>[],'leaves'=>[],'events'=>[],'enrollmentHistory'=>[]]);unset($person['version']);save('T1','links',$person);
$members=[];foreach(['M1','M2','MS'] as $id){$link=array_replace($person,['id'=>'L'.$id,'personId'=>'P'.$id,'registration'=>$id,'name'=>'Avaliador '.$id,'admission'=>'2000-01-01','cpf'=>'11144477735']);save('T1','links',$link);$members[$id]=['id'=>$id,'tenant'=>'T1','role'=>'commission','unit'=>null,'power'=>null,'person'=>'P'.$id,'is_demo'=>0,'name'=>'Avaliador '.$id];newUser('T1',strtolower($id).'@example.invalid','Avaliador '.$id,'commission','P'.$id,null,null,false,'TestPassword12345A!');$actual=one('SELECT id FROM tr_users WHERE email=?',[strtolower($id).'@example.invalid']);$members[$id]['id']=$actual['id'];}
$appointmentInput=['name'=>'Comissão QA','plan'=>'P','unit'=>'U','lotation'=>'','act'=>'Ato fictício QA','start'=>'2024-01-01','end'=>'2027-12-31','purpose'=>'evaluation',
'members'=>[['linkId'=>'LM1','representation'=>'Administração','indication'=>'Ato QA'],['linkId'=>'LM2','representation'=>'Sindicato','indication'=>'Ata QA']],
'substitutes'=>[['linkId'=>'LMS','representation'=>'Assembleia','indication'=>'Ata QA']]];
$a=stageCall($admin,'access/appointments',$appointmentInput);$appointment=record('T1','appointments',$a['id']);
check(count($appointment['members'])===2&&count($appointment['substitutes'])===1,'Titulares e suplentes separados');
check(!reviewerAllowed($members['MS'],$appointment,$person),'Suplente não vota sem assumir vaga');
$vote=['linkId'=>'LT','appointmentId'=>$a['id'],'modelId'=>$m['id'],'start'=>'2024-01-01','end'=>'2024-12-31','scores'=>['a'=>8,'b'=>4],'evidence'=>['a'=>'Evidência QA A','b'=>'Evidência QA B'],'conclusion'=>'Parecer QA'];
denied(fn()=>stageCall($members['MS'],'assessments/submit',$vote+['version'=>1]),'Suplente bloqueado');
denied(fn()=>stageCall($members['M1'],'assessments/submit',array_replace($vote,['version'=>1,'end'=>'2024-11-30'])),'Periodicidade conferida');
$r=stageCall($members['M1'],'assessments/submit',$vote+['version'=>1]);check($r['score']===80.0&&$r['consolidation']['state']==='Pendente','Nota ponderada e consolidação parcial');
denied(fn()=>stageCall($members['M1'],'assessments/submit',$vote+['version'=>2]),'Voto duplicado');
$r=stageCall($members['M2'],'assessments/submit',$vote+['version'=>2]);check($r['consolidation']['state']==='Atende','Todos os titulares consolidam período');
$pc=['evaluationCount'=>1,'aggregation'=>'mean','minScore'=>75];check(careerPerformance(record('T1','links','LT'),'2024-01-01','2024-12-31',$pc)['state']==='Atende','Motor usa formulário configurado');
$partial=array_replace($vote,['start'=>'2025-01-01','end'=>'2025-12-31','version'=>3]);stageCall($members['M1'],'assessments/submit',$partial);
$change=array_replace($appointmentInput,['id'=>$appointment['id'],'version'=>$appointment['version'],'reason'=>'Substituição QA','members'=>[$appointmentInput['members'][0],$appointmentInput['substitutes'][0]],'substitutes'=>[$appointmentInput['members'][1]]]);
stageCall($admin,'access/appointments',$change,'PUT');$changed=record('T1','links','LT');
check(count($changed['evaluationHistory'][0]['evaluations'])===1&&count($changed['evaluations'])===2,'Substituição preserva completo e arquiva parcial');
check(!reviewerAllowed($members['M2'],record('T1','appointments',$a['id']),$changed),'Titular substituído perde acesso');
$mgmt=array_replace($appointmentInput,['name'=>'Gestão do plano QA','purpose'=>'management']);$management=stageCall($admin,'access/appointments',$mgmt);check(!reviewerAllowed($members['M1'],record('T1','appointments',$management['id']),$changed),'Comissão de gestão não avalia servidores');

$row=['cpf'=>'39053344705','nome'=>'Pessoa Importada QA','matricula'=>'IMP-1','unidade_id'=>'U','plano_id'=>'P','cargo'=>'Cargo QA','jornada'=>'20','nivel'=>'II','referencia'=>'A','posse'=>'2020-01-01','enquadramento_data'=>'2024-01-01','enquadramento_ato'=>'Ato QA','regime'=>'Estatutário','estagio'=>'Concluído','lotacao'=>'Local QA','email'=>'pessoa@example.invalid','telefone'=>'(99) 99999-0000'];
$input=['mode'=>'servidores','csv'=>stageCsv('servidores',[$row,array_replace($row,['matricula'=>'IMP-2'])])];$pv=stageCall($admin,'staff-import/preview',$input);check($pv['errors']===0&&$pv['count']===2,'Mesmo CPF permite duas matrículas');check(!array_filter(records('T1','links'),fn($p)=>$p['registration']==='IMP-1'),'Prévia não grava');
$bad=stageCall($admin,'staff-import/preview',['mode'=>'servidores','csv'=>stageCsv('servidores',[$row,array_replace($row,['cpf'=>'11111111111','matricula'=>'BAD'])])]);check($bad['errors']===1,'CPF inválido relatado por linha');
$bad=stageCall($admin,'staff-import/preview',['mode'=>'servidores','csv'=>stageCsv('servidores',[$row,$row])]);check($bad['errors']===1,'Matrícula duplicada relatada');
$committed=stageCall($admin,'staff-import/commit',$input+['token'=>$pv['token'],'confirmed'=>true]);check($committed['count']===2,'Importação confirmada');
$imported=array_values(array_filter(records('T1','links'),fn($p)=>str_starts_with($p['registration'],'IMP-')));check(count($imported)===2&&$imported[0]['personId']===$imported[1]['personId'],'Pessoa única e carreiras independentes');
denied(fn()=>stageCall($admin,'staff-import/commit',$input+['token'=>$pv['token'],'confirmed'=>true]),'Reenvio não duplica');
$history=['id_externo'=>'H-1','matricula'=>'IMP-1','tipo'=>'tempo','inicio'=>'2024-03-01','fim'=>'','descricao'=>'Suspensão em curso QA','efeito'=>'exclude','modalidade'=>'all','fundamento'=>'Ato QA'];
$hi=['mode'=>'historicos','csv'=>stageCsv('historicos',[$history])];$hp=stageCall($admin,'staff-import/preview',$hi);check($hp['errors']===0,'Histórico permite período em curso');stageCall($admin,'staff-import/commit',$hi+['token'=>$hp['token'],'confirmed'=>true]);check(count(array_filter(records('T1','service_periods'),fn($p)=>$p['type']==='Suspensão em curso QA'&&$p['end']===null))===1,'Importa suspensão aberta');
check(stageCall($admin,'staff-import/preview',$hi)['errors']===1,'ID externo impede repetição histórica');
$enrollment=['id_externo'=>'H-2','matricula'=>'IMP-1','tipo'=>'enquadramento','inicio'=>'2020-01-01','fim'=>'2023-12-31','descricao'=>'Posição anterior QA','fundamento'=>'Ato QA','nivel'=>'I','referencia'=>'A','cargo'=>'Cargo QA','jornada'=>'20'];
$ei=['mode'=>'historicos','csv'=>stageCsv('historicos',[$enrollment])];$ep=stageCall($admin,'staff-import/preview',$ei);check($ep['errors']===0&&$ep['pending']===1,'Histórico sem tabela fica explicitamente pendente');stageCall($admin,'staff-import/commit',$ei+['token'=>$ep['token'],'confirmed'=>true]);
check(stageCall($admin,'staff-import/preview',['mode'=>'historicos','csv'=>stageCsv('historicos',[array_replace($enrollment,['id_externo'=>'H-3'])])])['errors']===1,'Enquadramento sobreposto bloqueado');
$stale=['mode'=>'servidores','csv'=>stageCsv('servidores',[array_replace($row,['matricula'=>'IMP-3'])])];$sp=stageCall($admin,'staff-import/preview',$stale);$unit=record('T1','units','U');save('T1','units',$unit,$unit['version']);denied(fn()=>stageCall($admin,'staff-import/commit',$stale+['token'=>$sp['token'],'confirmed'=>true]),'Mudança após prévia invalida token');
$viewer=array_replace($admin,['role'=>'viewer']);denied(fn()=>stageCall($viewer,'staff-import/preview',$input),'Consulta não importa');
$foreign=array_replace($admin,['tenant'=>'T2']);denied(fn()=>stageCall($foreign,'staff-import/preview',$input),'Isolamento municipal');

$plan=record('T1','plans','P');$plan['family']='legislativo';save('T1','plans',$plan,$plan['version']);$target=record('T1','links','LT');$target['evaluations']=[];$target['processes']=[];$target['leaves']=[];$target['salary']=array_replace(record('T1','links','L')['enrollmentHistory'][0]['salary'],['context'=>salaryContextKey($target)]);save('T1','links',$target,$target['version']);
$target=record('T1','links','LT');$x=['id'=>'PROC','type'=>'Progressão horizontal','start'=>'2024-01-01','end'=>'2025-12-31','fromLevel'=>'II','fromStage'=>'A','targetLevel'=>'II','targetStage'=>'B','state'=>'Em análise'];$target['processes']=[$x];save('T1','links',$target,$target['version']);$target=record('T1','links','LT');
stageCall($admin,'access/salaries/official-map',['linkId'=>'LT','version'=>$target['version'],'position'=>'target','processId'=>'PROC','jobId'=>$jobId,'effect'=>'2026-01-01','checked'=>true,'basis'=>'QA']);
$cert=record('T1','certificates','C');$cert['id']='CT';$cert['link']='LT';unset($cert['version']);save('T1','certificates',$cert);
$ctx=['linkId'=>'LT','processId'=>'PROC','at'=>'2025-12-31','effect'=>'2026-01-01'];$d=stageCall($admin,'processes/dossier',$ctx);check($d['check']['outcome']==='Habilitado para análise','Dossiê do Legislativo integra requisitos');
$target=record('T1','links','LT');stageCall($admin,'processes/assign-review',['linkId'=>'LT','processId'=>'PROC','appointmentId'=>$a['id'],'version'=>$target['version'],'reason'=>'QA parecer requerido']);$d=stageCall($admin,'processes/dossier',$ctx);check($d['reviewState']==='Pendente','Comissão designada exige todos os pareceres');
$decision=['decision'=>'Aprovar','reason'=>'Conferido QA','act'=>'Portaria QA','published'=>'2025-12-31','confirmed'=>true];denied(fn()=>stageCall($admin,'processes/decide',$ctx+$decision+['version'=>$d['linkVersion'],'token'=>$d['token']]),'Pareceres pendentes bloqueiam');
foreach(['M1','MS'] as $member){$target=record('T1','links','LT');stageCall($members[$member],'processes/opinion',['linkId'=>'LT','processId'=>'PROC','version'=>$target['version'],'decision'=>'Favorável','reason'=>'Parecer QA']);}
$d=stageCall($admin,'processes/dossier',$ctx);check($d['reviewState']==='Favorável','Parecer de todos os titulares');
$result=stageCall($admin,'processes/decide',$ctx+$decision+['version'=>$d['linkVersion'],'token'=>$d['token']]);$target=record('T1','links','LT');check($result['state']==='Aprovado'&&$target['stage']==='B','Conclusão do Legislativo aplica referência destino');check(isset($target['processes'][0]['careerCheck'],$target['processes'][0]['dossierHash']),'Memória, regra e dossiê preservados');
denied(fn()=>stageCall($admin,'processes/decide',$ctx+$decision+['version'=>$d['linkVersion'],'token'=>$d['token']]),'Concessão repetida bloqueada');
$unit=record('T1','units','U');denied(fn()=>stageCall($admin,'registry/deactivate',['kind'=>'units','id'=>'U','version'=>$unit['version'],'reason'=>'QA']),'Dependências bloqueiam inativação de unidade');
$imp=$imported[0];$imp=record('T1','links',$imp['id']);stageCall($admin,'registry/delete',['kind'=>'links','id'=>$imp['id'],'version'=>$imp['version'],'reason'=>'QA preserva histórico']);$deleted=record('T1','links',$imp['id']);check($deleted['deleted']&&!$deleted['active']&&count($deleted['registryHistory'])===1,'Exclusão lógica preserva dados e histórico');
$stableModel=assessmentModelAt('T1','P','2024-01-01');
stageCall($admin,'assessments/end',['id'=>$m['id'],'version'=>record('T1','assessment_models',$m['id'])['version'],'endedOn'=>today(),'basis'=>'Encerramento QA']);
check(j(assessmentModelAt('T1','P','2024-01-01'))===j($stableModel),'Encerrar modelo preserva formulário de períodos iniciados');
check(assessmentModelAt('T1','P',careerDate(today())->modify('+1 day')->format('Y-m-d'))===null,'Modelo encerrado não atende novos períodos');
echo 'OK: '.($n-$before).' verificações adicionais de avaliações, comissões, processos, importação e ações. '.PHP_EOL;
