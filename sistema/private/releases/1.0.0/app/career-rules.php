<?php
declare(strict_types=1);

function careerInteger($value, int $min, int $max, string $label): int
{
    $number=num($value,$min,$max,$label);
    if (floor($number)!==$number) throw new Fault($label.' deve ser inteiro.');
    return (int)$number;
}

function careerMonthDay($value, string $label): string
{
    $value=need($value,$label,5);
    day('2001-'.$value,$label);
    return $value;
}

function careerConfig(array $b): array
{
    $config=[];
    foreach (['label'=>120,'basis'=>1000,'jobId'=>64,'fromLevel'=>15,'fromReference'=>15,
        'toLevel'=>15,'toReference'=>15] as $field=>$max) $config[$field]=need($b[$field]??'',$field,$max);
    foreach (['fromLevel','fromReference','toLevel','toReference'] as $field) $config[$field]=mb_strtoupper($config[$field]);
    $config['kind']=need($b['kind']??'','modalidade',80);
    if (!in_array($config['kind'],['Progressão horizontal','Promoção vertical','Evolução por tempo de serviço'],true)) throw new Fault('Modalidade inválida.');
    if ([$config['fromLevel'],$config['fromReference']]===[$config['toLevel'],$config['toReference']]) throw new Fault('Origem e destino devem ser diferentes.');
    $config['from']=day($b['from']??'');
    $config['until']=empty($b['until'])?null:day($b['until']);
    if ($config['until'] && $config['until']<$config['from']) throw new Fault('Vigência inválida.');
    foreach (['stableRequired','performanceRequired','coursesRequired','titleRequired'] as $field) {
        if (!array_key_exists($field,$b) || !is_bool($b[$field])) throw new Fault('Defina '.$field.'.');
        $config[$field]=$b[$field];
    }
    $config['timeStart']=$b['timeStart']??'';
    $config['timeUnit']=$b['timeUnit']??'';
    if (!in_array($config['timeStart'],['admission','enrollment'],true) || !in_array($config['timeUnit'],['months','days'],true)) throw new Fault('Base de contagem inválida.');
    $config['timeValue']=careerInteger($b['timeValue']??'',0,($config['timeUnit']==='months'?600:20000),'interstício');
    $config['minScore']=$config['performanceRequired']?num($b['minScore']??'',0,100,'nota mínima'):null;
    $config['evaluationCount']=$config['performanceRequired']?careerInteger($b['evaluationCount']??'',1,20,'avaliações consecutivas'):null;
    $config['aggregation']=$config['performanceRequired']?($b['aggregation']??''):null;
    if ($config['performanceRequired'] && !in_array($config['aggregation'],['mean','minimum'],true)) throw new Fault('Defina a consolidação das notas.');
    $config['minHours']=$config['coursesRequired']?num($b['minHours']??'',1,10000,'horas mínimas'):null;
    $config['certificateWindow']=$b['certificateWindow']??'';
    if (!in_array($config['certificateWindow'],['career','cycle'],true)) throw new Fault('Defina o período de aproveitamento de certificados.');
    $config['calendar']=$b['calendar']??'';
    if (!in_array($config['calendar'],['continuous','annual'],true)) throw new Fault('Calendário inválido.');
    $config['cutoff']=$config['calendar']==='annual'?careerMonthDay($b['cutoff']??'','fechamento'):null;
    $config['publication']=$config['calendar']==='annual'?careerMonthDay($b['publication']??'','prazo da publicação'):null;
    $config['effectMonthDay']=$config['calendar']==='annual'?careerMonthDay($b['effectMonthDay']??'','início dos efeitos'):null;
    $config['effectOffset']=$config['calendar']==='annual'?careerInteger($b['effectOffset']??'',0,5,'exercício dos efeitos'):0;
    if ($config['calendar']==='annual' && ($config['publication']<$config['cutoff'] ||
        ($config['effectOffset']===0 && $config['effectMonthDay']<$config['cutoff']))) throw new Fault('Calendário anterior ao fechamento.');
    $config['pending']=trim((string)($b['pending']??''));
    if (mb_strlen($config['pending'])>2000) throw new Fault('Pendências muito extensas.');
    return $config;
}

