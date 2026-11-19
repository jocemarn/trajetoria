<?php
declare(strict_types=1);

function officialCsv(string $csv): array
{
    if (strlen($csv) > 1048576 || !mb_check_encoding($csv, 'UTF-8')) {
        throw new Fault('Use um CSV UTF-8 de até 1 MB.');
    }
    $csv = preg_replace('/^\xEF\xBB\xBF/', '', trim($csv));
    $first = strtok($csv, "\r\n");
    $delimiter = substr_count((string)$first, ';') >= 2 ? ';' : ',';
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $csv);
    rewind($stream);
    $header = fgetcsv($stream, 0, $delimiter, '"', '');
    if ($header !== ['nivel', 'referencia', 'salario_base']) {
        fclose($stream);
        throw new Fault('Cabeçalho obrigatório: nivel;referencia;salario_base');
    }
    $cells = [];
    while (($row = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
        if ($row === [null]) continue;
        if (count($row) !== 3 || count($cells) >= 5000) {
            fclose($stream);
            throw new Fault('Confira as três colunas e o limite de 5.000 referências.');
        }
        $level = mb_strtoupper(need($row[0], 'nível', 15));
        $reference = mb_strtoupper(need($row[1], 'referência', 15));
        if (preg_match('/[\x00-\x1f]/', $level.$reference)) throw new Fault('Referência inválida.');
        $key = hash('sha256', j([$level, $reference]));
        if (isset($cells[$key])) throw new Fault('Há nível e referência duplicados no CSV.');
        $value = cents($row[2]);
        if ($value <= 0) throw new Fault('O vencimento base deve ser maior que zero.');
        $cells[$key] = ['key'=>$key, 'level'=>$level, 'reference'=>$reference, 'cents'=>$value];
    }
    fclose($stream);
    if (!$cells) throw new Fault('Informe pelo menos uma referência salarial.');
    return array_values($cells);
}

function officialTables(string $tenant, string $job): array
{
    $tables = array_values(array_filter(records($tenant, 'salary_tables'), fn($r)=>$r['job']===$job));
    usort($tables, fn($a,$b)=>strcmp($a['effective'], $b['effective']));
    return $tables;
}

function officialTableAt(string $tenant, string $job, string $at): array
{
    day($at);
    $selected = null;
    foreach (officialTables($tenant, $job) as $table) {
        if ($table['effective'] <= $at) $selected = $table;
    }
    if (!$selected) throw new Fault('Não há tabela oficial vigente nesta data.');
    return $selected;
}

function officialSalaryQuote(string $tenant, array $map, array $p, string $at, ?array $process): array
{
    $job = record($tenant, 'salary_jobs', $map['jobId'] ?? '');
    $frame = $job ? record($tenant, 'salary_frames', $job['frame']) : null;
    if (!$job || ($job['source'] ?? '') !== 'municipal' || !$frame ||
        $frame['plan'] !== $p['planId'] ||
        mb_strtolower(trim($job['name'])) !== mb_strtolower(trim($p['role'])) ||
        (float)$job['workload'] !== (float)$p['workload'] ||
        ($map['context'] ?? '') !== salaryContextKey($p)) {
        throw new Fault('Revalide cargo, jornada, plano e enquadramento da matrícula.');
    }
    if ($process && ($map['target'] ?? '') !== j([$process['id'],$process['targetLevel'],$process['targetStage'],$process['type']])) {
        throw new Fault('O destino do processo mudou. Revalide a vinculação.');
    }
    $level = mb_strtoupper($process['targetLevel'] ?? $p['level']);
    $reference = mb_strtoupper($process['targetStage'] ?? $p['stage']);
    $key = hash('sha256', j([$level, $reference]));
    if (($map['cellKey'] ?? '') !== $key) throw new Fault('A célula vinculada não corresponde ao enquadramento.');
    $table = officialTableAt($tenant, $job['id'], $at);
    $cell = null;
    foreach ($table['cells'] as $candidate) if ($candidate['key'] === $key) $cell = $candidate;
    if (!$cell) throw new Fault('O enquadramento não consta da tabela vigente.');
    $value = $cell['cents'];
    $changes = records($tenant, 'salary_changes');
    usort($changes, fn($a,$b)=>[$a['effective'],$a['sequence']]<=>[$b['effective'],$b['sequence']]);
    $applied = [];
    foreach ($changes as $change) {
        if ($change['frame'] === $frame['id'] && $change['effective'] >= $table['effective'] &&
            $change['effective'] <= $at && $change['sequence'] > $table['baseSequence'] &&
            ($change['kind'] === 'annual' || $change['job'] === $job['id'])) {
            $value = roundRate($value, 1000000 + $change['rate']);
            $applied[] = ['id'=>$change['id'],'law'=>$change['law'],'effective'=>$change['effective']];
        }
    }
    return ['cents'=>$value,'jobId'=>$job['id'],'frameId'=>$frame['id'],'frameVersion'=>$frame['version'],
        'cargo'=>$job['name'],'reference'=>$level.' / '.$reference,'sourceHash'=>$table['hash'],
        'law'=>$table['law'],'tableId'=>$table['id'],'tableEffective'=>$table['effective'],'adjustments'=>$applied];
}

