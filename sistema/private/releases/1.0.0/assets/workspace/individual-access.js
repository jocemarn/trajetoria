const planFamilyName=value=>{if(value&&typeof value==='object'&&value.family)return value.family;const n=String(value&&typeof value==='object'?value.name:value||'').normalize('NFD').replace(/\p{Diacritic}/gu,'').toLowerCase();return n.includes('magister')?'magisterio':n.includes('legislativ')||n.includes('camara')?'legislativo':n.includes('geral')?'geral':'proprio'};
let individualContext = null, annualOverview = null, rankedRows = [], rankedCriteria = [], rankedOrdinance = null;
const realToday = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Sao_Paulo', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
const roleNames = { admin: 'Gestor Municipal', manager: 'Gestor de unidade', viewer: 'Consulta', employee: 'Servidor', commission: 'Membro de comissão', revoked: 'Revogado' };
const accessBaseRender = render, accessBaseProfile = renderPerson;
render = () => {
    accessBaseRender();
    if (!session)
        return;
    if (session.platformAdmin)
        return;
    const role = workspace.permissions.role || session.role, individual = ['employee', 'commission'].includes(role);
    if (individual) {
        $('#profile-label').textContent = roleNames[role];
        $('#nav').innerHTML = ['Minha Trajetória', ...(role === 'commission' ? ['Minha comissão'] : [])].map(n => `<button onclick="go('${n}')" class="${view === n ? 'active' : ''}">${n}</button>`).join('');
        if (!selected && !['Minha Trajetória', 'Minha comissão', 'Meu perfil'].includes(view))
            view = 'Minha Trajetória';
        if (!selected && view === 'Minha Trajetória') {
            $('#content').innerHTML = header('Minha Trajetória', 'Acesso individual às matrículas associadas à sua conta.') + workspace.links.filter(p=>p.personId===session.person).map(p => `<section class="card pad"><h2>${esc(p.name)} · Matrícula ${esc(p.registration)}</h2><p>${esc(p.role)} · ${esc(workspace.units.find(u => u.id === p.unitId)?.name)}<br>${esc(p.lotation || '')}<br>Nível ${esc(p.level)} · Referência ${esc(p.stage)}</p><button class="btn" onclick="openPerson('${p.id}')">Consultar carreira</button> <button class="btn secondary" onclick="sendIndividualCertificate('${p.id}')">Enviar certificado PDF</button> <button class="btn secondary" onclick="requestIndividualProgression('${p.id}')">Solicitar progressão</button></section>`).join('') + '<section class="card pad"><h2>Certificados e solicitações</h2><div id="individual-certificates">Carregando…</div></section>';
            loadIndividualCertificates();
        }
    }
    else if (tenant()) {
        const nav = $('#nav');
        if (workspace.permissions.admin)
            for (const name of ['Comissões de avaliação', 'Progressão anual'])
                nav.insertAdjacentHTML('beforeend', `<button onclick="go('${name}')" class="${view === name ? 'active' : ''}">${name}</button>`);
        else
            nav.insertAdjacentHTML('beforeend', `<button onclick="go('Comissões de avaliação')">Comissões de avaliação</button>`);
    }
    if (view === 'Minha comissão' || view === 'Comissões de avaliação' && !workspace.permissions.admin)
        drawIndividualCommissions();
    if (view === 'Progressão anual' && workspace.permissions.admin)
        drawAnnual();
    if (view === 'Usuários e acessos' && workspace.permissions.admin) {
        $('#content').innerHTML = header('Usuários e acessos', 'A conta autenticada é associada ao município e, quando aplicável, à pessoa do servidor.', button('+ Configurar acesso', 'newMember()')) + `<section class="card pad"><p>Servidor: somente suas matrículas. Comissão: suas matrículas e documentos designados durante o mandato. Revogação aplicada em cada consulta e operação.</p><table><thead><tr><th>Conta</th><th>Perfil</th><th>Associação</th><th>Situação</th><th></th></tr></thead><tbody>${workspace.members.map(m => `<tr><td>${esc(m.email)}</td><td>${esc(roleNames[m.role])}</td><td>${esc(workspace.links.find(l => l.personId === m.person)?.name || workspace.units.find(u => u.id === m.unit)?.name || m.power || 'Município')}</td><td>${m.role === 'revoked' ? 'Revogado' : m.subject ? 'Conta vinculada' : 'Aguardando primeiro acesso'}</td><td>${m.email !== session.user.email && m.role !== 'revoked' ? `<button class="text-btn" onclick="revoke('${m.id}')">Revogar</button>` : ''}</td></tr>`).join('')}</tbody></table></section>`;
    }
};
newMember = () => { const people = [...new Map(workspace.links.map(l => [l.personId, l])).values()]; form('Configurar acesso individual', field('E-mail da nova conta', 'email', 'email') + select('Perfil', 'role', Object.entries(roleNames).filter(([r]) => r !== 'revoked')) + select('Servidor associado (para Servidor e Comissão)', 'personId', [['', 'Selecione'], ...people.map(p => [p.personId, p.name + ' · CPF ***.' + (p.cpf || '').slice(3, 6) + '.***-**'])]) + select('Poder (gestão ou consulta)', 'power', [['', 'Todo o município'], 'Executivo', 'Legislativo']) + select('Unidade (gestão ou consulta)', 'unit', [['', 'Todas no escopo'], ...workspace.units.map(u => [u.id, u.name])]) + '<p>Uma senha temporária será gerada. O primeiro acesso exige a troca da senha. O CPF identifica a pessoa e não substitui a senha.</p>', 'Salvar acesso', async (b) => { const result=await api(endpoint('members'), 'POST', b); await load();if(result.temporaryPassword)showTemporaryPassword(result); }); };
renderPerson = () => { accessBaseProfile(); if (workspace.permissions.admin) {
    const p = selected;
    if (activeTab === 'Processos' && planFamilyName(workspace.plans.find(x => x.id === p.planId)) === 'geral')
        for (const process of p.processes.filter(x => x.state === 'Em análise'))
            $('#content').insertAdjacentHTML('beforeend', `<section class="card pad"><h3>Habilitação anual · ${esc(process.type)}</h3><p>Analise as exigências do plano antes de incluir esta matrícula na classificação.</p><button class="btn" onclick="qualifyIndividual('${process.id}')">Conferir habilitação anual</button></section>`);
    for (const process of p.processes.filter(x => x.state === 'Aprovado · efeito futuro'))
        $('#content').insertAdjacentHTML('beforeend', `<section class="card pad"><h3>Concessão para o próximo exercício</h3><p>${esc(process.act)} · Efeito ${fmt(process.effect)}</p><button class="btn" ${process.effect > realToday() ? 'disabled' : ''} onclick="activateAnnual('${process.id}')">Aplicar efeito da portaria</button></section>`);
} if (['employee', 'commission'].includes(workspace.permissions.role)&&selected.personId===session.person)
    $('#content').insertAdjacentHTML('beforeend', `<section class="card pad"><button class="btn secondary" onclick="sendIndividualCertificate('${selected.id}')">Enviar certificado para esta matrícula</button></section>`); };