function careerRuleTransition(array $config): string
{
    return j([$config['jobId'],$config['kind'],$config['fromLevel'],$config['fromReference'],$config['toLevel'],$config['toReference']]);
}

function careerValidateRule(string $tenant, array $rule): void
{
    $c=careerConfig($rule['config']);
    if ($c['pending']!=='') throw new Fault('Resolva as pendências antes de validar.');
    $job=record($tenant,'salary_jobs',$c['jobId']);
    $frame=$job?record($tenant,'salary_frames',$job['frame']):null;
    if (!$frame || $frame['plan']!==$rule['plan'] || ($job['source']??'')!=='municipal') throw new Fault('Associe um cargo oficial deste plano.');
    $table=officialTableAt($tenant,$job['id'],$c['from']);
    $keys=array_column($table['cells'],'key');
    foreach ([[$c['fromLevel'],$c['fromReference']],[$c['toLevel'],$c['toReference']]] as $coord) {
        if (!in_array(hash('sha256',j($coord)),$keys,true)) throw new Fault('Origem ou destino ausente na tabela da vigência.');
    }
}

function careerRules(string $tenant, string $plan): array
{
    return array_values(array_filter(records($tenant,'rules'),fn($r)=>$r['plan']===$plan && ($r['engineVersion']??0)===2));
}

function careerRuleEnd(array $rule): string
{
    $end=$rule['config']['until']??'9999-12-31';
    if (!empty($rule['endedOn'])) $end=min($end,careerDate($rule['endedOn'])->modify('-1 day')->format('Y-m-d'));
    return $end;
}

function careerRuleUsable(array $rule, string $at): bool
{
    return in_array($rule['status'],['Ativa','Encerrada'],true) && $at>=$rule['config']['from'] && $at<=careerRuleEnd($rule);
}

function careerAnnualCalendar(string $tenant, string $plan, int $cycle): array
{
    $municipality=one('SELECT is_demo FROM tr_tenants WHERE id=?',[$tenant]);
    if ($municipality['is_demo']??false) return ['cutoff'=>$cycle.'-06-30','publication'=>$cycle.'-12-31','effectYear'=>$cycle+1,'effectMonthDay'=>'01-01'];
    $found=[];
    foreach (careerRules($tenant,$plan) as $rule) {
        $c=$rule['config'];
        if ($c['calendar']!=='annual') continue;
        $cutoff=$cycle.'-'.$c['cutoff'];
        if (!careerRuleUsable($rule,$cutoff)) continue;
        $found[j([$c['cutoff'],$c['publication'],$c['effectOffset'],$c['effectMonthDay']])]=[
            'cutoff'=>$cutoff,'publication'=>$cycle.'-'.$c['publication'],
            'effectYear'=>$cycle+$c['effectOffset'],'effectMonthDay'=>$c['effectMonthDay']];
    }
    if (count($found)!==1) throw new Fault('Configure um calendário anual único e vigente para este plano.');
    return array_values($found)[0];
}

