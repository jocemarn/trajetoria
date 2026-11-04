<?php
declare(strict_types=1);
function employeeSalaryPaths(string $tenant,array $p,array $roadmap):array{
 if(!$roadmap['configured']||empty($p['salary']))return [];
 try{$current=salaryQuote($tenant,$p['salary'],$p,today());$job=record($tenant,'salary_jobs',$p['salary']['jobId']);$cell=salaryCell($job['source'],$p['salary']['groupId'],(int)$p['salary']['rowIndex']);}
 catch(Fault $e){return [];}
 $rows=json_decode((string)file_get_contents(TR_RELEASE.'/data/salary/'.basename($cell['group']['data'])),true)['rows'];
 $level=(int)$cell['row'][0];$reference=(int)$cell['row'][1];$band=$cell['row'][2];$out=[];
 foreach($roadmap['paths'] as $path)foreach($rows as $index=>$row){
  if((string)$row[2]!== (string)$band)continue;$candidate=false;
  if($path['type']==='Progressão horizontal'&&isset($path['maxReference']))$candidate=(int)$row[0]===$level&&(int)$row[1]===$reference+1&&(int)$row[1]<=$path['maxReference'];
  if($path['type']==='Promoção vertical'&&isset($path['maxLevel']))$candidate=(int)$row[1]===$reference&&(int)$row[0]>$level&&(int)$row[0]<=$path['maxLevel']&&(($path['policy']??'')==='qualification'||(int)$row[0]===$level+1);
  if(!$candidate)continue;
  $x=['id'=>'preview-'.$path['type'].'-'.$index,'type'=>$path['type'],'targetLevel'=>(string)$row[0],'targetStage'=>(string)$row[1]];
  $map=$p['salary'];$map['rowIndex']=$index;$map['target']=j([$x['id'],$x['targetLevel'],$x['targetStage'],$x['type']]);
  try{$q=salaryQuote($tenant,$map,$p,today(),$x);}catch(Fault $e){continue;}
  $out[]=['type'=>$path['type'],'targetLevel'=>$x['targetLevel'],'targetStage'=>$x['targetStage'],'levelLabel'=>$path['levelNames'][(int)$row[0]-1]??null,'targetBasicCents'=>$q['cents'],'baselineBasicCents'=>$current['cents'],'promotionBasicDeltaCents'=>$q['cents']-$current['cents'],'effect'=>today(),'mode'=>'reference'];
 }
 return $out;
}