async function accessContext() { return individualContext = await api(endpoint('access/context')); }
function certificateCards(rows) { return rows.map(c => `<article class="card pad"><h3>${esc(c.name)} ${status(c.status)}</h3><p>${esc(c.employee)} · Matrícula ${esc(c.registration)}<br>${esc(c.category)} · ${esc(c.destination)}<br>${c.hours} horas · ${c.votes.length}/${c.reviewers.length} pareceres</p><button class="btn secondary" onclick="individualPDF('${c.id}')">Visualizar PDF${c.canVote ? ' e registrar parecer' : ''}</button></article>`).join('') || '<p>Nenhum certificado autorizado neste acesso.</p>'; }
async function loadIndividualCertificates() { try {
    const d = await accessContext();
    if ($('#individual-certificates'))
        $('#individual-certificates').innerHTML = certificateCards(d.certificates.filter(c=>workspace.links.some(p=>p.id===c.link&&p.personId===session.person)));
}
catch (e) {
    if ($('#individual-certificates'))
        $('#individual-certificates').textContent = e.message;
} }
async function drawIndividualCommissions() { const v = view; $('#content').innerHTML = header(view, 'Nomeações com escopo, mandato e membros identificados.') + '<div id="individual-commissions">Carregando…</div>'; try {
    const d = await accessContext();
    if (view !== v)
        return;
    $('#individual-commissions').innerHTML = (workspace.permissions.admin ? button('+ Nomear comissão', 'newIndividualCommission()') : '') + d.appointments.map(a => `<section class="card pad"><h2>${esc(a.name)} ${a.active ? '' : '· Desativada'}</h2><p>${esc(a.act)} · ${fmt(a.start)} a ${fmt(a.end)}<br>${esc(workspace.units.find(u => u.id === a.unit)?.name || 'Unidade designada')} · ${esc(a.lotation || 'Todos os locais da unidade')}</p><p>${a.members.map(m => esc(m.name)).join(' · ')}</p>${workspace.permissions.admin && a.active ? `<button class="text-btn" onclick="deactivateAppointment('${a.id}')">Encerrar atuação desta comissão</button>` : ''}</section>`).join('') + (workspace.permissions.role==='commission'?'<h2>Carreiras sob avaliação</h2>'+workspace.links.filter(p=>p.personId!==session.person).map(p=>`<button class="btn secondary" onclick="openPerson('${p.id}')">${esc(p.name)} · ${esc(p.registration)}</button>`).join(''):'') + '<h2>Documentos autorizados</h2>' + certificateCards(d.certificates);
}
catch (e) {
    $('#content').innerHTML = header(view, e.message);
} }
async function deactivateAppointment(id) { form('Encerrar atuação da comissão', '<p>A comissão e seus pareceres serão preservados no histórico. Novos acessos de revisão e pareceres ficarão bloqueados.</p>', 'Encerrar atuação', async () => { await api(endpoint('access/appointments/' + id), 'DELETE', {}); render(); }); }
async function newIndividualCommission() { const d = await accessContext(), candidates = d.candidates; if (!candidates.length) {
    toast('Associe contas de membros de comissão a servidores cadastrados primeiro.');
    return;
} form('Nomear comissão', field('Nome da comissão', 'name') + field('Ato de nomeação e fundamento legal', 'act') + select('Plano', 'plan', workspace.plans.map(p => [p.id, p.name])) + select('Unidade gestora', 'unit', workspace.units.map(u => [u.id, u.name])) + field('Local de lotação específico (opcional)', 'lotation', 'text', '', 'maxlength="120"') + field('Início do mandato', 'start', 'date') + field('Fim do mandato', 'end', 'date') + '<p>Todos os titulares deverão conferir os documentos. Informe representação e origem da indicação conforme a legislação própria.</p>' + candidates.map((m, i) => { const links = workspace.links.filter(l => l.personId === m.person); return `<section class="card pad"><label><input type="checkbox" name="member${i}" value="${esc(m.id)}"> ${esc(links[0]?.name || m.email)}</label>` + select('Matrícula de carreira do membro', 'link' + i, links.map(l => [l.id, l.registration + ' · ' + l.role])) + select('Representação', 'representation' + i, ['Administração', 'Sindicato', 'Assembleia']) + field('Origem da indicação / ata / documento', 'indication' + i, 'text', '', 'maxlength="240"') + '</section>'; }).join(''), 'Salvar nomeação', async (b, f) => { b.members = candidates.flatMap((m, i) => f.elements['member' + i].checked ? [{ memberId: m.id, linkId: b['link' + i], representation: b['representation' + i], indication: b['indication' + i] }] : []); await api(endpoint('access/appointments'), 'POST', b); render(); }); }
async function sendIndividualCertificate(id) { const p = workspace.links.find(l => l.id === id); if (!p)
    return; const cycle = await import('./annual-cycle.mjs'); const categoryEngine = await import('./certificate-categories.mjs'); const c = cycle.annualCycle(), family = planFamilyName(workspace.plans.find(x => x.id === p.planId)), categories = categoryEngine.categoriesFor({ ...p, plan: family === 'magisterio' ? 'Magistério' : family === 'geral' ? 'Quadro geral' : 'Legislativo' }); if (!categories.length) {
    toast('Cadastre e valide as categorias próprias deste plano antes do envio.');
    return;
} form('Enviar certificado · matrícula ' + p.registration, (family === 'geral' ? `<p>Fechamento: ${fmt(c.cutoff)} · Progressão no exercício ${c.effectYear}. O envio será datado pelo servidor, sem possibilidade de retroagir.</p>` : '<p>Este plano possui regras próprias. O calendário do Quadro Geral não é aplicado automaticamente; a comissão deve conferir os prazos e requisitos do plano desta matrícula.</p>') + field('Nome do curso / título', 'name') + select('Categoria aceita pelo plano', 'categoryId', categories.map(c => [c.id, c.label])) + field('Nível pretendido (titulação)', 'targetLevel', 'text', '', 'maxlength="15"') + field('Progressão de destino', 'destinationDisplay', 'text', categories[0].destination, 'readonly') + '<p id="individual-category-details"></p>' + field('Horas', 'hours', 'number', '', 'required min="1" max="10000"') + field('Conclusão', 'completed', 'date') + '<label>Certificado PDF (até 10 MB)<input type="file" name="pdf" accept="application/pdf" required></label>', 'Enviar para conferência', async (b, f) => { const file = f.elements.pdf.files[0], params = new URLSearchParams({ tenant: tenant(), linkId: id, name: b.name, category: categories.find(c => c.id === b.categoryId).label, categoryId: b.categoryId, targetLevel: b.targetLevel, destination: categories.find(c => c.id === b.categoryId).destination, hours: b.hours, completed: b.completed, fileName: file.name }); const response = await fetch('/api/access/certificates?' + params, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/pdf' }, body: file }); const data = await response.json(); if (!response.ok)
    throw Error(data.error); render(); }); const syncCategory = () => { const f = $('#form'), c = categories.find(c => c.id === f.elements.categoryId.value); f.elements.destinationDisplay.value = c.destination; f.elements.targetLevel.required = c.kind === 'Titulação'; f.elements.targetLevel.readOnly = !!c.targetLevel; f.elements.targetLevel.value = c.targetLevel || ''; f.elements.hours.min = c.minHours || 0; $('#individual-category-details').textContent = c.basis + ' ' + c.requirements; }; $('#form').elements.categoryId.onchange = syncCategory; syncCategory(); }
const individualPDFDialog = document.createElement('dialog');
individualPDFDialog.id = 'individual-pdf';
document.body.append(individualPDFDialog);
let individualPDFDoc, individualPDFTask, individualPDFGeneration = 0, individualPDFPage = 1, individualPDFPaint, individualReadId;
individualPDFDialog.addEventListener('close', () => { individualPDFGeneration++; individualPDFPaint?.cancel(); individualPDFDoc?.destroy(); individualPDFTask?.destroy(); individualPDFDoc = null; individualReadId = null; individualPDFDialog.innerHTML = ''; render(); });
async function individualPDF(id) { const d = await accessContext(), c = d.certificates.find(c => c.id === id); if (!c)
    return; individualPDFPage = 1; individualReadId = null; const gen = ++individualPDFGeneration; individualPDFDialog.innerHTML = `<div class="page-title"><h2>${esc(c.name)}</h2><button class="btn secondary" onclick="individualPDFDialog.close()">Fechar visualização</button></div><p>${esc(c.employee)} · Matrícula ${esc(c.registration)} · ${esc(c.destination)}</p><div class="links"><button id="real-pdf-prev" class="btn secondary">← Página</button><span id="real-pdf-pages"></span><button id="real-pdf-next" class="btn secondary">Página →</button></div><p id="real-pdf-status" role="status">Carregando documento protegido…</p><div class="real-pdf-layout"><section><div id="real-pdf-wrap"><canvas id="real-pdf-canvas"></canvas></div><details><summary>Texto acessível da página</summary><p id="real-pdf-text"></p></details></section><section class="card pad" id="real-pdf-review"><h3>Parecer da comissão</h3>${c.votes.map(v => '<p>' + esc(c.reviewers.find(m => m.memberId === v.member)?.name) + ' · ' + esc(v.decision) + '<br>' + esc(v.reason) + '</p>').join('')}${c.canVote ? '<form id="real-pdf-vote">' + select('Decisão', 'decision', [['', 'Selecione'], 'Aprovar', 'Rejeitar']) + '<label>Parecer fundamentado<textarea name="reason" maxlength="2000" required></textarea></label><label><input type="checkbox" name="verified" required> Conferi o PDF e sua aplicação ao plano e à matrícula.</label><p id="real-pdf-error" class="form-error" role="alert"></p><button class="btn" disabled>Registrar meu parecer</button></form>' : '<p>Consulta do documento e dos pareceres autorizados.</p>'}</section></div>`; individualPDFDialog.showModal(); try {
    const response = await fetch('/api/access/certificates/' + id + '/file?tenant=' + encodeURIComponent(tenant()), { credentials: 'same-origin', cache: 'no-store' });
    if (!response.ok)
        throw Error((await response.json()).error);
    const ticket = response.headers.get('X-Document-Read'), bytes = new Uint8Array(await response.arrayBuffer()), pdf = await import('/pdfjs/pdf.min.mjs');
    pdf.GlobalWorkerOptions.workerSrc = '/pdfjs/pdf.worker.min.mjs';
    if (gen !== individualPDFGeneration)
        return;
    individualPDFTask = pdf.getDocument({ data: bytes, cMapUrl: '/pdfjs/cmaps/', cMapPacked: true, standardFontDataUrl: '/pdfjs/standard_fonts/', wasmUrl: '/pdfjs/wasm/', isEvalSupported: false, enableXfa: false, maxImageSize: 20000000 });
    individualPDFDoc = await individualPDFTask.promise;
    if (gen !== individualPDFGeneration)
        return;
    await paintIndividualPDF();
    if (gen !== individualPDFGeneration)
        return;
    individualReadId = ticket;
    if ($('#real-pdf-vote button'))
        $('#real-pdf-vote button').disabled = false;
    $('#real-pdf-prev').onclick = async () => { if (individualPDFPage > 1) {
        individualPDFPage--;
        await paintIndividualPDF();
    } };
    $('#real-pdf-next').onclick = async () => { if (individualPDFPage < individualPDFDoc.numPages) {
        individualPDFPage++;
        await paintIndividualPDF();
    } };
    if ($('#real-pdf-vote'))
        $('#real-pdf-vote').onsubmit = async (e) => { e.preventDefault(); const f = e.target, button = f.querySelector('button'); button.disabled = true; try {
            if (!individualReadId)
                throw Error('Aguarde a exibição do PDF.');
            await api(endpoint('access/certificates/' + id + '/vote'), 'POST', { decision: f.elements.decision.value, reason: f.elements.reason.value, verified: f.elements.verified.checked, readId: individualReadId });
            $('#real-pdf-review').innerHTML = '<h3>Parecer registrado</h3><p>Seu parecer está salvo. O resultado depende da conferência de todos os membros.</p>';
            toast('Parecer salvo no servidor.');
        }
        catch (e) {
            $('#real-pdf-error').textContent = e.message;
            button.disabled = false;
        } };
}
catch (e) {
    if (gen === individualPDFGeneration)
        $('#real-pdf-status').textContent = e.message;
} }
async function paintIndividualPDF() { const doc = individualPDFDoc, gen = individualPDFGeneration, page = await doc.getPage(individualPDFPage); if (gen !== individualPDFGeneration)
    return; const canvas = $('#real-pdf-canvas'), viewport = page.getViewport({ scale: Math.max(260, $('#real-pdf-wrap').clientWidth - 24) / page.getViewport({ scale: 1 }).width }); canvas.width = viewport.width; canvas.height = viewport.height; individualPDFPaint = page.render({ canvasContext: canvas.getContext('2d'), viewport }); await individualPDFPaint.promise; if (gen !== individualPDFGeneration)
    return; $('#real-pdf-pages').textContent = individualPDFPage + '/' + doc.numPages; $('#real-pdf-status').textContent = 'Documento exibido na plataforma · sem download automático'; $('#real-pdf-prev').disabled = individualPDFPage === 1; $('#real-pdf-next').disabled = individualPDFPage === doc.numPages; const text = await page.getTextContent(); if (gen === individualPDFGeneration)
    $('#real-pdf-text').textContent = text.items.map(i => i.str || '').join(' '); }
async function qualifyIndividual(processId) { const p = selected; form('Confirmar habilitação anual', `<p>${esc(p.name)} · Matrícula ${esc(p.registration)}<br>A habilitação exige análise humana das regras do plano. Os certificados elegíveis são recuperados do servidor, com corte em 30/06.</p>` + field('Ano de referência do fechamento em 30/06', 'cycle', 'number', realToday().slice(0, 4), 'required min="2000" max="2200"') + ['Interstício e tempo de efetivo exercício conferidos', 'Avaliação de desempenho e requisitos do plano conferidos', 'Certificados aprovados e enviados até 30/06 conferidos', 'Cargo de concurso, função exercida e seus efeitos conferidos', 'Impedimentos, afastamentos e demais exigências legais conferidos'].map((l, i) => `<label><input type="checkbox" name="check${i}" required> ${l}</label>`).join('') + '<label>Fundamentação da habilitação<textarea name="reason" maxlength="2000" required></textarea></label>', 'Habilitar para classificação', async (b, f) => { b.linkId = p.id; b.version = p.version; b.processId = processId; b.checks = [0, 1, 2, 3, 4].map(i => f.elements['check' + i].checked); await api(endpoint('access/annual/qualify'), 'POST', b); await load(); openPerson(p.id); }); }
async function activateAnnual(processId) { await api(endpoint('access/annual/activate'), 'POST', { linkId: selected.id, version: selected.version, processId }); await load(); }
async function drawAnnual(){return drawAnnualSelection();}
async function newAnnualOrdinance() { form('Registrar portaria publicada', select('Plano do Quadro Geral', 'plan', workspace.plans.filter(p => planFamilyName(p) === 'geral').map(p => [p.id, p.name])) + field('Ano de referência do direito aquisitivo', 'cycle', 'number', realToday().slice(0, 4), 'required min="2000" max="2200"') + field('Número da portaria', 'number') + field('Data da publicação oficial', 'published', 'date') + field('Diário oficial / edição / página / referência verificável', 'reference') + field('Data de efeito no exercício seguinte', 'effect', 'date') + '<p>Registro de uma publicação já realizada. Este formulário não publica a portaria no diário oficial.</p>', 'Registrar publicação', async (b) => { await api(endpoint('access/annual/ordinances'), 'POST', b); render(); }); }
async function classifyAnnual() { try {
    const plan=document.querySelector('[name="annual-plan"]').value,cycle=Number(document.querySelector('[name="annual-cycle"]').value),effect=document.querySelector('[name="annual-effect"]').value;if(!plan||!Number.isInteger(cycle)||cycle<2000||cycle>2200)throw Error('Selecione o plano e o ano de referência.');if(!effect||effect.slice(0,4)!==String(cycle+1))throw Error('A data de efeito deve estar no ano '+(cycle+1)+'.');rankedOrdinance={plan,cycle,effect,id:null};
    rankedCriteria = [1, 2, 3].map(n => document.querySelector('[name="criterion' + n + '"]').value).filter(Boolean);
    const data = await api(endpoint('access/annual/rank'), 'POST', { plan: rankedOrdinance.plan, cycle: rankedOrdinance.cycle, criteria: rankedCriteria });
    rankedRows = data.rows;
    $('#annual-ranking').innerHTML = '<h2>Classificação por cargo</h2><p>Selecione quantos promover em cada cargo. Ao marcar uma posição, as anteriores e os empatados desse cargo serão incluídos. Desmarcar uma posição retira as seguintes desse cargo.</p><button class="btn secondary" id="annual-all">Selecionar todos os habilitados</button> <button class="btn secondary" id="annual-clear">Limpar seleção</button><table><thead><tr><th>Selecionar</th><th>Cargo</th><th>Classificação</th><th>Servidor / matrícula</th><th>Valores dos critérios</th><th>Destino</th></tr></thead><tbody>' + rankedRows.map(r => `<tr><td><input type="checkbox" class="annual-pick" value="${r.id}" aria-label="Selecionar ${esc(r.name)} matrícula ${esc(r.registration)}"></td><td>${esc(r.role)}</td><td>${r.position}º ${r.tie ? '· empate' : ''}</td><td>${esc(r.name)}<br>${esc(r.registration)}</td><td>${r.values.map((v, i) => v + (rankedCriteria[i] === 'service' ? ' dias' : rankedCriteria[i] === 'hours' ? ' h' : ' pontos')).join(' → ')}</td><td>${esc(r.from)} → ${esc(r.target)}</td></tr>`).join('') + '</tbody></table><p id="annual-count" role="status"></p><button class="btn" onclick="confirmAnnualBatch()">Revisar seleção e justificar</button>';
    const picks = [...document.querySelectorAll('.annual-pick')];
    const update = () => $('#annual-count').textContent = picks.filter(p => p.checked).length + ' de ' + rankedRows.length + ' matrículas selecionadas';
    picks.forEach(p => p.onchange = () => { const row = rankedRows.find(r => r.id === p.value); picks.forEach(other => { const r = rankedRows.find(r => r.id === other.value); if (r.group === row.group && (p.checked ? r.position <= row.position : r.position >= row.position))
        other.checked = p.checked; }); update(); });
    $('#annual-all').onclick = () => { picks.forEach(p => p.checked = true); update(); };
    $('#annual-clear').onclick = () => { picks.forEach(p => p.checked = false); update(); };
    update();
    $('#annual-error').textContent = '';
}
catch (e) {
    $('#annual-error').textContent = e.message;
} }
async function confirmAnnualBatch(){return reviewAnnualSelection();}

function requestIndividualProgression(id) { const p = workspace.links.find(l => l.id === id); if (!p)
    return; form('Solicitar progressão · matrícula ' + p.registration, '<p>O pedido não concede progressão. Haverá análise dos requisitos, dos prazos e das regras próprias do plano.</p>' + select('Tipo', 'type', [['Progressão horizontal','Promoção horizontal'],['Promoção vertical','Progressão vertical'],['Evolução por tempo de serviço','Evolução por tempo de serviço']]) + field('Início do período aquisitivo', 'start', 'date') + field('Fim do período aquisitivo', 'end', 'date') + field('Nível pretendido', 'targetLevel', 'text', p.level) + field('Referência pretendida', 'targetStage') + field('Fundamento / regra do plano', 'basis'), 'Enviar solicitação', async (data) => { await api(endpoint('access/requests'), 'POST', { linkId: id, version: p.version, data }); await load(); }); }

newPlan=()=>form('Cadastrar plano de carreira',field('Nome','name')+select('Poder','power',['Executivo','Legislativo'])+select('Regras próprias do plano','family',[['geral','Quadro Geral · ciclo anual'],['magisterio','Magistério · regras próprias'],['legislativo','Legislativo · regras próprias'],['proprio','Outro plano · parametrização própria']])+field('Fundamento legal','basis'),'Salvar plano',async b=>{await api(endpoint('plans'),'POST',b);await load()});