function careerPerformance(array $p, string $start, string $end, array $c): array
{
    $groups=[];
    foreach ($p['evaluations']??[] as $evaluation) {
        if (!empty($evaluation['manual']) || empty($evaluation['start']) || empty($evaluation['end']) ||
            $evaluation['start']<$start || $evaluation['end']>$end || empty($evaluation['appointment']['members'])) continue;
        $key=j([$evaluation['start'],$evaluation['end']]);
        $groups[$key][]=$evaluation;
    }
    uksort($groups,fn($a,$b)=>strcmp(json_decode($b,true)[1],json_decode($a,true)[1]));
    $scores=[];
    foreach (array_slice($groups,0,$c['evaluationCount']) as $evaluations) {
        $expected=array_column($evaluations[0]['appointment']['members'],'memberId');
        $actors=array_column($evaluations,'actor');
        if (!$expected || count($actors)!==count(array_unique($actors)) || array_diff($expected,$actors) || array_diff($actors,$expected)) {
            return ['state'=>'Pendente','actual'=>'Pareceres incompletos no período'];
        }
        foreach ($evaluations as $evaluation) {
            if (j(array_column($evaluation['appointment']['members'],'memberId'))!==j($expected)) return ['state'=>'Pendente','actual'=>'Composição divergente'];
        }
        $values=array_column($evaluations,'score');
        $scores[]=$c['aggregation']==='mean'?array_sum($values)/count($values):min($values);
    }
    if (count($scores)<$c['evaluationCount']) return ['state'=>'Pendente','actual'=>'Períodos completos insuficientes'];
    return ['state'=>min($scores)>=$c['minScore']?'Atende':'Não atende','actual'=>$scores];
}

