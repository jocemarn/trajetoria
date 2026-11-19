<?php
declare(strict_types=1);
$work=sys_get_temp_dir().'/trajetoria-foundation-'.bin2hex(random_bytes(6));
mkdir($work,0700);
define('TR_PRIVATE',$work);
define('TR_RELEASE',dirname(__DIR__).'/private/releases/1.0.0');
file_put_contents($work.'/config.php','<?php return '.var_export([
    'dsn'=>'sqlite::memory:','db_user'=>'','db_password'=>'','key'=>base64_encode(random_bytes(32))
],true).';');
require TR_RELEASE.'/app/core.php';
require TR_RELEASE.'/app/schema.php';
require TR_RELEASE.'/app/domain.php';
require TR_RELEASE.'/app/api.php';
require TR_RELEASE.'/app/salaries.php';
require TR_RELEASE.'/app/annual.php';
$n=0;
function check(bool $ok,string $message):void {global $n;if(!$ok)throw new RuntimeException($message);$n++;}
function denied(callable $operation,string $message):void {try{$operation();}catch(Fault $error){check(true,$message);return;}throw new RuntimeException('Não bloqueou: '.$message);}
function callApi(string $path,array $body=[],string $method='POST'):array {
    global $user;
    $_GET['tenant']='T1';
    return routeApi($user,$path,$method,$body);
}
try {
    createSchema(db());
    foreach ([['T1','QA','MA',0],['T2','Outro','MA',0],['DEMONSTRACAO','Demo','DEMO',1]] as $t) query('INSERT INTO tr_tenants(id,name,state,is_demo) VALUES(?,?,?,?)',$t);
    $user=['id'=>'QA','tenant'=>'T1','role'=>'admin','unit'=>null,'power'=>null,'person'=>null,'is_demo'=>0];
    save('T1','plans',['id'=>'P','name'=>'Plano QA','family'=>'geral','power'=>'Executivo']);
    save('T1','salary_frames',['id'=>'F','plan'=>'P','name'=>'Quadro QA']);
    $input=['frameId'=>'F','version'=>1,'jobId'=>'','name'=>'Cargo QA','workload'=>20,'effective'=>'2024-01-01','law'=>'Lei QA — tabela','baseSequence'=>0,
        'csv'=>"nivel;referencia;salario_base\nII;A;2000,00\nII;B;2100,00\nIII;A;2400,00\nIII;B;2500,00"];
    denied(fn()=>officialCsv("nivel;referencia;salario_base\nII;A;1\nII;A;2"),'Coordenada duplicada');
    denied(fn()=>officialCsv("nivel;referencia;salario_base\nII;A;=1+1"),'Fórmula em valor monetário');
    denied(fn()=>officialCsv("nivel;referencia;salario_base\nII;A;0"),'Valor zero');
    $preview=callApi('access/salaries/official-preview',$input);
    check($preview['count']===4,'Prévia sem escrever');
    check(!records('T1','salary_jobs'),'Prévia preserva banco');
    denied(fn()=>callApi('access/salaries/official-import',$input+['checked'=>true,'token'=>'invalid']),'Token inválido');
    $created=callApi('access/salaries/official-import',$input+['checked'=>true,'token'=>$preview['token']]);
    $jobId=$created['jobId'];
    check(count(officialTables('T1',$jobId))===1,'Importa versão imutável');
    denied(fn()=>callApi('access/salaries/official-import',$input+['checked'=>true,'token'=>$preview['token']]),'Quadro concorrente');
    $p=['id'=>'L','personId'=>'PERSON','name'=>'Pessoa QA','registration'=>'001','cpf'=>'','unitId'=>'U','power'=>'Executivo',
        'planId'=>'P','role'=>'Cargo QA','workload'=>20,'level'=>'II','stage'=>'A','admission'=>'2024-01-01','enrollmentDate'=>'2024-01-01',
        'probation'=>'Concluído','processes'=>[],'paidFunctions'=>[],'leaves'=>[],'evaluations'=>[],'events'=>[]];
    save('T1','links',$p);
    callApi('access/salaries/official-map',['linkId'=>'L','version'=>1,'position'=>'current','jobId'=>$jobId,'checked'=>true,'basis'=>'Conferência QA']);
    $p=record('T1','links','L');
    check(salaryQuote('T1',$p['salary'],$p,'2024-02-01')['cents']===200000,'Valor inicial na vigência');
    denied(fn()=>salaryQuote('T2',$p['salary'],$p,'2024-02-01'),'Isolamento da tabela');
    denied(fn()=>salaryQuote('T1',$p['salary'],array_replace($p,['role'=>'Outro cargo']),'2024-02-01'),'Cargo divergente');
    $frame=record('T1','salary_frames','F');
    callApi('access/salaries/adjust',['frameId'=>'F','version'=>$frame['version'],'kind'=>'annual','percent'=>'10','effective'=>'2024-07-01','law'=>'Lei QA anual']);
    check(salaryQuote('T1',$p['salary'],$p,'2024-08-01')['cents']===220000,'Reajuste sobre a célula');
    $frame=record('T1','salary_frames','F');
    $revision=array_replace($input,['jobId'=>$jobId,'version'=>$frame['version'],'effective'=>'2025-01-01',
        'csv'=>"nivel;referencia;salario_base\nII;A;2300\nII;B;2400\nIII;A;2600\nIII;B;2700"]);
    $pv=callApi('access/salaries/official-preview',$revision);
    callApi('access/salaries/official-import',$revision+['checked'=>true,'token'=>$pv['token']]);
    check(salaryQuote('T1',$p['salary'],$p,'2024-02-01')['cents']===200000,'Histórico anterior preservado');
    check(salaryQuote('T1',$p['salary'],$p,'2025-02-01')['cents']===230000,'Nova vigência sem reaplicar índice anterior');
    $frame=record('T1','salary_frames','F');
    callApi('access/salaries/adjust',['frameId'=>'F','version'=>$frame['version'],'kind'=>'annual','percent'=>'5','effective'=>'2025-05-01','law'=>'Lei QA anual 2025']);
    check(salaryQuote('T1',$p['salary'],$p,'2025-06-01')['cents']===241500,'Índice posterior aplicado uma vez');
    $process=['id'=>'X','type'=>'Progressão horizontal','start'=>'2024-01-01','end'=>'2025-12-31','fromLevel'=>'II','fromStage'=>'A','targetLevel'=>'II','targetStage'=>'B','state'=>'Em análise'];
    $p['processes']=[$process];save('T1','links',$p,$p['version']);
    $p=record('T1','links','L');
    callApi('access/salaries/official-map',['linkId'=>'L','version'=>$p['version'],'position'=>'target','processId'=>'X',
        'jobId'=>$jobId,'effect'=>'2026-01-01','checked'=>true,'basis'=>'Destino QA']);
    $p=record('T1','links','L');$process=$p['processes'][0];
    check(salaryQuote('T1',$process['salaryTarget'],$p,'2026-01-01',$process)['cents']===252000,'Destino é célula da tabela');
    check(careerMonthsAfter('2024-01-31',1)==='2024-02-29','Aniversário em fim de mês');
    $report=careerTimeReport($p,'2024-01-01','2024-01-31',[
        ['source'=>'a','type'=>'A','start'=>'2024-01-10','end'=>'2024-01-20','effect'=>'exclude','basis'=>'QA'],
        ['source'=>'b','type'=>'B','start'=>'2024-01-15','end'=>'2024-01-25','effect'=>'exclude','basis'=>'QA'],
        ['source'=>'c','type'=>'C','start'=>'2024-01-18','end'=>'2024-01-19','effect'=>'pending','basis'=>'QA']
    ]);
    check([$report['countedDays'],$report['excludedDays'],$report['pendingDays']] === [15,14,2],'Sobreposições sem dupla contagem');
    $reset=careerTimeReport($p,'2024-01-01','2024-01-31',[
        ['source'=>'r','type'=>'Interrupção','start'=>'2024-01-10','end'=>'2024-01-12','effect'=>'reset','basis'=>'QA']
    ]);
    check($reset['cycleDays']===19&&$reset['cycleStart']==='2024-01-13','Reinício do interstício');
    $config=['label'=>'Horizontal QA','basis'=>'Lei QA, artigo de progressão','jobId'=>$jobId,'kind'=>'Progressão horizontal',
        'fromLevel'=>'II','fromReference'=>'A','toLevel'=>'II','toReference'=>'B','from'=>'2024-01-01','until'=>'2025-12-31',
        'stableRequired'=>true,'performanceRequired'=>false,'coursesRequired'=>true,'titleRequired'=>false,
        'timeStart'=>'enrollment','timeUnit'=>'months','timeValue'=>24,'minHours'=>40,'certificateWindow'=>'cycle',
        'calendar'=>'annual','cutoff'=>'12-31','publication'=>'12-31','effectMonthDay'=>'01-01','effectOffset'=>1,'pending'=>''];
    $rule=callApi('career/rule-draft',['planId'=>'P','config'=>$config])['rule'];
    $pending=callApi('career/rule-draft',['planId'=>'P','config'=>array_replace($config,['pending'=>'Aguarda ato municipal'])])['rule'];
    denied(fn()=>callApi('career/rule-status',['id'=>$pending['id'],'version'=>$pending['version'],'status'=>'Validada','confirmed'=>true,'basis'=>'QA']),'Pendência bloqueia validação');
    $rule=callApi('career/rule-status',['id'=>$rule['id'],'version'=>$rule['version'],'status'=>'Validada','confirmed'=>true,'basis'=>'Conferido'])['rule'];
    $rule=callApi('career/rule-status',['id'=>$rule['id'],'version'=>$rule['version'],'status'=>'Ativa','confirmed'=>true,'basis'=>'Ato QA'])['rule'];
    check($rule['status']==='Ativa','Ativação após validação');
    $duplicate=callApi('career/rule-draft',['planId'=>'P','config'=>$config])['rule'];
    $duplicate=callApi('career/rule-status',['id'=>$duplicate['id'],'version'=>$duplicate['version'],'status'=>'Validada','confirmed'=>true,'basis'=>'QA'])['rule'];
    denied(fn()=>callApi('career/rule-status',['id'=>$duplicate['id'],'version'=>$duplicate['version'],'status'=>'Ativa','confirmed'=>true,'basis'=>'QA']),'Ativação sobreposta');
    save('T1','certificates',['id'=>'C','link'=>'L','kind'=>'Capacitação','hours'=>40,'progressionEligible'=>true,
        'createdDay'=>'2025-08-01','completed'=>'2025-08-01','reviewers'=>[['memberId'=>'M']],'votes'=>[['member'=>'M','decision'=>'Aprovar']]]);
    $result=careerEvaluate('T1',$p,$process,'2025-12-31',null,false,'2026-01-01');
    check($result['outcome']==='Habilitado para análise','Conferência integrada completa');
    check($result['time']['cycleDays']===731,'Ano bissexto incluído');
    $same=careerEvaluate('T1',$p,$process,'2025-12-31',null,false,'2026-01-01');
    check($same['fingerprint']===$result['fingerprint'],'Memória reproduzível');
    $p['leaves']=[['id'=>'LEAVE','type'=>'Cessão QA','start'=>'2024-01-10','end'=>'2024-01-20']];
    save('T1','links',$p,$p['version']);$p=record('T1','links','L');
    check(careerEvaluate('T1',$p,$p['processes'][0],'2025-12-31',null,false,'2026-01-01')['outcome']==='Pendente','Cessão não classificada bloqueia');
    $period=callApi('career/period',['linkId'=>'L','linkVersion'=>$p['version'],'source'=>'leave:LEAVE','type'=>'Cessão QA',
        'start'=>'2024-01-10','end'=>'2024-01-20','effect'=>'count','modality'=>'all','basis'=>'Ato QA permite contagem'])['period'];
    $p=record('T1','links','L');
    check(careerEvaluate('T1',$p,$p['processes'][0],'2025-12-31',null,false,'2026-01-01')['outcome']==='Habilitado para análise','Resolução regulariza o tempo');
    $calendar=careerAnnualCalendar('T1','P',2025);
    check($calendar['cutoff']==='2025-12-31','Calendário próprio substitui corte fixo');
    $eligible=callApi('access/annual/qualify',['linkId'=>'L','version'=>$p['version'],'processId'=>'X','cycle'=>2025,
        'checks'=>[true,true,true,true,true],'reason'=>'QA requisitos conferidos']);
    $rank=callApi('access/annual/rank',['plan'=>'P','cycle'=>2025,'criteria'=>['service']]);
    check(count($rank['rows'])===1,'Habilitação e classificação integradas');
    $p=record('T1','links','L');
    callApi('career/period',['id'=>$period['id'],'version'=>$period['version'],'linkId'=>'L','linkVersion'=>$p['version'],
        'source'=>'leave:LEAVE','type'=>'Cessão QA','start'=>'2024-01-10','end'=>'2024-01-20','effect'=>'exclude','modality'=>'all','basis'=>'Revisão do ato QA']);
    denied(fn()=>callApi('access/annual/rank',['plan'=>'P','cycle'=>2025,'criteria'=>['service']]),'Alteração do tempo invalida habilitação');
    check(count(record('T1','service_periods',$period['id'])['history'])===1,'Histórico da classificação preservado');

    $p=record('T1','links','L');$period=record('T1','service_periods',$period['id']);
    callApi('career/period',['id'=>$period['id'],'version'=>$period['version'],'linkId'=>'L','linkVersion'=>$p['version'],
        'source'=>'leave:LEAVE','type'=>'Cessão QA','start'=>'2024-01-10','end'=>'2024-01-20','effect'=>'count','modality'=>'all','basis'=>'Restabelece contagem QA']);
    $p=record('T1','links','L');
    $eligible=callApi('access/annual/qualify',['linkId'=>'L','version'=>$p['version'],'processId'=>'X','cycle'=>2025,
        'checks'=>[true,true,true,true,true],'reason'=>'QA conferência final']);
    $selection=['plan'=>'P','cycle'=>2025,'effect'=>'2026-01-01','criteria'=>['service'],
        'eligibilityIds'=>[$eligible['id']],'budgetOptions'=>['months'=>12,'chargesPct'=>'','otherAnnual'=>'']];
    $budget=callApi('access/annual/budget',$selection);
    check($budget['monthlyCents']===10500,'Impacto do destino oficial');
    $batch=callApi('access/annual/finalize',$selection+['budgetToken'=>$budget['token'],'confirmed'=>true,
        'basis'=>'Critérios QA','number'=>'QA-1','published'=>'2025-12-31','reference'=>'Diário QA']);
    $granted=record('T1','links','L');
    check($granted['stage']==='B'&&$granted['processes'][0]['state']==='Aprovado','Concessão percorre fluxo normal');
    check(isset($granted['processes'][0]['careerCheck']['ruleId']),'Concessão guarda regra e memória');
    check(salaryQuote('T1',$granted['salary'],$granted,'2026-01-01')['cents']===252000,'Salário vinculado após concessão');

    check(salaryQuote('T1',$granted['salary'],$granted,'2024-02-01')['cents']===200000,'Consulta histórica usa o enquadramento anterior');
    check($granted['enrollmentHistory'][0]['stage']==='A','Referência anterior preservada');
    denied(fn()=>callApi('access/annual/finalize',$selection+['budgetToken'=>$budget['token'],'confirmed'=>true,
        'basis'=>'QA','number'=>'QA-2','published'=>'2025-12-31','reference'=>'Diário QA']),'Concessão duplicada');

    $_GET=['tenant'=>'T2','planId'=>'P'];
    denied(fn()=>routeApi($user,'career/context','GET',[]),'Acesso cruzado bloqueado');
    $performanceConfig=array_replace($config,['performanceRequired'=>true,'evaluationCount'=>2,'minScore'=>70,'aggregation'=>'mean']);
    $performancePerson=$p;$performancePerson['evaluations']=[];
    foreach ([['2024-01-01','2024-12-31'],['2025-01-01','2025-12-31']] as [$start,$end]) {
        foreach (['M1','M2'] as $member) $performancePerson['evaluations'][]=[
            'start'=>$start,'end'=>$end,'actor'=>$member,'score'=>80,
            'appointment'=>['members'=>[['memberId'=>'M1'],['memberId'=>'M2']]]];
    }
    check(careerPerformance($performancePerson,'2024-01-01','2025-12-31',$performanceConfig)['state']==='Atende','Consolida membros por período');
    array_pop($performancePerson['evaluations']);
    check(careerPerformance($performancePerson,'2024-01-01','2025-12-31',$performanceConfig)['state']==='Pendente','Parecer ausente não vira avaliação completa');

    $open=careerTimeReport($p,'2024-01-01','2024-01-31',[
        ['source'=>'open','type'=>'Função em exercício','start'=>'2024-01-10','end'=>null,'effect'=>'exclude','basis'=>'Ato QA']
    ]);
    check($open['countedDays']===9&&$open['excludedDays']===22,'Suspensão em curso conta até a data consultada');
    $ongoing=array_replace($p,['paidFunctions'=>[['id'=>'FUNX','start'=>'2024-01-10','end'=>null,'careerEffect'=>'suspend','careerBasis'=>'QA']]]);
    save('T1','service_periods',['id'=>'OPEN-FUNCTION','link'=>'L','source'=>'function:FUNX','type'=>'Função','start'=>'2024-01-10','end'=>null,'effect'=>'exclude','modality'=>'all','basis'=>'QA','history'=>[]]);
    $sources=careerTimeSources('T1',$ongoing,'horizontal');
    $functionSource=array_values(array_filter($sources,fn($source)=>$source['source']==='function:FUNX'))[0];
    check($functionSource['end']===null,'Classificação mantém a função em aberto');
    $ongoing['paidFunctions'][0]['end']='2024-01-20';
    $sources=careerTimeSources('T1',$ongoing,'horizontal');
    $functionSource=array_values(array_filter($sources,fn($source)=>$source['source']==='function:FUNX'))[0];
    check($functionSource['end']==='2024-01-20','Encerramento da função atualiza a classificação existente');
    $live=record('T1','links','L');
    $openPeriod=callApi('career/period',['linkId'=>'L','linkVersion'=>$live['version'],'type'=>'Suspensão em curso',
        'start'=>'2024-01-10','end'=>'','effect'=>'exclude','modality'=>'all','basis'=>'Ato QA'])['period'];
    check($openPeriod['end']===null,'Cadastro aceita período sem fim');
    $live=record('T1','links','L');
    callApi('career/period',['id'=>$openPeriod['id'],'version'=>$openPeriod['version'],'linkId'=>'L','linkVersion'=>$live['version'],
        'type'=>'Suspensão encerrada','start'=>'2024-01-10','end'=>'2024-01-20','effect'=>'exclude','modality'=>'all','basis'=>'Ato de encerramento QA']);
    check(record('T1','service_periods',$openPeriod['id'])['end']==='2024-01-20','Período em curso pode ser encerrado com histórico');

    echo "OK: $n verificações de tabelas oficiais, vigências, regras, calendário, tempo e isolamento.\n";
} finally {
    foreach (glob($work.'/*') as $file) if (is_file($file)) unlink($file);
    rmdir($work);
}
