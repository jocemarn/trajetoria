<?php
declare(strict_types=1);
function employeeRoadmap(string $tenant,array $p):array{
 static $catalogue=null;$catalogue??=json_decode((string)file_get_contents(TR_RELEASE.'/data/career-paths.json'),true);
 $plan=record($tenant,'plans',$p['planId']);$entry=$catalogue[$p['planId']]??null;
 // Regras demonstrativas só correspondem ao mesmo documento cadastrado. Não herdar por família.
 $matched=$entry&&($plan['basis']??$plan['act']??'')===$entry['basis']&&($plan['articles']??[])===$entry['articles'];
 $process=null;foreach($p['processes']??[] as $x)if($x['state']==='Em análise'){$process=$x;break;}
 $paths=$matched?$entry['paths']:[];
 foreach($paths as &$path){if(isset($path['byRole'][$p['role']]))$path=array_replace($path,$path['byRole'][$p['role']]);unset($path['byRole']);}unset($path);
 $scores=[];foreach($p['evaluations']??[] as $e){$score=$e['score']??null;if(!is_numeric($score)||$score<0||$score>100)continue;
  if($process&&preg_match('/^\d{4}$/',(string)($e['period']??''))&&((int)$e['period']<(int)substr($process['start'],0,4)||(int)$e['period']>(int)substr($process['end'],0,4)))continue;
  $scores[]=(float)$score;
 }
 $certs=array_values(array_filter(records($tenant,'certificates'),fn($c)=>$c['link']===$p['id']&&certificateStatus($c)==='Aprovado'));
 if($process)$certs=array_values(array_filter($certs,fn($c)=>$c['completed']>=$process['start']&&$c['completed']<=$process['end']));
 $hours=array_sum(array_map(fn($c)=>($c['kind']??'')==='Capacitação'?(float)$c['hours']:0,$certs));
 $titles=count(array_filter($certs,fn($c)=>($c['kind']??'')==='Titulação'&&(!$process||($c['targetLevel']??null)===$process['targetLevel'])));
 $months=null;if($process){$end=min(today(),$process['end']);if($end>=$process['start']){$diff=(new DateTimeImmutable($process['start']))->diff(new DateTimeImmutable($end));$months=$diff->y*12+$diff->m;}}
 $special=false;foreach($p['paidFunctions']??[] as $a)if(in_array($a['careerEffect'],['unreviewed','suspend','special'])&&(!$process||$a['start']<=$process['end']&&(!$a['end']||$a['end']>=$process['start'])))$special=true;
 return ['plan'=>$plan['name'],'basis'=>$plan['basis']??$plan['act']??'','family'=>$plan['family'],'paths'=>$paths,'configured'=>(bool)$matched,'articles'=>array_values(array_filter($plan['articles']??[],fn($a)=>(int)$a['number']>=16)),
  'score'=>$scores?round(array_sum($scores)/count($scores),2):null,'scoreCount'=>count($scores),'scoreScope'=>$process?'Avaliações registradas abrangidas pelos anos do período aquisitivo; confirmar consolidação.':'Média dos registros disponíveis, sem período aquisitivo definido.',
  'approvedHours'=>$hours,'approvedTitles'=>$titles,'months'=>$months,'process'=>$process?array_intersect_key($process,array_flip(['id','type','start','end','targetLevel','targetStage'])):null,
  'probationConcluded'=>$p['probation']==='Concluído','specialFunction'=>$special,'hasOccurrences'=>count($p['leaves']??[])>0,'effectiveFrom'=>$matched?$entry['from']:null,
  'notice'=>'Roteiro orientativo do documento cadastrado. Horas não são convertidas em pontos. Período computável, média consolidada, certificado já utilizado, pré-requisitos e impedimentos exigem conferência humana. Não constitui habilitação nem concessão.'];
}