function careerEvaluate(string $tenant, array $p, array $process, string $at, ?array $rule=null, bool $preview=false, ?string $effect=null): array
{
    day($at);
    if ($at>today()) throw new Fault('A apuração utiliza data de referência até hoje.');
    if (!$rule) {
        $matches=[];
        foreach (careerRules($tenant,$p['planId']) as $candidate) {
            $c=$candidate['config'];
            $job=record($tenant,'salary_jobs',$c['jobId']);
            if (careerRuleUsable($candidate,$at) &&
                $c['kind']===$process['type'] && $c['fromLevel']===mb_strtoupper($p['level']) &&
                $c['fromReference']===mb_strtoupper($p['stage']) && $c['toLevel']===mb_strtoupper($process['targetLevel']) &&
                $c['toReference']===mb_strtoupper($process['targetStage']) && $job &&
                mb_strtolower($job['name'])===mb_strtolower($p['role']) && (float)$job['workload']===(float)$p['workload']) $matches[]=$candidate;
        }
        if (count($matches)!==1) throw new Fault('Exige uma única regra ativa para cargo, origem e destino.');
        $rule=$matches[0];
    }
    if (($rule['engineVersion']??0)!==2 || $rule['plan']!==$p['planId'] || (!$preview && !careerRuleUsable($rule,$at))) throw new Fault('Regra executável incompatível.');
    $c=$rule['config'];
    $checks=[];
    $add=function(string $label,string $state,$actual,$required)use(&$checks):void{$checks[]=['label'=>$label,'state'=>$state,'actual'=>$actual,'required'=>$required];};
    $add('Vigência da regra',$at>=$c['from']&&$at<=careerRuleEnd($rule)?'Atende':'Não atende',$at,[$c['from'],$c['until']]);
    $job=record($tenant,'salary_jobs',$c['jobId']);
    $scope=$job && mb_strtolower($job['name'])===mb_strtolower($p['role']) && (float)$job['workload']===(float)$p['workload'] &&
        [$c['fromLevel'],$c['fromReference'],$c['toLevel'],$c['toReference'],$c['kind']] ===
        [mb_strtoupper($p['level']),mb_strtoupper($p['stage']),mb_strtoupper($process['targetLevel']),mb_strtoupper($process['targetStage']),$process['type']];
    $add('Cargo, jornada e transição',$scope?'Atende':'Não atende',$p['role'],$c['jobId']);
    if ($c['pending']!=='') $add('Validação municipal','Pendente',$c['pending'],'Sem pendências');
    $end=min($at,day($process['end']));
    $start=max(day($process['start']),$c['timeStart']==='admission'?$p['admission']:$p['enrollmentDate']);
    if ($start>$end) throw new Fault('Período anterior à base de contagem.');
    $mode=$process['type']==='Promoção vertical'?'vertical':'horizontal';
    $time=careerTimeReport($p,$start,$end,careerTimeSources($tenant,$p,$mode));
    $timePass=$c['timeUnit']==='days'?$time['cycleDays']>=$c['timeValue']:
        $time['creditedThrough']>=careerMonthsAfter($time['cycleStart'],$c['timeValue']);
    $add('Tempo computável',$time['pendingDays']?'Pendente':($timePass?'Atende':'Não atende'),$time['cycleDays'].' dias',$c['timeValue'].' '.$c['timeUnit']);
    if ($c['stableRequired']) $add('Estágio probatório',$p['probation']==='Concluído'?'Atende':'Não atende',$p['probation'],'Concluído');
    if ($c['performanceRequired']) {
        $performance=careerPerformance($p,$start,$end,$c);
        $add('Avaliação consolidada',$performance['state'],$performance['actual'],$c['evaluationCount'].' períodos, nota '.$c['minScore']);
    }
    $certificateStart=$c['certificateWindow']==='career'?$p['admission']:$start;
    $certificateEnd=$end;
    $earliestEffect=null;
    if ($c['calendar']==='annual') {
        $cutoff=substr($at,0,4).'-'.$c['cutoff'];
        $add('Fechamento do ciclo',$at>=$cutoff?'Atende':'Pendente',$at,$cutoff);
        $certificateEnd=min($certificateEnd,$cutoff);
        $earliestEffect=((int)substr($at,0,4)+$c['effectOffset']).'-'.$c['effectMonthDay'];
    }
    $used=[];
    foreach (records($tenant,'eligibility') as $eligibility) {
        if ($eligibility['link']===$p['id'] && ($eligibility['batch']??null)) foreach ($eligibility['evidence'] as $proof) $used[]=$proof['id'];
    }
    foreach ($p['processes']??[] as $previous) {
        if ($previous['id']!==$process['id'] && str_starts_with($previous['state'],'Aprovado')) {
            foreach ($previous['careerCheck']['evidence']??[] as $proof) $used[]=$proof['id'];
        }
    }
    $evidence=[];
    foreach (records($tenant,'certificates') as $certificate) {
        if ($certificate['link']!==$p['id'] || ($certificate['progressionEligible']??true)===false ||
            certificateStatus($certificate)!=='Aprovado' || $certificate['completed']<$certificateStart ||
            $certificate['completed']>$certificateEnd || $certificate['createdDay']>$certificateEnd || in_array($certificate['id'],$used,true)) continue;
        if (($c['coursesRequired'] && $certificate['kind']==='Capacitação') ||
            ($c['titleRequired'] && $certificate['kind']==='Titulação' && $certificate['targetLevel']===$c['toLevel'])) $evidence[]=$certificate;
    }
    if ($c['coursesRequired']) {
        $hours=array_sum(array_column(array_filter($evidence,fn($e)=>$e['kind']==='Capacitação'),'hours'));
        $add('Capacitação aprovada',$hours>=$c['minHours']?'Atende':'Não atende',$hours,$c['minHours']);
    }
    if ($c['titleRequired']) {
        $titles=count(array_filter($evidence,fn($e)=>$e['kind']==='Titulação'));
        $add('Titulação aprovada',$titles?'Atende':'Não atende',$titles,'Nível '.$c['toLevel']);
    }
    $salary=null;$effect=$effect??$at;
    if ($earliestEffect && $effect<$earliestEffect) $add('Data de efeito','Não atende',$effect,$earliestEffect);
    try {
        if (($p['salary']['jobId']??'')!==$c['jobId'] || ($process['salaryTarget']['jobId']??'')!==$c['jobId']) throw new Fault('Vincule as duas referências oficiais.');
        $current=salaryQuote($tenant,$p['salary'],$p,$effect);
        $target=salaryQuote($tenant,$process['salaryTarget'],$p,$effect,$process);
        $salary=['current'=>$current,'target'=>$target];
        $add('Referências salariais',$target['cents']>=$current['cents']?'Atende':'Não atende',$target['cents'],$current['cents']);
    } catch (Fault $error) { $add('Referências salariais','Pendente',$error->getMessage(),'Células oficiais vinculadas'); }
    $states=array_column($checks,'state');
    $outcome=in_array('Não atende',$states,true)?'Não atende':(in_array('Pendente',$states,true)?'Pendente':'Habilitado para análise');
    $result=['ruleId'=>$rule['id'],'ruleVersion'=>$rule['version'],'sequence'=>$rule['sequence'],'ruleStatus'=>$rule['status'],
        'linkId'=>$p['id'],'linkVersion'=>$p['version'],'at'=>$at,'effect'=>$effect,'earliestEffect'=>$earliestEffect,
        'checks'=>$checks,'time'=>$time,'evidence'=>$evidence,'salary'=>$salary,'outcome'=>$outcome,'preview'=>$preview];
    $result['fingerprint']=hash('sha256',j($result));
    return $result;
}

