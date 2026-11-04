// Datas do período aquisitivo não representam movimentação do processo.
export function validDay(value) {
  if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}(?:T|$)/.test(value)) return null;
  const day=value.slice(0,10),stamp=Date.parse(day+'T00:00:00Z');
  return Number.isFinite(stamp)&&new Date(stamp).toISOString().slice(0,10)===day?day:null;
}
export function lastMovement(record) {
  const dates=[record.createdDay,record.created,record.createdAt,record.updated,record.updatedAt,record.at,
    ...(record.votes||[]).map(v=>v.at),...(record.history||[]).map(v=>v.at),...(record.activity||[]).map(v=>v.at)].map(validDay).filter(Boolean).sort();
  return dates.at(-1)||null;
}
export function idleDays(record,today) {
  const last=lastMovement(record),now=validDay(today);
  if(!last||!now)return null;
  return Math.max(0,Math.floor((Date.parse(now)-Date.parse(last))/86400000));
}
export function certificateLane(c){
  if(c.status==='Aprovado')return 'approved';
  if(c.status==='Rejeitado')return 'rejected';
  return c.canVote?'mine':'waiting';
}
export function normalizeSearch(value){return String(value||'').normalize('NFD').replace(/\p{Diacritic}/gu,'').toLowerCase();}
export function boardModel({links,certificates,appointments,processActivity=[],person,today,query='',appointment='',threshold=15,onlyOverdue=false}){
  const limit=Math.min(365,Math.max(1,Number(threshold)||15)),needle=normalizeSearch(query);
  const assignment=appointments.find(a=>a.id===appointment);
  const covered=p=>!assignment||(p.planId===assignment.plan&&p.unitId===assignment.unit&&(!assignment.lotation||p.lotation===assignment.lotation));
  const people=links.filter(p=>p.personId!==person&&covered(p));
  const byId=new Map(people.map(p=>[p.id,p]));
  const matches=p=>!needle||normalizeSearch([p.name,p.registration,p.role,p.unit,p.lotation].join(' ')).includes(needle);
  const docs=certificates.filter(c=>byId.has(c.link)&&(!appointment||c.appointment===appointment)).map(c=>{
    const p=byId.get(c.link),days=idleDays(c,today),lane=certificateLane(c);
    return {...c,person:p,lane,days,lastMovement:lastMovement(c),overdue:['mine','waiting'].includes(lane)&&days!==null&&days>=limit};
  }).filter(c=>(matches(c.person)||needle&&normalizeSearch(c.name).includes(needle))&&(!onlyOverdue||c.overdue));
  docs.sort((a,b)=>(b.days??-1)-(a.days??-1)||String(a.name).localeCompare(String(b.name),'pt-BR'));
  const rows=people.filter(matches).map(p=>{
    const pending=(p.processes||[]).filter(r=>r.state==='Em análise').map(r=>{const record={...r,activity:processActivity.filter(e=>e.link===p.id&&e.process===r.id)};return {...r,days:idleDays(record,today),lastMovement:lastMovement(record)};});
    const ownDocs=docs.filter(c=>c.link===p.id),stalled=pending.filter(r=>r.days!==null&&r.days>=limit);
    return {person:p,docs:ownDocs,pending,stalled,overdue:ownDocs.some(c=>c.overdue)||stalled.length>0};
  }).filter(r=>!onlyOverdue||r.overdue);
  return {docs,rows,limit,overdueDocs:docs.filter(c=>c.overdue).length,overdueProcesses:rows.reduce((n,r)=>n+r.stalled.length,0),undatedProcesses:rows.reduce((n,r)=>n+r.pending.filter(p=>p.days===null).length,0)};
}