function officialSalaryApi(array $u, string $action, array $b): array
{
    fullManager($u);
    $tenant = $u['tenant'];
    if ($action === 'official-tables') {
        $job = record($tenant, 'salary_jobs', $b['jobId'] ?? '');
        if (!$job || ($job['source'] ?? '') !== 'municipal') throw new Fault('Tabela não encontrada.',404);
        return ['job'=>$job,'versions'=>officialTables($tenant,$job['id'])];
    }
    if ($action === 'official-map') {
        $p = linkFor($u, $b['linkId'] ?? '');
        if (($b['checked'] ?? false) !== true) throw new Fault('Confirme o enquadramento.');
        $job = record($tenant, 'salary_jobs', $b['jobId'] ?? '');
        if (!$job || ($job['source'] ?? '') !== 'municipal') throw new Fault('Cargo oficial não encontrado.');
        $process = null;
        $index = null;
        if (($b['position'] ?? '') === 'target') {
            foreach ($p['processes'] as $i=>$x) {
                if ($x['id'] === ($b['processId'] ?? '') && $x['state'] === 'Em análise') {
                    $process = $x; $index = $i;
                }
            }
            if (!$process) throw new Fault('Selecione um processo em análise.');
        } elseif (($b['position'] ?? '') !== 'current') throw new Fault('Posição inválida.');
        $at = $process ? day($b['effect'] ?? '') : today();
        $level = mb_strtoupper($process['targetLevel'] ?? $p['level']);
        $reference = mb_strtoupper($process['targetStage'] ?? $p['stage']);
        $map = ['jobId'=>$job['id'],'groupId'=>$job['id'],'cellKey'=>hash('sha256',j([$level,$reference])),
            'context'=>salaryContextKey($p),'target'=>$process?j([$process['id'],$process['targetLevel'],$process['targetStage'],$process['type']]):null,
            'forecastDate'=>$process?$at:null,'basis'=>need($b['basis'] ?? '', 'fundamento da vinculação',1000),'actor'=>$u['id']];
        $quote = officialSalaryQuote($tenant,$map,$p,$at,$process);
        if ($process) {
            $current = salaryQuote($tenant,$p['salary'] ?? null,$p,$at);
            if ($quote['cents'] < $current['cents']) throw new Fault('O destino reduz o vencimento base vigente.');
            $p['processes'][$index]['salaryTarget'] = $map;
        } else $p['salary'] = $map;
        updateLink($u,$p,(int)($b['version']??0),'salary.official.mapping');
        return ['quote'=>$quote];
    }
    if (!in_array($action,['official-preview','official-import'],true)) throw new Fault('Operação não encontrada.',404);
    $frame = record($tenant,'salary_frames',$b['frameId']??'');
    if (!$frame || $frame['version'] !== ($b['version']??null)) throw new Fault('Quadro alterado. Recarregue.',409);
    $jobId = trim((string)($b['jobId']??''));
    $job = $jobId ? record($tenant,'salary_jobs',$jobId) : null;
    if ($jobId && (!$job || $job['frame']!==$frame['id'] || ($job['source']??'')!=='municipal')) {
        throw new Fault('Cargo não encontrado neste quadro.');
    }
    $name = need($b['name']??'', 'cargo',120);
    $workload = num($b['workload']??'',1,60,'jornada');
    if ($job && ($job['name']!==$name || (float)$job['workload']!==$workload)) throw new Fault('Use o mesmo cargo e jornada na nova vigência.');
    if (!$job) foreach (records($tenant,'salary_jobs') as $other) {
        if ($other['frame']===$frame['id'] && mb_strtolower($other['name'])===mb_strtolower($name) && (float)$other['workload']===$workload) {
            throw new Fault('Este cargo e jornada já existem. Selecione nova vigência.',409);
        }
    }
    $effective = day($b['effective']??'');
    $law = need($b['law']??'','lei, artigo e publicação',1000);
    $sequence = (int)num($b['baseSequence']??0,0,$frame['version'],'versão já incorporada');
    if ($job) foreach (officialTables($tenant,$jobId) as $old) {
        if ($old['effective']===$effective) throw new Fault('Já existe uma tabela nessa data. O histórico é preservado.',409);
    }
    foreach (records($tenant,'salary_changes') as $change) {
        if ($change['frame']===$frame['id'] && $change['sequence']<=$sequence && $change['effective']>$effective) {
            throw new Fault('A versão incorporada inclui reajuste posterior à vigência.');
        }
    }
    $cells = officialCsv(need($b['csv']??'','CSV',1048576));
    $canonical = ['frame'=>$frame['id'],'frameVersion'=>$frame['version'],'job'=>$jobId,'name'=>$name,
        'workload'=>$workload,'effective'=>$effective,'law'=>$law,'baseSequence'=>$sequence,'cells'=>$cells,'csvHash'=>hash('sha256',$b['csv'])];
    $token = hash_hmac('sha256',j([$tenant,$canonical]),config()['key']);
    if ($action==='official-preview') return ['preview'=>$canonical,'token'=>$token,'count'=>count($cells)];
    if (($b['checked']??false)!==true || !hash_equals($token,(string)($b['token']??''))) {
        throw new Fault('Confira a prévia atual antes de importar.',409);
    }
    if (!$job) {
        $job = save($tenant,'salary_jobs',['id'=>uid(),'frame'=>$frame['id'],'name'=>$name,'workload'=>$workload,
            'source'=>'municipal','source_hash'=>'municipal','groups'=>['ids'=>[],'baseSequence'=>0],
            'base_from'=>$effective,'law'=>$law]);
    }
    $table = save($tenant,'salary_tables',['id'=>uid(),'job'=>$job['id'],'frame'=>$frame['id'],
        'effective'=>$effective,'law'=>$law,'baseSequence'=>$sequence,'cells'=>$cells,
        'hash'=>hash('sha256',j($canonical)),'csvHash'=>$canonical['csvHash'],'sourceCsv'=>$b['csv'],'actor'=>$u['id'],'created'=>date(DATE_ATOM)]);
    if ($effective < $job['base_from']) {
        $job['base_from']=$effective;$job['law']=$law;
        save($tenant,'salary_jobs',$job,$job['version']);
    }
    save($tenant,'salary_frames',$frame,$frame['version']);
    audit($tenant,'salary.official.import',$table['id']);
    return ['jobId'=>$job['id'],'tableId'=>$table['id'],'count'=>count($cells)];
}