function careerGuard(array $u, array $p, array &$process, string $at, ?string $effect=null): ?array
{
    $municipality=one('SELECT is_demo FROM tr_tenants WHERE id=?',[$u['tenant']]);
    if ($municipality['is_demo']??false) return null;
    $result=careerEvaluate($u['tenant'],$p,$process,$at,null,false,$effect);
    if ($result['outcome']!=='Habilitado para análise') throw new Fault('Requisitos da carreira: '.$result['outcome'].'. Consulte a memória de cálculo.',409);
    $process['careerCheck']=$result;
    return $result;
}

function careerApi(array $u, string $action, string $method, array $b): array
{
    $tenant=$u['tenant'];
    if ($action==='time') {
        $p=linkFor($u,$_GET['linkId']??$b['linkId']??'');
        $end=day($_GET['end']??$b['end']??today());
        if ($end>today()) throw new Fault('A apuração deve terminar até hoje.');
        $start=day($_GET['start']??$b['start']??$p['admission']);
        $mode=$_GET['modality']??$b['modality']??'horizontal';
        if (!in_array($mode,['horizontal','vertical'],true)) throw new Fault('Modalidade inválida.');
        $sources=careerTimeSources($tenant,$p,$mode);
        return ['report'=>careerTimeReport($p,$start,$end,$sources),'sources'=>$sources,
            'periods'=>array_values(array_filter(records($tenant,'service_periods'),fn($r)=>$r['link']===$p['id'])),'linkVersion'=>$p['version']];
    }
    if ($action==='period') {
        if ($method!=='POST') throw new Fault('Método não permitido.',405);
        return careerSavePeriod($u,$b);
    }
    if ($action==='evaluate') {
        if ($method!=='POST' || !in_array($u['role'],['admin','manager'],true)) throw new Fault('Perfil sem permissão de conferência.',403);
        $p=linkFor($u,$b['linkId']??'');
        $rule=record($tenant,'rules',$b['ruleId']??'');
        if (!$rule || ($rule['engineVersion']??0)!==2) throw new Fault('Versão executável não encontrada.');
        $at=day($b['at']??today());
        $process=null;
        foreach ($p['processes'] as $candidate) if ($candidate['id']===($b['processId']??'') && $candidate['state']==='Em análise') $process=$candidate;
        if (!$process) {
            $c=$rule['config'];
            $process=['id'=>'preview','type'=>$c['kind'],'start'=>$c['timeStart']==='admission'?$p['admission']:$p['enrollmentDate'],
                'end'=>$at,'targetLevel'=>$c['toLevel'],'targetStage'=>$c['toReference']];
        }
        return careerEvaluate($tenant,$p,$process,$at,$rule,true,day($b['effect']??$at));
    }
    fullManager($u);
    if ($action==='calendar') {
        $plan=record($tenant,'plans',$_GET['planId']??$b['planId']??'');
        if (!$plan) throw new Fault('Plano não encontrado.',404);
        $cycle=careerInteger($_GET['cycle']??$b['cycle']??'',2000,2200,'ciclo');
        return careerAnnualCalendar($tenant,$plan['id'],$cycle);
    }
    if ($action==='context') {
        $plan=record($tenant,'plans',$_GET['planId']??$b['planId']??'');
        if (!$plan) throw new Fault('Plano não encontrado.',404);
        $frames=array_column(array_filter(records($tenant,'salary_frames'),fn($f)=>$f['plan']===$plan['id']),'id');
        return ['plan'=>$plan,'rules'=>careerRules($tenant,$plan['id']),
            'jobs'=>array_values(array_filter(records($tenant,'salary_jobs'),fn($j)=>in_array($j['frame'],$frames,true)&&($j['source']??'')==='municipal'))];
    }
    if ($method!=='POST') throw new Fault('Método não permitido.',405);
    if ($action==='rule-draft') {
        $plan=record($tenant,'plans',$b['planId']??'');
        if (!$plan) throw new Fault('Plano não encontrado.',404);
        $c=careerConfig($b['config']??[]);
        $job=record($tenant,'salary_jobs',$c['jobId']);
        $frame=$job?record($tenant,'salary_frames',$job['frame']):null;
        if (!$frame || $frame['plan']!==$plan['id'] || ($job['source']??'')!=='municipal') throw new Fault('Cargo fora deste plano.');
        $all=array_filter(records($tenant,'rules'),fn($r)=>$r['plan']===$plan['id']);
        $sequence=$all?max(array_column($all,'sequence'))+1:1;
        $rule=save($tenant,'rules',['id'=>uid(),'plan'=>$plan['id'],'sequence'=>$sequence,'engineVersion'=>2,
            'status'=>'Rascunho','config'=>$c,'actor'=>$u['id'],'created'=>date(DATE_ATOM),'history'=>[]]);
        audit($tenant,'career.rule.draft',$rule['id']);
        return ['rule'=>$rule];
    }
    if ($action==='rule-status') {
        $rule=record($tenant,'rules',$b['id']??'');
        if (!$rule || ($rule['engineVersion']??0)!==2 || $rule['version']!==($b['version']??null)) throw new Fault('Versão alterada.',409);
        $status=$b['status']??'';
        if (($rule['status']==='Rascunho' && $status!=='Validada') ||
            ($rule['status']==='Validada' && $status!=='Ativa') ||
            ($rule['status']==='Ativa' && $status!=='Encerrada') || $rule['status']==='Encerrada') throw new Fault('Transição de versão inválida.');
        if (($b['confirmed']??false)!==true) throw new Fault('Confirme a conferência legal.');
        if ($status!=='Encerrada') careerValidateRule($tenant,$rule);
        if ($status==='Encerrada') {
            $endedOn=day($b['endedOn']??'');
            if ($endedOn<today() || $endedOn<=$rule['config']['from']) throw new Fault('O encerramento deve ser posterior ao início e a partir de hoje.');
            $rule['endedOn']=$endedOn;
        }
        if ($status==='Ativa') foreach (careerRules($tenant,$rule['plan']) as $other) {
            if ($other['id']===$rule['id'] || !in_array($other['status'],['Ativa','Encerrada'],true)) continue;
            $a=$rule['config'];$c=$other['config'];
            $overlap=$a['from']<=careerRuleEnd($other) && $c['from']<=careerRuleEnd($rule);
            if ($overlap && careerRuleTransition($a)===careerRuleTransition($c)) throw new Fault('Outra versão ativa cobre essa transição e vigência.',409);
            if ($overlap && $a['calendar']==='annual' && $c['calendar']==='annual' &&
                j([$a['cutoff'],$a['publication'],$a['effectOffset'],$a['effectMonthDay']])!==j([$c['cutoff'],$c['publication'],$c['effectOffset'],$c['effectMonthDay']])) throw new Fault('O calendário anual deve ser único por plano.');
        }
        $rule['history'][]=['status'=>$rule['status'],'actor'=>$u['id'],'at'=>date(DATE_ATOM)];
        $rule['status']=$status;$rule['statusBasis']=need($b['basis']??'','fundamento da validação',1000);
        $rule=save($tenant,'rules',$rule,$rule['version']);
        audit($tenant,'career.rule.status.'.$status,$rule['id']);
        return ['rule'=>$rule];
    }
    throw new Fault('Ferramenta de carreira não encontrada.',404);
}

