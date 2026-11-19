<?php
declare(strict_types=1);

function careerDate(string $date): DateTimeImmutable
{
    return new DateTimeImmutable(day($date), new DateTimeZone('UTC'));
}

function careerMonthsAfter(string $start, int $months): string
{
    $date = careerDate($start);
    $month = $date->modify('first day of this month')->modify('+'.$months.' months');
    return $month->setDate((int)$month->format('Y'), (int)$month->format('m'),
        min((int)$date->format('d'), (int)$month->format('t')))->format('Y-m-d');
}

function careerTimeSources(string $tenant, array $p, string $modality): array
{
    $sources = [];
    foreach ($p['leaves'] ?? [] as $leave) {
        $sources['leave:'.$leave['id']] = ['source'=>'leave:'.$leave['id'],'type'=>$leave['type'],
            'start'=>$leave['start'],'end'=>$leave['end'],'effect'=>'pending',
            'basis'=>'Efeito do afastamento ainda não classificado.'];
    }
    foreach ($p['paidFunctions'] ?? [] as $function) {
        $effect = ['normal'=>'count','suspend'=>'exclude','special'=>'pending','unreviewed'=>'pending'][$function['careerEffect']] ?? 'pending';
        $sources['function:'.$function['id']] = ['source'=>'function:'.$function['id'],'type'=>'Exercício de função',
            'start'=>$function['start'],'end'=>$function['end'],'effect'=>$effect,
            'basis'=>$function['careerBasis'] ?? ''];
    }
    foreach (records($tenant,'service_periods') as $period) {
        if ($period['link']!==$p['id'] || !in_array($period['modality'],['all',$modality],true)) continue;
        $source = $period['source'] ?: 'period:'.$period['id'];
        $original = $period['source'] ? ($sources[$source] ?? null) : null;
        $start = $original['start'] ?? $period['start'];
        $end = $original ? $original['end'] : $period['end'];
        $effect = $period['source'] && !$original ? 'pending' : $period['effect'];
        $sources[$source] = ['source'=>$source,'periodId'=>$period['id'],'periodVersion'=>$period['version'],
            'type'=>$period['type'],'start'=>$start,'end'=>$end,
            'effect'=>$effect,'basis'=>$period['basis']];
    }
    return array_values($sources);
}

/** Inclusive dates; overlapping occurrences are classified once per segment. */
function careerTimeReport(array $p, string $start, string $end, array $sources): array
{
    day($start); day($end);
    if ($start < $p['admission'] || $end < $start) throw new Fault('Período fora da matrícula.');
    $first = careerDate($start)->getTimestamp();
    $stop = careerDate($end)->modify('+1 day')->getTimestamp();
    $boundaries = [$first,$stop];
    $periods = [];
    $lastReset = $first;
    foreach ($sources as $source) {
        $a = max($first, careerDate($source['start'])->getTimestamp());
        $b = min($stop, careerDate($source['end'] ?: $end)->modify('+1 day')->getTimestamp());
        if ($a >= $b) continue;
        $source['a']=$a; $source['b']=$b; $periods[]=$source;
        $boundaries[]=$a; $boundaries[]=$b;
        if ($source['effect']==='reset') $lastReset=max($lastReset,$b);
    }
    $boundaries=array_values(array_unique($boundaries));sort($boundaries,SORT_NUMERIC);
    $timeline=[];$counted=0;$excluded=0;$pending=0;$cycleDays=0;
    $priority=['count'=>0,'exclude'=>1,'reset'=>2,'pending'=>3];
    for ($i=0;$i<count($boundaries)-1;$i++) {
        $a=$boundaries[$i];$b=$boundaries[$i+1];$effect='count';$details=[];
        foreach ($periods as $period) {
            if ($period['a']>$a || $period['b']<$b) continue;
            $details[]=['source'=>$period['source'],'type'=>$period['type'],'basis'=>$period['basis'],'effect'=>$period['effect']];
            if ($priority[$period['effect']]>$priority[$effect]) $effect=$period['effect'];
        }
        $days=(int)(($b-$a)/86400);
        if ($effect==='count') {
            $counted+=$days;
            $cycleDays+=(int)(max(0,$b-max($a,$lastReset))/86400);
        } elseif ($effect==='pending') $pending+=$days;
        else $excluded+=$days;
        $timeline[]=['start'=>gmdate('Y-m-d',$a),'end'=>gmdate('Y-m-d',$b-86400),'days'=>$days,'effect'=>$effect,'sources'=>$details];
    }
    $cycleStart=gmdate('Y-m-d',$lastReset);
    $creditedThrough=careerDate($cycleStart)->modify('+'.$cycleDays.' days')->format('Y-m-d');
    $report=['start'=>$start,'end'=>$end,'calendarDays'=>(int)(($stop-$first)/86400),
        'countedDays'=>$counted,'excludedDays'=>$excluded,'pendingDays'=>$pending,
        'cycleStart'=>$cycleStart,'cycleDays'=>$cycleDays,'creditedThrough'=>$creditedThrough,'timeline'=>$timeline];
    $report['hash']=hash('sha256',j($report));
    return $report;
}

