<?php
declare(strict_types=1);
function workflowUpload(array $u):array {
    $t=$u['tenant'];$c=record($t,'workflow_cases',$_GET['caseId']??'');if(!$c)throw new Fault('Tramitação não encontrada.',404);[$p]=workflowAccess($u,$c);
    if(!workflowOwn($u,$p)||in_array($c['state'],['Concluída','Cancelada'],true)||$c['kind']==='diligence'&&($c['state']!=='Aguardando resposta'||!$c['receipt']))throw new Fault('Anexo indisponível nesta etapa.',403);
    if($c['version']!==(int)($_GET['version']??0))throw new Fault('Tramitação alterada. Atualize antes de anexar.',409);
    if(count($c['files'])>=10)throw new Fault('Use até dez PDFs por tramitação.');
    $bytes=file_get_contents('php://input',false,null,0,10485761);if(strlen($bytes)>10485760||!str_starts_with($bytes,'%PDF-')||stripos($bytes,'%%EOF')===false||($_SERVER['CONTENT_TYPE']??'')!=='application/pdf')throw new Fault('Envie PDF válido de até 10 MB.');
    return workflowStorePdf($u,$c,$p,$bytes,need($_GET['fileName']??'comprovante.pdf','nome do PDF',160));
}
function workflowStorePdf(array $u,array $c,array $p,string $bytes,string $name):array {
    $name=basename($name);if(!str_ends_with(mb_strtolower($name),'.pdf'))throw new Fault('Use extensão PDF.');
    $hash=hash('sha256',$bytes);foreach($c['files'] as $fileId){$old=record($u['tenant'],'workflow_files',$fileId);if($old&&$old['hash']===$hash)throw new Fault('Este PDF já está anexado.',409);}
    $id=uid();$path=TR_PRIVATE.'/data/'.$id.'.enc';if(!is_dir(dirname($path)))mkdir(dirname($path),0700,true);if(file_put_contents($path,seal($bytes),LOCK_EX)===false)throw new Fault('Não foi possível guardar o documento.',500);chmod($path,0600);
    try{query('INSERT INTO tr_files(id,tenant,link_id,name,size,hash,created) VALUES(?,?,?,?,?,?,?)',[$id,$u['tenant'],$p['id'],$name,strlen($bytes),$hash,date(DATE_ATOM)]);
        save($u['tenant'],'workflow_files',['id'=>$id,'case'=>$c['id'],'link'=>$p['id'],'name'=>$name,'hash'=>$hash,'size'=>strlen($bytes),'actor'=>$u['id'],'round'=>$c['round'],'created'=>date(DATE_ATOM)]);
        $c['files'][]=$id;save($u['tenant'],'workflow_cases',$c,$c['version']);audit($u['tenant'],'workflow.file.upload',$id);return ['id'=>$id];
    }catch(Throwable $e){@unlink($path);throw $e;}
}
function workflowFile(array $u,string $id):array {
    $file=record($u['tenant'],'workflow_files',$id);$case=$file?record($u['tenant'],'workflow_cases',$file['case']):null;if(!$file||!$case||!in_array($id,$case['files'],true))throw new Fault('Documento não encontrado.',404);
    workflowAccess($u,$case);return [$file,$case];
}
function workflowServePdf(array $u,string $id):never {
    [$file]=workflowFile($u,$id);$path=TR_PRIVATE.'/data/'.$id.'.enc';if(!is_file($path))throw new Fault('Documento indisponível.',404);$bytes=unseal(file_get_contents($path));if(!hash_equals($file['hash'],hash('sha256',$bytes)))throw new Fault('Integridade do documento não confirmada.',500);
    $ticket=save($u['tenant'],'workflow_reads',['id'=>uid(),'file'=>$id,'member'=>$u['id'],'at'=>time()]);audit($u['tenant'],'workflow.file.view',$id);
    header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="comprovante.pdf"');header('X-Document-Read: '.$ticket['id']);header("Content-Security-Policy: sandbox; default-src 'none'");echo $bytes;exit;
}
function workflowReplaceCertificate(array $u,array $c,array $p,array $x,string $fileId,string $readId):void {
    if($c['targetKind']!=='certificate')throw new Fault('Substituição de PDF aplica-se somente ao certificado.');
    $file=record($u['tenant'],'workflow_files',$fileId);$read=record($u['tenant'],'workflow_reads',$readId);
    if(!$file||$file['case']!==$c['id']||!in_array($fileId,$c['files'],true)||!$read||$read['file']!==$fileId||$read['member']!==$u['id']||time()-$read['at']>1800)throw new Fault('Visualize o PDF escolhido nesta plataforma antes de aceitar a substituição.');
    $x['documentHistory'][]=['documentId'=>$x['documentId']??$x['id'],'hash'=>$x['hash'],'reviewers'=>$x['reviewers'],'votes'=>$x['votes'],'case'=>$c['id'],'actor'=>$u['id'],'at'=>date(DATE_ATOM)];
    $a=record($u['tenant'],'appointments',$x['appointment']);if(!$a||!$a['active']||($a['purpose']??'evaluation')!=='evaluation'||$a['unit']!==$p['unitId']||$a['plan']!==$p['planId']||$a['start']>today()||$a['end']<today())throw new Fault('Comissão sem vigência ou escopo para a nova conferência.');
    foreach($a['members'] as $m)if(!$m['memberId']||$m['personId']===$p['personId']||$m['admission']>=$p['admission'])throw new Fault('Confira contas e impedimentos dos titulares.');
    $x['documentId']=$fileId;$x['hash']=$file['hash'];$x['fileName']=$file['name'];$x['size']=$file['size'];$x['reviewers']=$a['members'];$x['votes']=[];$x['reviewRound']=($x['reviewRound']??1)+1;
    // Course, category, hours, completion and original submission date are preserved.
    save($u['tenant'],'certificates',$x,$x['version']);audit($u['tenant'],'workflow.certificate.complement',$x['id']);
}

function workflowResetCertificateVotes(array $u,array $c,array $p,array $x,string $reason):void {
    $a=record($u['tenant'],'appointments',$x['appointment']);if(!$a||!$a['active']||($a['purpose']??'evaluation')!=='evaluation'||$a['unit']!==$p['unitId']||$a['plan']!==$p['planId']||$a['start']>today()||$a['end']<today())throw new Fault('Comissão sem vigência ou escopo para a nova conferência.');
    foreach($a['members'] as $m)if(!$m['memberId']||$m['personId']===$p['personId']||$m['admission']>=$p['admission'])throw new Fault('Confira contas e impedimentos dos titulares.');
    $x['reviewHistory'][]=['reviewers'=>$x['reviewers'],'votes'=>$x['votes'],'case'=>$c['id'],'reason'=>$reason,'actor'=>$u['id'],'at'=>date(DATE_ATOM)];$x['reviewers']=$a['members'];$x['votes']=[];save($u['tenant'],'certificates',$x,$x['version']);
}
