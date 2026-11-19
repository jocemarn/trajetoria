<?php
declare(strict_types=1);
function registryApi(array $u,string $action,string $method,array $b):array {
    fullManager($u);if($method!=='POST')throw new Fault('Método não permitido.',405);$t=$u['tenant'];$kind=$b['kind']??'';
    if(!in_array($kind,['units','plans','links'],true))throw new Fault('Cadastro não suportado.');$r=record($t,$kind,$b['id']??'');if(!$r||$r['version']!==($b['version']??null))throw new Fault('Cadastro alterado. Atualize a lista.',409);
    $old=$r;unset($old['registryHistory']);$reason=need($b['reason']??'','motivo da alteração',1000);
    if($action==='edit'){
        if(!in_array($kind,['units','plans']))throw new Fault('Use o cadastro funcional para alterar uma matrícula.');$name=need($b['name']??'','nome',120);foreach(records($t,$kind) as $other)if($other['id']!==$r['id']&&mb_strtolower($other['name'])===mb_strtolower($name))throw new Fault('Nome já cadastrado.');$r['name']=$name;if($kind==='plans')$r['basis']=need($b['basis']??'','fundamento',1000);
    }elseif(in_array($action,['deactivate','activate','delete'],true)){
        if($action==='activate'&&($r['deleted']??false))throw new Fault('Cadastro excluído logicamente; o histórico está preservado.');
        if($action!=='activate'&&in_array($kind,['units','plans'])){
            foreach(records($t,'links') as $link)if(($link[$kind==='units'?'unitId':'planId']??'')===$r['id']&&($link['active']??true)&&!($link['deleted']??false))throw new Fault('Há matrículas ativas associadas. Regularize os vínculos antes de inativar este cadastro.');
            foreach(records($t,'appointments') as $a)if($a[$kind==='units'?'unit':'plan']===$r['id']&&$a['active'])throw new Fault('Há comissão ativa associada. Encerre-a antes de inativar este cadastro.');
        }
        if($kind==='links'){
            foreach($r['processes']??[] as $x)if(in_array($x['state'],['Em análise','Aprovado · efeito futuro'],true))throw new Fault('Conclua os processos pendentes antes de inativar a matrícula.');
            foreach(records($t,'appointments') as $a)if($a['active'])foreach(array_merge($a['members'],$a['substitutes']??[]) as $m)if($m['linkId']===$r['id'])throw new Fault('Servidor compõe comissão ativa. Substitua-o antes da inativação.');
        }
        $r['active']=$action==='activate';if($action==='delete'){$r['deleted']=true;$r['deletedAt']=date(DATE_ATOM);}
    }else throw new Fault('Ação não encontrada.',404);
    $r['registryHistory'][]=['previous'=>$old,'reason'=>$reason,'action'=>$action,'actor'=>$u['id'],'at'=>date(DATE_ATOM)];save($t,$kind,$r,$r['version']);audit($t,'registry.'.$kind.'.'.$action,$r['id']);return ['ok'=>true];
}