function careerSavePeriod(array $u, array $b): array
{
    if (!in_array($u['role'],['admin','manager'],true)) throw new Fault('Perfil sem permissão de classificação.',403);
    $p=linkFor($u,$b['linkId']??'');
    if ($p['version']!==($b['linkVersion']??null)) throw new Fault('Matrícula alterada.',409);
    $start=day($b['start']??'');$end=empty($b['end'])?null:day($b['end']);
    if ($start<$p['admission'] || ($end && $end<$start)) throw new Fault('Período inválido.');
    $effect=$b['effect']??'';
    $modality=$b['modality']??'';
    if (!in_array($effect,['count','exclude','reset','pending'],true) ||
        !in_array($modality,['all','horizontal','vertical'],true)) throw new Fault('Classificação inválida.');
    $id=trim((string)($b['id']??''));
    $old=$id?record($u['tenant'],'service_periods',$id):null;
    if ($id && (!$old || $old['link']!==$p['id'])) throw new Fault('Período não encontrado.',404);
    $source=trim((string)($b['source']??''));
    if ($source) {
        $original=null;
        foreach (careerTimeSources($u['tenant'],$p,'horizontal') as $candidate) {
            if ($candidate['source']===$source && !str_starts_with($source,'period:')) $original=$candidate;
        }
        if (!$original) throw new Fault('Ocorrência original não encontrada.');
        // A resolution classifies the whole source interval.
        $start=$original['start'];$end=$original['end'];
        foreach (records($u['tenant'],'service_periods') as $other) {
            if ($other['link']===$p['id'] && $other['source']===$source && $other['id']!==($old['id']??'') &&
                ($other['modality']===$modality || $other['modality']==='all' || $modality==='all')) {
                throw new Fault('Já há classificação para esta ocorrência e modalidade. Edite a existente.',409);
            }
        }
    }
    $history=$old['history']??[];
    if ($old) { $previous=$old;unset($previous['history']);$history[]=$previous; }
    $period=save($u['tenant'],'service_periods',['id'=>$old['id']??uid(),'link'=>$p['id'],
        'source'=>$source,'type'=>need($b['type']??'','tipo da ocorrência',120),'start'=>$start,'end'=>$end,
        'effect'=>$effect,'modality'=>$modality,'basis'=>need($b['basis']??'','fundamento legal',1000),
        'actor'=>$u['id'],'created'=>date(DATE_ATOM),'history'=>$history],$old?(int)($b['version']??0):null);
    event($p,$u,'Período funcional classificado',$period['type'].' · '.$period['effect']);
    updateLink($u,$p,$p['version'],'career.time.classify');
    return ['period'=>$period];
}

function careerRememberEnrollment(array &$p, string $effect, array $u): void
{
    if ($effect < $p['enrollmentDate']) throw new Fault('Efeito anterior ao enquadramento vigente.');
    $p['enrollmentHistory'][] = [
        'from'=>$p['enrollmentDate'],'until'=>careerDate($effect)->modify('-1 day')->format('Y-m-d'),
        'level'=>$p['level'],'stage'=>$p['stage'],'role'=>$p['role'],'workload'=>$p['workload'],
        'planId'=>$p['planId'],'salary'=>$p['salary']??null,'actor'=>$u['id'],'recorded'=>date(DATE_ATOM)
    ];
}

function careerHistoricSalary(array $p, ?array $map, string $at): ?array
{
    if (!array_key_exists('enrollmentHistory',$p) || $at >= $p['enrollmentDate'] || $map !== ($p['salary']??null)) return null;
    foreach (array_reverse($p['enrollmentHistory']) as $position) {
        if ($position['from']<=$at && $at<=$position['until']) {
            $historic=array_replace($p,array_intersect_key($position,array_flip(['level','stage','role','workload','planId','salary'])));
            $historic['enrollmentDate']=$position['from'];
            return ['position'=>$historic,'map'=>$position['salary']];
        }
    }
    throw new Fault('O histórico de enquadramento não alcança a data consultada.');
}