function careerOfficialRoadmap(string $tenant, array $p): ?array
{
    $plan=record($tenant,'plans',$p['planId']);
    $paths=[];$end=today();
    $process=null;
    foreach ($p['processes']??[] as $candidate) if ($candidate['state']==='Em análise') {$process=$candidate;break;}
    foreach (careerRules($tenant,$p['planId']) as $rule) {
        $c=$rule['config'];$job=record($tenant,'salary_jobs',$c['jobId']);
        if (!careerRuleUsable($rule,$end) || !$job || mb_strtolower($job['name'])!==mb_strtolower($p['role']) ||
            (float)$job['workload']!==(float)$p['workload'] || $c['fromLevel']!==mb_strtoupper($p['level']) ||
            $c['fromReference']!==mb_strtoupper($p['stage'])) continue;
        $start=$c['timeStart']==='admission'?$p['admission']:$p['enrollmentDate'];
        if ($start>$end) continue;
        $mode=$c['kind']==='Promoção vertical'?'vertical':'horizontal';
        $time=careerTimeReport($p,$start,$end,careerTimeSources($tenant,$p,$mode));
        $performance=$c['performanceRequired']?careerPerformance($p,$start,$end,$c):null;
        $score=$performance&&is_array($performance['actual'])?min($performance['actual']):null;
        $paths[]=['type'=>$c['kind'],'policy'=>'official','ruleId'=>$rule['id'],'targetLevel'=>$c['toLevel'],'targetReference'=>$c['toReference'],
            'stable'=>$c['stableRequired'],'score'=>$c['minScore'],'actualScore'=>$score,
            'scoreScope'=>'Notas consolidadas por período conforme a versão '.$rule['sequence'].'.',
            'months'=>$c['timeUnit']==='months'?$c['timeValue']:null,'timeDays'=>$c['timeUnit']==='days'?$c['timeValue']:null,
            'timeReport'=>$time,'hours'=>$c['minHours'],'title'=>$c['titleRequired']?'Titulação aprovada para o nível '.$c['toLevel']:null,
            'horizon'=>'Nível '.$c['toLevel'].' / referência '.$c['toReference'],
            'movement'=>'Transição definida na versão '.$rule['sequence'].' · '.$c['basis'],
            'calendar'=>$c['calendar']==='annual'?'Fechamento '.$c['cutoff'].'; prazo de publicação '.$c['publication']:'Apuração contínua'];
    }
    if (!$paths) return null;
    return ['plan'=>$plan['name'],'basis'=>$plan['basis']??$plan['act']??'','family'=>$plan['family'],'paths'=>$paths,
        'configured'=>true,'articles'=>[],'score'=>null,'scoreCount'=>0,'scoreScope'=>'Consolidação específica de cada modalidade.',
        'approvedHours'=>0,'approvedTitles'=>0,'months'=>null,'process'=>$process,'probationConcluded'=>$p['probation']==='Concluído',
        'specialFunction'=>false,'hasOccurrences'=>count($p['leaves']??[])>0,'effectiveFrom'=>null,
        'notice'=>'As metas seguem as versões ativas. A memória de tempo detalha exclusões e pendências. A habilitação exige a conferência do processo e das referências oficiais.'];
}
