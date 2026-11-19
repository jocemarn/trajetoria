<?php
declare(strict_types=1);
require_once __DIR__.'/people.php';
function staffImportHeaders(string $mode):array {
    if($mode==='servidores')return explode(';','cpf;nome;matricula;unidade_id;plano_id;cargo;jornada;nivel;referencia;posse;enquadramento_data;enquadramento_ato;regime;estagio;lotacao;email;telefone;cep;logradouro;numero;complemento;bairro;cidade;uf');
    if($mode==='historicos')return explode(';','id_externo;matricula;tipo;inicio;fim;descricao;efeito;modalidade;fundamento;nivel;referencia;cargo;jornada;tabela_cargo_id');
    throw new Fault('Tipo de importação inválido.');
}
function staffImportCsv(string $csv,string $mode):array {
    if(strlen($csv)>1048576||!mb_check_encoding($csv,'UTF-8'))throw new Fault('Use CSV UTF-8 de até 1 MB.');
    $csv=preg_replace('/^\xEF\xBB\xBF/','',$csv);$f=fopen('php://temp','r+');fwrite($f,$csv);rewind($f);$head=fgetcsv($f,0,';','"','');
    if($head!==staffImportHeaders($mode))throw new Fault('Cabeçalho diferente do modelo. Use ponto e vírgula e mantenha todas as colunas.');
    $rows=[];$line=1;while(($values=fgetcsv($f,0,';','"',''))!==false){$line++;if($values===[null])continue;if(count($rows)>=500)throw new Fault('Importe até 500 linhas por arquivo.');if(count($values)!==count($head)){$rows[]=['line'=>$line,'error'=>'Quantidade de colunas incorreta.'];continue;}$rows[]=['line'=>$line,'values'=>array_combine($head,array_map('trim',$values))];}fclose($f);if(!$rows)throw new Fault('Arquivo sem registros.');return $rows;
}
function staffImportState(string $tenant):string {
    $state=[];foreach(['links','units','plans','salary_jobs','salary_tables','staff_imports','service_periods'] as $kind)foreach(records($tenant,$kind) as $r)$state[]=[$kind,$r['id'],$r['version']];return hash('sha256',j($state));
}
function staffImportPreview(array $u,array $b):array {
    fullManager($u);$t=$u['tenant'];$mode=$b['mode']??'';$historySeen=[];$csv=need($b['csv']??'','arquivo CSV',1048576);$rows=staffImportCsv($csv,$mode);$links=records($t,'links');$seen=[];$people=[];$historySeen=[];$out=[];
    foreach($links as $p)$people[$p['cpf']]=$p;
    $knownIds=[];foreach(records($t,'staff_imports') as $batch)foreach($batch['externalIds']??[] as $id)$knownIds[]=$id;
    $pending=0;
    foreach($rows as $row){$result=['line'=>$row['line'],'registration'=>$row['values']['matricula']??'','name'=>$row['values']['nome']??''];try{
        if(isset($row['error']))throw new Fault($row['error']);$v=$row['values'];
        if($mode==='servidores'){
            $c=cpf($v['cpf']);$reg=need($v['matricula'],'matrícula',80);if(isset($seen[$reg]))throw new Fault('Matrícula repetida no arquivo.');$seen[$reg]=true;
            foreach($links as $p)if($p['registration']===$reg)throw new Fault('Matrícula já cadastrada. Esta importação não sobrescreve registros.');
            $name=need($v['nome'],'nome',120);if(isset($people[$c])&&mb_strtolower($people[$c]['name'])!==mb_strtolower($name))throw new Fault('O CPF já possui outro nome. Confira a pessoa antes de criar a nova matrícula.');
            $unit=record($t,'units',$v['unidade_id']);$plan=record($t,'plans',$v['plano_id']);if(!$unit||!$plan||($unit['active']??true)===false||($plan['active']??true)===false||$unit['power']!==$plan['power'])throw new Fault('Unidade e plano ativos devem pertencer ao mesmo poder.');
            $start=day($v['posse']);$enrollment=day($v['enquadramento_data']);if($start>today()||$enrollment<$start||$enrollment>today())throw new Fault('Datas de posse ou enquadramento inválidas.');
            $contacts=personContacts(['email'=>$v['email'],'phone'=>$v['telefone'],'postalCode'=>$v['cep'],'street'=>$v['logradouro'],'number'=>$v['numero'],'complement'=>$v['complemento'],'district'=>$v['bairro'],'city'=>$v['cidade'],'state'=>$v['uf']]);
            if(!in_array($v['regime'],['Estatutário','Celetista'])||!in_array($v['estagio'],['Em andamento','Concluído']))throw new Fault('Regime ou estágio inválido.');
            $payload=['cpf'=>$c,'name'=>$name,'registration'=>$reg,'unitId'=>$unit['id'],'planId'=>$plan['id'],'role'=>need($v['cargo'],'cargo',120),'workload'=>num($v['jornada'],1,60,'jornada'),'level'=>need($v['nivel'],'nível',15),'stage'=>need($v['referencia'],'referência',15),'admission'=>$start,'enrollmentDate'=>$enrollment,'enrollmentAct'=>need($v['enquadramento_ato'],'ato',500),'regime'=>$v['regime'],'probation'=>$v['estagio'],'lotation'=>need($v['lotacao'],'lotação',120),'lotationType'=>'Outro local'];
            $people[$c]=['name'=>$name];$payload=array_replace($payload,['email'=>$contacts['email'],'phone'=>$contacts['phone'],'postalCode'=>$v['cep'],'street'=>$v['logradouro'],'number'=>$v['numero'],'complement'=>$v['complemento'],'district'=>$v['bairro'],'city'=>$v['cidade'],'state'=>$v['uf']]);
            $result['warning']='Referência salarial deverá ser vinculada após o cadastro.';$result['payload']=$payload;$pending++;
        }else{
            $id=need($v['id_externo'],'ID externo',100);if(in_array($id,$knownIds,true)||isset($seen[$id]))throw new Fault('ID externo já importado ou repetido.');$seen[$id]=true;
            $p=null;foreach($links as $candidate)if($candidate['registration']===$v['matricula'])$p=$candidate;if(!$p)throw new Fault('Matrícula não encontrada neste município.');
            $start=day($v['inicio']);$end=$v['fim']===''?null:day($v['fim']);if($start<$p['admission']||$start>today()||$end&&$end<$start)throw new Fault('Período histórico inválido.');
            $type=$v['tipo'];if(!in_array($type,['tempo','enquadramento','ocorrencia']))throw new Fault('Tipo deve ser tempo, enquadramento ou ocorrencia.');
            $payload=['externalId'=>$id,'linkId'=>$p['id'],'type'=>$type,'start'=>$start,'end'=>$end,'description'=>need($v['descricao'],'descrição',120),'basis'=>need($v['fundamento'],'fundamento',1000)];
            if($type==='tempo'){
                if(!in_array($v['efeito'],['count','exclude','reset','pending'])||!in_array($v['modalidade'],['all','horizontal','vertical']))throw new Fault('Efeito ou modalidade inválidos.');$payload+=['effect'=>$v['efeito'],'modality'=>$v['modalidade']];
                foreach(records($t,'service_periods') as $period)if($period['link']===$p['id']&&$period['start']===$start&&$period['end']===$end&&$period['type']===$payload['description'])throw new Fault('Período equivalente já cadastrado.');
            }
            if($type==='enquadramento'){
                if(!$end||$end>=$p['enrollmentDate'])throw new Fault('Enquadramento histórico exige fim anterior ao enquadramento atual.');
                $position=['from'=>$start,'until'=>$end,'level'=>need($v['nivel'],'nível',15),'stage'=>need($v['referencia'],'referência',15),'role'=>need($v['cargo'],'cargo',120),'workload'=>num($v['jornada'],1,60,'jornada'),'planId'=>$p['planId'],'salary'=>null,'basis'=>$payload['basis']];
                foreach(array_merge($p['enrollmentHistory']??[],$historySeen[$p['id']]??[]) as $prior)if($prior['from']<=$end&&$start<=$prior['until'])throw new Fault('Enquadramento sobreposto a outro período histórico.');
                $historySeen[$p['id']][]=$position;
                if($v['tabela_cargo_id']!==''){$historic=array_replace($p,$position);$map=['jobId'=>$v['tabela_cargo_id'],'context'=>salaryContextKey($historic),'cellKey'=>hash('sha256',j([mb_strtoupper($position['level']),mb_strtoupper($position['stage'])]))];officialSalaryQuote($t,$map,$historic,$start,null);officialSalaryQuote($t,$map,$historic,$end,null);$position['salary']=$map;}else{$result['warning']='Enquadramento sem tabela oficial: salário histórico permanecerá pendente.';$pending++;}
                $payload['position']=$position;
            }
            $result['name']=$p['name'];$result['payload']=$payload;
        }
        $result['state']='Válido';
    }catch(Fault $e){$result['state']='Inconsistente';$result['error']=$e->getMessage();} $out[]=$result;}
    $state=staffImportState($t);$hash=hash('sha256',$csv);$token=hash_hmac('sha256',j([$t,$mode,$hash,$state,$out]),config()['key']);
    return ['rows'=>$out,'count'=>count($out),'errors'=>count(array_filter($out,fn($r)=>$r['state']==='Inconsistente')),'pending'=>$pending,'token'=>$token,'csvHash'=>$hash,'mode'=>$mode];
}
function staffImportApi(array $u,string $action,string $method,array $b):array {
    fullManager($u);$t=$u['tenant'];
    if($action==='context'&&$method==='GET')return ['batches'=>array_map(fn($r)=>array_diff_key($r,array_flip(['sourceCsv','externalIds'])),records($t,'staff_imports')),'templates'=>['servidores'=>implode(';',staffImportHeaders('servidores')),'historicos'=>implode(';',staffImportHeaders('historicos'))]];
    if($method!=='POST')throw new Fault('Método não permitido.',405);
    $external=[]; $preview=staffImportPreview($u,$b);if($action==='preview')return $preview;if($action!=='commit')throw new Fault('Operação não encontrada.',404);
    if($preview['errors']||($b['confirmed']??false)!==true||!hash_equals($preview['token'],(string)($b['token']??'')))throw new Fault('Corrija as inconsistências e confirme uma prévia atualizada.',409);
    $batchId=uid();$ids=[];$external=[];
    foreach($preview['rows'] as $row){$v=$row['payload'];if($b['mode']==='servidores'){$result=municipalApi($u,['links'],'links','POST',$v);$ids[]=$result['id'];}else{
        $p=record($t,'links',$v['linkId']);$external[]=$v['externalId'];
        if($v['type']==='tempo')careerSavePeriod($u,['linkId'=>$p['id'],'linkVersion'=>$p['version'],'start'=>$v['start'],'end'=>$v['end'],'type'=>$v['description'],'effect'=>$v['effect'],'modality'=>$v['modality'],'basis'=>$v['basis']]);
        else{if($v['type']==='enquadramento'){$p['enrollmentHistory'][]=$v['position']+['actor'=>$u['id'],'recorded'=>date(DATE_ATOM),'import'=>$batchId];usort($p['enrollmentHistory'],fn($a,$b)=>strcmp($a['from'],$b['from']));}
            $p['events'][]=['date'=>$v['start'].'T12:00:00-03:00','title'=>$v['description'],'detail'=>$v['basis'],'actor'=>$u['id'],'import'=>$batchId,'externalId'=>$v['externalId'],'historical'=>true];updateLink($u,$p,$p['version'],'staff.history.import');}
        $ids[]=$p['id'];
    }}
    save($t,'staff_imports',['id'=>$batchId,'mode'=>$b['mode'],'count'=>$preview['count'],'pending'=>$preview['pending'],'csvHash'=>$preview['csvHash'],'sourceCsv'=>$b['csv'],'externalIds'=>$external,'links'=>array_values(array_unique($ids)),'actor'=>$u['id'],'created'=>date(DATE_ATOM)]);audit($t,'staff.import.complete',$batchId);return ['id'=>$batchId,'count'=>$preview['count']];
}
