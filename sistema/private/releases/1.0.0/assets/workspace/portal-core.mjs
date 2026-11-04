export function ownLinks(links,identity){if(!identity?.tenant||!identity?.personId)return [];return links.filter(p=>p.municipality===identity.tenant&&p.personId===identity.personId)}
export function selectLink(links,identity,id){const p=ownLinks(links,identity).find(p=>String(p.id)===String(id));if(!p)throw Error('Matrícula não vinculada a este servidor neste município.');return p}
export function ownRecords(records,links,identity,linkId){const allowed=new Set(ownLinks(links,identity).map(p=>p.id));return records.filter(r=>r.tenant===identity?.tenant&&allowed.has(r.linkId)&&(linkId===undefined||r.linkId===linkId))}
export function maskCPF(cpf){return String(cpf||'').replace(/\D/g,'').replace(/^(\d{3})\d{6}(\d{2})$/,'$1.***.***-$2')}
