let registryServerColumns={},registryServerTenant=null;
let registryServerColumns={},registryServerTenant=null;
/* Filtros por coluna no cabeçalho, paginação e ações acessíveis. */
function registryNormalize(value){return String(value??'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('pt-BR');}
function registryPrepareList(table){
 if(table.dataset.listReady||table.closest('#stage-print,#stage-model-factors')||table.classList.contains('salary-grid'))return;
 const head=table.tHead?.rows[0],body=table.tBodies[0];if(!head||!body||head.cells.length<2)return;
 table.dataset.listReady='1';table.classList.add('registry-list');
 const heads=[...head.cells],last=heads.at(-1);if(!last.textContent.trim()&&body.querySelector('tr td:last-child button'))last.textContent='Ações';
 const filter=document.createElement('tr');filter.className='registry-column-filters';
 heads.forEach((th,i)=>{const cell=document.createElement('th');const title=th.textContent.trim();if(!/^aç(ão|ões)$/i.test(title)){
  const input=document.createElement('input');input.type='search';input.placeholder='Filtrar '+title;input.setAttribute('aria-label','Filtrar por '+title);input.dataset.column=i;input.oninput=()=>{if(table.closest('#server-results')){registryServerColumns[i]=input.value;serverFilters.page=1;drawServerRows();const updated=document.querySelector('#server-results table');registryPrepareList(updated);const focus=updated.querySelector('[data-column="'+i+'"]');focus?.focus();return;}page=1;apply();};if(table.closest('#server-results'))input.value=registryServerColumns[i]||'';cell.append(input);
 }filter.append(cell);});table.tHead.append(filter);
 // Servidores já possuem paginação e filtros próprios; os filtros de coluna afetam a página apresentada.
 const external=!!table.closest('#server-results'),pager=document.createElement('div');pager.className='registry-pager';
 let page=1,size=20;const previous=document.createElement('button'),next=document.createElement('button'),count=document.createElement('span'),limit=document.createElement('select');
 previous.type=next.type='button';previous.textContent='Anterior';next.textContent='Próxima';previous.className=next.className='btn secondary';
 for(const n of [20,50,100]){const o=document.createElement('option');o.value=n;o.textContent=n+' por página';limit.append(o);}limit.setAttribute('aria-label','Registros por página');limit.onchange=()=>{size=Number(limit.value);page=1;apply();};previous.onclick=()=>{page--;apply();};next.onclick=()=>{page++;apply();};
 if(!external){pager.append(count,limit,previous,next);table.parentElement.insertBefore(pager,table.nextSibling);}
 function apply(){const filters=[...filter.querySelectorAll('input')],rows=[...body.rows].filter(r=>r.cells.length===heads.length),matching=rows.filter(row=>filters.every(input=>registryNormalize(row.cells[Number(input.dataset.column)]?.textContent).includes(registryNormalize(input.value))));const pages=Math.max(1,Math.ceil(matching.length/size));page=Math.max(1,Math.min(page,pages));const visible=new Set(external?matching:matching.slice((page-1)*size,page*size));rows.forEach(row=>{row.hidden=!visible.has(row);});count.textContent=matching.length+' registro(s) · página '+page+' de '+pages;previous.disabled=page<=1;next.disabled=page>=pages;}
 if(!external)apply();standardizeRegistryActions();
}
let registryListScheduled=false;
new MutationObserver(()=>{if(registryListScheduled)return;registryListScheduled=true;queueMicrotask(()=>{registryListScheduled=false;document.querySelectorAll('#content table,#modal-content table').forEach(registryPrepareList);});}).observe(document.body,{childList:true,subtree:true});
document.querySelectorAll('#content table,#modal-content table').forEach(registryPrepareList);
const stagesDrawServers=drawServerRows;
drawServerRows=()=>{if(registryServerTenant!==tenant()){registryServerTenant=tenant();registryServerColumns={};}stagesDrawServers();const table=$('#server-results table');if(!table)return;const head=table.tHead.rows[0];head.insertCell().outerHTML='<th>Ações</th>';
 [...table.tBodies[0].rows].forEach(row=>{const trigger=row.querySelector('button.row-link');if(!trigger){row.firstElementChild.colSpan=7;return;}const match=trigger.getAttribute('onclick').match(/openPerson\('([^']+)'\)/);if(!match)return;const id=match[1],p=workspace.links.find(p=>p.id===id),cell=row.insertCell();cell.innerHTML='<div class="function-actions">'+stageAction('Consultar matrícula','view','openPerson('+JSON.stringify(id)+')')+(workspace.permissions.admin&&!p.deleted?stageAction('Editar contato e endereço','edit','stageEditContact('+JSON.stringify(id)+')')+stageAction(p.active===false?'Reativar matrícula':'Inativar matrícula','power','stageRegistryAction("links",'+JSON.stringify(id)+','+JSON.stringify(p.active===false?'activate':'deactivate')+')')+stageAction('Excluir preservando histórico','delete','stageRegistryAction("links",'+JSON.stringify(id)+',"delete")'):'')+'</div>';if(p.deleted||p.active===false)row.cells[5].textContent=p.deleted?'Excluído':'Inativo';});};
function stageEditContact(id){openPerson(id);editPersonContacts();}
const stagesOldOperation=operation;
operation=type=>{if(type==='decision'&&!session.isDemo){go('Processos de evolução');return;}stagesOldOperation(type);};

const registryServerFilterBase=filterServerLinks;
filterServerLinks=(links,f)=>registryServerFilterBase(links,f).filter(p=>{const values=[p.name+" "+p.registration,p.role,(workspace.units.find(u=>u.id===p.unitId)?.name||p.unit||"")+" "+(p.lotation||""),workspace.plans.find(pl=>pl.id===p.planId)?.name||"",p.level+" / "+p.stage,p.deleted?"Excluído":p.active===false?"Inativo":p.status];return Object.entries(registryServerColumns).every(([i,v])=>registryNormalize(values[Number(i)]).includes(registryNormalize(v)));});

const registryClearServers=clearServerFilters;
clearServerFilters=()=>{registryServerColumns={};registryClearServers();};

const registryClearServerFilters=clearServerFilters;clearServerFilters=()=>{registryServerColumns={};registryClearServerFilters();};
