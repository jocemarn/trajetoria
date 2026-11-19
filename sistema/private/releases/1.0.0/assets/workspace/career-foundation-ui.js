/* Tabelas oficiais, regras executáveis e memória de tempo por matrícula. */
function foundationForm(title, body, submit, action) {
    $('#modal-content').innerHTML='<h2>'+esc(title)+'</h2><form id="foundation-form">'+body+
        '<p class="form-error" id="foundation-error" role="alert"></p><button class="btn" type="submit">'+esc(submit)+'</button></form>';
    $('#modal').showModal();
    $('#foundation-form').onsubmit=async event=>{
        event.preventDefault();
        const form=event.target,button=form.querySelector('[type=submit]');
        button.disabled=true;
        try {
            const close=await action(Object.fromEntries(new FormData(form)),form);
            if(close!==false)$('#modal').close();
        } catch(error) { if($('#foundation-error'))$('#foundation-error').textContent=error.message;else toast(error.message); }
        finally {button.disabled=false;}
    };
}
function foundationRows(headers,rows) {
    return '<div class="server-table"><table><thead><tr>'+headers.map(h=>'<th>'+esc(h)+'</th>').join('')+
        '</tr></thead><tbody>'+rows.map(row=>'<tr>'+row.map(v=>'<td>'+v+'</td>').join('')+'</tr>').join('')+
        (rows.length?'':'<tr><td colspan="'+headers.length+'">Nenhum registro.</td></tr>')+'</tbody></table></div>';
}
const foundationNewSalaryJob=newSalaryJob;
newSalaryJob=async frameId=>{
    if(salaryDemoMunicipality()){
        $('#modal-content').innerHTML='<h2>Cadastrar tabela do cargo</h2><div class="links"><button class="btn" id="foundation-import-csv">Importar CSV oficial</button><button class="btn secondary" id="foundation-demo-catalogue">Usar catálogo demonstrativo</button></div>';
        $('#modal').showModal();
        $('#foundation-import-csv').onclick=()=>officialImportForm(frameId);
        $('#foundation-demo-catalogue').onclick=()=>foundationNewSalaryJob(frameId);
        return;
    }
    await officialImportForm(frameId);
};
async function officialImportForm(frameId, jobId='') {
    try {
        const context=await api(endpoint('access/salaries/tables'));
        if(!context.frames.some(f=>f.id===frameId))throw Error('Quadro não encontrado.');
        salaryContext=context;
        const frame=context.frames.find(f=>f.id===frameId),job=context.jobs.find(j=>j.id===jobId);
        foundationForm(job?'Nova vigência · '+job.name:'Cadastrar tabela oficial',
            '<p>Quadro '+esc(frame.name)+'. Informe os valores oficiais de vencimento base de cada referência.</p>'+
            field('Cargo','name','text',job?.name||'',job?'required readonly':'required')+
            field('Jornada semanal','workload','number',job?.workload||'','required min="1" max="60" '+(job?'readonly':''))+
            field('Início da vigência','effective','date')+
            field('Lei, artigo e referência da publicação','law','text','','required maxlength="1000"')+
            field('Versão do quadro já incorporada nos valores','baseSequence','number','0','required min="0" max="'+frame.version+'" step="1"')+
            '<p>Use 0 para valores anteriores aos reajustes registrados. Informe outra versão somente se esses reajustes já constarem no arquivo.</p>'+
            '<label>CSV em UTF-8<input type="file" id="official-csv-file" accept=".csv,text/csv"></label>'+
            '<label>Dados da tabela<textarea name="csv" id="official-csv" required rows="9" style="width:100%" placeholder="nivel;referencia;salario_base"></textarea></label>'+
            '<p>Cabeçalho obrigatório: <code>nivel;referencia;salario_base</code>. Use valores sem separador de milhar, como <code>4500,00</code>. É possível preencher a tabela diretamente acima.</p>',
            'Conferir prévia',async values=>{
                const input={...values,frameId,jobId,workload:Number(values.workload),baseSequence:Number(values.baseSequence),version:frame.version};
                const result=await api(endpoint('access/salaries/official-preview'),'POST',input);
                officialImportPreview(input,result);
                return false;
            });
        $('#official-csv-file').onchange=async event=>{
            const file=event.target.files[0];
            if(!file)return;
            if(file.size>1048576){$('#foundation-error').textContent='Use um arquivo de até 1 MB.';return;}
            $('#official-csv').value=await file.text();
        };
    } catch(error) {toast(error.message);}
}
function officialImportPreview(input,result) {
    foundationForm('Conferir importação oficial',
        '<p><b>'+esc(result.preview.name)+'</b> · '+result.preview.workload+' h · vigência '+fmt(result.preview.effective)+'</p>'+
        '<p>'+esc(result.preview.law)+'</p>'+foundationRows(['Nível','Referência','Vencimento base'],
            result.preview.cells.slice(0,100).map(c=>[esc(c.level),esc(c.reference),currency(c.cents)]))+
        '<p>'+result.count+' referências. A prévia exibe até 100 linhas.</p>'+
        '<label><input name="checked" type="checkbox" required> Conferi a fonte, todos os valores, a jornada, a vigência e os reajustes já incorporados.</label>',
        'Importar tabela',async(values,form)=>{
            await api(endpoint('access/salaries/official-import'),'POST',{...input,token:result.token,checked:form.elements.checked.checked});
            await drawSalaryTables();
            toast('Tabela oficial registrada com histórico preservado.');
        });
}
const foundationShowSalaryJobTable=showSalaryJobTable;
showSalaryJobTable=async id=>{
    const job=salaryContext?.jobs?.find(j=>j.id===id);
    if(job?.source!=='municipal')return foundationShowSalaryJobTable(id);
    try {
        const data=await api(endpoint('access/salaries/official-tables'),'POST',{jobId:id});
        $('#modal-content').innerHTML='<h2>'+esc(data.job.name)+' · '+data.job.workload+' h</h2>'+
            data.versions.slice().reverse().map(table=>'<section class="card pad"><h3>Vigência '+fmt(table.effective)+'</h3><p>'+esc(table.law)+'</p>'+
                foundationRows(['Nível','Referência','Vencimento base'],table.cells.map(c=>[esc(c.level),esc(c.reference),currency(c.cents)]))+
                '<p>Reajustes incorporados até a versão '+table.baseSequence+'.</p></section>').join('')+
            '<button class="btn" id="official-new-version">Cadastrar nova vigência</button>';
        $('#modal').showModal();
        $('#official-new-version').onclick=()=>officialImportForm(job.frame,id);
    } catch(error){toast(error.message);}
};
const foundationBindSalary=bindSalary;
bindSalary=async(linkId,position)=>{
    try {
        const context=await getSalaryContext(),p=workspace.links.find(l=>l.id===linkId);
        if(!p)return;
        const official=context.jobs.filter(j=>j.source==='municipal'&&context.frames.find(f=>f.id===j.frame)?.plan===p.planId&&
            j.name.toLocaleLowerCase()===p.role.toLocaleLowerCase()&&Number(j.workload)===Number(p.workload));
        if(!official.length) {
            if(salaryDemoMunicipality())return foundationBindSalary(linkId,position);
            throw Error('Cadastre uma tabela oficial com o mesmo cargo e jornada da matrícula.');
        }
        const processes=p.processes.filter(x=>x.state==='Em análise');
        if(position==='target'&&!processes.length)throw Error('Abra o processo de evolução antes de vincular o destino.');
        foundationForm('Vincular referência oficial',
            '<p>'+esc(p.name)+' · '+esc(p.registration)+' · '+esc(p.role)+'</p>'+
            select('Tabela do cargo','jobId',official.map(j=>[j.id,j.name+' · '+j.workload+' h']))+
            (position==='target'?select('Processo','processId',processes.map(x=>[x.id,x.type+' · '+x.targetLevel+'/'+x.targetStage]))+
                field('Data de efeito prevista','effect','date',realToday()):'')+
            '<p>A referência será conferida com o nível e a classe registrados na matrícula ou no destino do processo.</p>'+
            field('Fundamento da vinculação','basis')+
            '<label><input type="checkbox" name="checked" required> Conferi cargo, jornada, nível e referência.</label>',
            'Vincular célula',async(values,form)=>{
                await api(endpoint('access/salaries/official-map'),'POST',{...values,linkId,position,version:p.version,checked:form.elements.checked.checked});
                await load();openPerson(linkId);
            });
    }catch(error){toast(error.message);}
};
function executableRuleCard(rule) {
    const c=rule.config;
    const next={Rascunho:'Validada',Validada:'Ativa',Ativa:'Encerrada'}[rule.status];
    return '<section class="card pad"><h2>Versão '+rule.sequence+' · '+esc(c.label)+' '+status(rule.status)+'</h2>'+
        '<p>'+esc(c.kind)+' · '+esc(c.fromLevel)+'/'+esc(c.fromReference)+' → '+esc(c.toLevel)+'/'+esc(c.toReference)+'</p>'+
        '<p>Vigência '+fmt(c.from)+' a '+(c.until?fmt(c.until):'sem término')+'. '+esc(c.basis)+'</p>'+
        foundationRows(['Critério','Parâmetro'],[
            ['Tempo',c.timeValue+' '+(c.timeUnit==='months'?'meses de calendário':'dias computáveis')],
            ['Base de contagem',c.timeStart==='admission'?'Admissão':'Enquadramento atual'],
            ['Avaliação',c.performanceRequired?c.evaluationCount+' períodos · nota '+c.minScore:'Dispensada pela configuração'],
            ['Capacitação',c.coursesRequired?c.minHours+' horas aprovadas':'Dispensada pela configuração'],
            ['Titulação',c.titleRequired?'Título aprovado para o nível de destino':'Dispensada pela configuração'],
            ['Calendário',c.calendar==='annual'?'Fechamento '+esc(c.cutoff)+' · publicação '+esc(c.publication):'Contínuo'],
            ['Pendências',esc(c.pending||'Nenhuma informada')]
        ])+
        '<div class="links">'+button('Simular matrícula',"foundationSimulate('"+rule.id+"')")+
        button('Copiar para rascunho',"foundationRuleForm('"+rule.id+"')")+
        (next?button(next==='Validada'?'Validar':next==='Ativa'?'Ativar':'Encerrar',"foundationRuleStatus('"+rule.id+"','"+next+"')"):'')+'</div></section>';
}
const foundationCreateRule=createRule;
createRule=async id=>{
    if(salaryDemoMunicipality()&&!ruleRows.find(r=>r.id===id&&r.engineVersion===2))return foundationCreateRule(id);
    await foundationRuleForm(id);
};
async function foundationRuleForm(copyId='') {
    if(!configuredPlan)return;
    try {
        const data=await api('career/context?tenant='+encodeURIComponent(tenant())+'&planId='+encodeURIComponent(configuredPlan.id));
        if(!data.jobs.length)throw Error('Cadastre primeiro a tabela oficial do cargo em Quadros e salários.');
        const source=data.rules.find(r=>r.id===copyId)?.config||{};
        const bool=(title,name)=>select(title,name,[['','Defina'],['true','Sim'],['false','Não']]);
        foundationForm('Regra executável · nova versão',
            field('Nome','label','text',source.label||'')+
            select('Cargo e jornada','jobId',data.jobs.map(j=>[j.id,j.name+' · '+j.workload+' h']))+
            select('Modalidade','kind',['Progressão horizontal','Promoção vertical','Evolução por tempo de serviço'])+
            '<div class="form-grid">'+field('Nível de origem','fromLevel','text',source.fromLevel||'')+
            field('Referência de origem','fromReference','text',source.fromReference||'')+
            field('Nível de destino','toLevel','text',source.toLevel||'')+
            field('Referência de destino','toReference','text',source.toReference||'')+
            field('Vigência inicial','from','date',source.from||'')+
            field('Vigência final opcional','until','date',source.until||'','')+'</div>'+
            '<h3>Tempo e requisitos</h3>'+
            select('Contar a partir de','timeStart',[['admission','Admissão'],['enrollment','Enquadramento atual']])+
            select('Unidade do interstício','timeUnit',[['months','Meses de calendário'],['days','Dias computáveis']])+
            field('Interstício exigido','timeValue','number',source.timeValue??'','required min="0" max="20000" step="1"')+
            bool('Exigir estágio probatório concluído','stableRequired')+
            bool('Exigir avaliação de desempenho','performanceRequired')+
            field('Nota mínima','minScore','number',source.minScore??'','min="0" max="100" step="0.01"')+
            field('Períodos consecutivos de avaliação','evaluationCount','number',source.evaluationCount??'','min="1" max="20" step="1"')+
            select('Consolidação dos membros por período','aggregation',[['mean','Média dos pareceres'],['minimum','Menor nota dos pareceres']])+
            bool('Exigir capacitação aprovada','coursesRequired')+
            field('Horas mínimas de capacitação','minHours','number',source.minHours??'','min="1" max="10000" step="0.01"')+
            bool('Exigir titulação para o destino','titleRequired')+
            select('Período dos certificados','certificateWindow',[['career','Desde a admissão'],['cycle','Período aquisitivo apurado']])+
            '<h3>Calendário e validação</h3>'+
            select('Calendário','calendar',[['continuous','Contínuo'],['annual','Anual']])+
            field('Fechamento anual (MM-DD)','cutoff','text',source.cutoff||'','pattern="[0-9]{2}-[0-9]{2}"')+
            field('Prazo da portaria (MM-DD)','publication','text',source.publication||'','pattern="[0-9]{2}-[0-9]{2}"')+
            field('Início dos efeitos (MM-DD)','effectMonthDay','text',source.effectMonthDay||'','pattern="[0-9]{2}-[0-9]{2}"')+
            field('Exercícios após o fechamento','effectOffset','number',source.effectOffset??0,'min="0" max="5" step="1"')+
            field('Fundamento legal completo','basis','text',source.basis||'','required maxlength="1000"')+
            '<label>Pendências para validação<textarea name="pending" rows="3" maxlength="2000">'+esc(source.pending||'')+'</textarea></label>',
            'Salvar rascunho',async values=>{
                for(const name of ['stableRequired','performanceRequired','coursesRequired','titleRequired']) {
                    values[name]=values[name]==='true'?true:values[name]==='false'?false:null;
                }
                await api(endpoint('career/rule-draft'),'POST',{planId:configuredPlan.id,config:values});
                await openPlan(configuredPlan.id);
            });
        const form=$('#foundation-form');
        for(const [name,value]of Object.entries(source))if(form.elements[name]&&form.elements[name].tagName==='SELECT')form.elements[name].value=String(value??'');
    }catch(error){toast(error.message);}
}
function foundationRuleStatus(id,next) {
    const rule=ruleRows.find(r=>r.id===id);if(!rule)return;
    foundationForm('Alterar situação · '+rule.config.label,
        '<p>'+esc(rule.status)+' → '+esc(next)+'</p>'+
        (next==='Encerrada'?field('Encerrar a partir de','endedOn','date',realToday()):'')+
        field('Fundamento da conferência e da alteração','basis')+
        '<label><input name="confirmed" type="checkbox" required> Conferi a legislação, os parâmetros e a vigência desta versão.</label>',
        'Confirmar',async(values,form)=>{
            await api(endpoint('career/rule-status'),'POST',{...values,id,version:rule.version,status:next,confirmed:form.elements.confirmed.checked});
            await openPlan(configuredPlan.id);
        });
}
function foundationSimulate(ruleId,linkId="") {
    const rule=ruleRows.find(r=>r.id===ruleId);
    const links=workspace.links.filter(p=>p.planId===rule?.plan);
    if(!links.length){toast('Cadastre uma matrícula neste plano para simular.');return;}
    foundationForm('Apurar requisitos da matrícula',
        select('Matrícula','linkId',links.map(p=>[p.id,p.name+' · '+p.registration]))+
        field('Data da apuração','at','date',realToday())+
        field('Data de efeito salarial','effect','date',rule.config.calendar==='annual'?(Number(realToday().slice(0,4))+rule.config.effectOffset)+'-'+rule.config.effectMonthDay:realToday())+
        '<label>Processo em análise opcional<select name="processId" id="foundation-process"><option value="">Simular a transição da regra</option></select></label>',
        'Conferir',async values=>{
            const result=await api(endpoint('career/evaluate'),'POST',{...values,ruleId});
            $('#modal-content').innerHTML=foundationRuleResultMarkup(result);
            return false;
        });
    const sync=()=>{
        const p=links.find(l=>l.id===$('#foundation-form').elements.linkId.value);
        $('#foundation-process').innerHTML='<option value="">Simular a transição da regra</option>'+p.processes.filter(x=>x.state==='Em análise').map(x=>'<option value="'+esc(x.id)+'">'+esc(x.type+' · '+x.targetLevel+'/'+x.targetStage)+'</option>').join('');
    };
    if(linkId&&links.some(link=>link.id===linkId))$('#foundation-form').elements.linkId.value=linkId;
    $('#foundation-form').elements.linkId.onchange=sync;sync();
}
function foundationTimeMarkup(report) {
    const effects={count:'Computável',exclude:'Excluído',reset:'Interrompe e reinicia',pending:'Pendente de análise'};
    return '<h3>Memória de cálculo do tempo</h3><p>'+report.calendarDays+' dias no período · '+report.countedDays+' computáveis · '+report.excludedDays+' excluídos · '+report.pendingDays+' pendentes.</p>'+
        '<p>Interstício atual desde '+fmt(report.cycleStart)+': '+report.cycleDays+' dias computáveis.</p>'+
        foundationRows(['Início','Fim','Dias','Tratamento','Fundamento'],report.timeline.map(row=>[
            fmt(row.start),fmt(row.end),row.days,esc(effects[row.effect]),
            row.sources.length?row.sources.map(s=>esc(s.type)+' · '+esc(s.basis)).join('<br>'):'Efetivo exercício no período informado'
        ]));
}
let foundationTimeState=null;
async function foundationOpenTime(linkId,start='',end='',modality='horizontal') {
    const p=workspace.links.find(l=>l.id===linkId);if(!p)return;
    try {
        const query=new URLSearchParams({tenant:tenant(),linkId,start:start||p.admission,end:end||realToday(),modality});
        const data=await api('career/time?'+query);foundationTimeState={...data,link:p,modality};
        $('#modal-content').innerHTML='<h2>Tempo de carreira · '+esc(p.registration)+'</h2>'+
            '<form id="foundation-time-query">'+field('Início','start','date',data.report.start)+field('Fim','end','date',data.report.end)+
            select('Modalidade','modality',[['horizontal','Horizontal / tempo'],['vertical','Vertical']])+
            '<button class="btn">Recalcular</button></form>'+foundationTimeMarkup(data.report)+
            (workspace.permissions.write?'<h3>Ocorrências e classificações</h3>'+button('+ Registrar período',"foundationPeriodForm()")+
                foundationRows(['Ocorrência','Período','Tratamento','Ações'],data.sources.map(s=>[
                    esc(s.type),fmt(s.start)+' a '+(s.end?fmt(s.end):'em aberto'),esc(s.effect),
                    '<button class="function-icon" title="Classificar período" aria-label="Classificar período" onclick="foundationPeriodForm('+esc(JSON.stringify(s.source))+')">✎</button>'
                ])):'');
        $('#modal').showModal();
        $('#foundation-time-query').elements.modality.value=modality;
        $('#foundation-time-query').onsubmit=event=>{
            event.preventDefault();const values=Object.fromEntries(new FormData(event.target));
            foundationOpenTime(linkId,values.start,values.end,values.modality);
        };
    }catch(error){toast(error.message);}
}
function foundationPeriodForm(source='') {
    const state=foundationTimeState;if(!state)return;
    const occurrence=state.sources.find(s=>s.source===source);
    const old=state.periods.find(p=>p.id===occurrence?.periodId);
    const inherited=Boolean(occurrence&&!source.startsWith('period:'));
    const linkId=state.link.id;
    foundationForm(old?'Revisar classificação':'Classificar período funcional',
        (inherited?'<p>As datas acompanham a ocorrência original. Para encerrar uma função ainda em exercício, registre o encerramento da designação na ficha do servidor.</p>':'<p>O fim é opcional. Enquanto o período estiver em curso, a apuração considera até a data consultada. Para encerrá-lo depois, edite esta classificação e informe o fim.</p>')+
        field('Tipo / descrição','type','text',old?.type||occurrence?.type||'')+
        field('Início','start','date',occurrence?.start||old?.start||'',inherited?'required readonly':'required')+
        field('Fim opcional — deixe vazio enquanto estiver em curso','end','date',occurrence?.end||old?.end||'',inherited?'readonly':'')+
        select('Efeito sobre o interstício','effect',[['pending','Pendente de análise'],['count','Computável'],['exclude','Suspende a contagem'],['reset','Interrompe e reinicia após o período']])+
        select('Aplicar na modalidade','modality',[['all','Horizontal e vertical'],['horizontal','Horizontal'],['vertical','Vertical']])+
        field('Fundamento legal e ato do período','basis','text',old?.basis||'','required maxlength="1000"'),
        'Salvar classificação',async values=>{
            await api(endpoint('career/period'),'POST',{...values,linkId,linkVersion:state.linkVersion,
                id:old?.id||'',version:old?.version,source:old?.source||(source.startsWith('period:')?'':source)});
            await load();openPerson(linkId);await foundationOpenTime(linkId,state.report.start,state.report.end,state.modality);
            return false;
        });
    const form=$('#foundation-form');
    if(old){form.elements.effect.value=old.effect;form.elements.modality.value=old.modality;}
}
const foundationProfileRenderer=renderPerson;
renderPerson=()=>{
    foundationProfileRenderer();
    if(selected&&!session?.platformAdmin)$('#content').insertAdjacentHTML('beforeend',
        '<section class="card pad"><h2>Apuração do tempo de carreira</h2><p>Confira afastamentos, cessões, funções e os períodos computáveis desta matrícula.</p>'+
        button('Apurar tempo',"foundationOpenTime('"+selected.id+"')")+'</section>');
};

let foundationCalendarRequest=0;
const foundationDrawAnnual=drawAnnualSelection;
drawAnnualSelection=async()=>{
    await foundationDrawAnnual();
    const update=async()=>{
        const plan=document.querySelector('[name="annual-plan"]'),cycle=document.querySelector('[name="annual-cycle"]');
        const effect=document.querySelector('[name="annual-effect"]'),info=$('#annual-calendar-info');
        if(!plan?.value||!cycle?.value||!effect||!info)return;
        const generation=++foundationCalendarRequest,t=tenant();
        try {
            const calendar=await api('career/calendar?'+new URLSearchParams({tenant:t,planId:plan.value,cycle:cycle.value}));
            if(generation!==foundationCalendarRequest||tenant()!==t||!effect.isConnected)return;
            info.textContent='Fechamento '+fmt(calendar.cutoff)+' · prazo de publicação '+fmt(calendar.publication)+'.';
            effect.min=calendar.effectYear+'-'+calendar.effectMonthDay;
            effect.max=calendar.effectYear+'-12-31';
            if(effect.value<effect.min||effect.value>effect.max)effect.value=effect.min;
        }catch(error){if(generation===foundationCalendarRequest&&info.isConnected)info.textContent=error.message;}
    };
    document.querySelector('[name="annual-plan"]')?.addEventListener('change',update);
    document.querySelector('[name="annual-cycle"]')?.addEventListener('change',update);
    await update();
};
const foundationRender=render;
render=()=>{
    foundationRender();
    if(view==='Regras do plano'&&configuredPlan&&workspace.permissions.admin&&!$('#foundation-new-rule')){
        document.querySelector('.page-title')?.insertAdjacentHTML('beforeend',
            '<button id="foundation-new-rule" class="btn secondary" onclick="foundationRuleForm()">+ Regra executável</button>');
    }
};

function foundationRuleResultMarkup(result) {
    return '<h2>'+esc(result.outcome)+'</h2><p>Versão '+result.sequence+' · '+esc(result.ruleStatus)+'</p>'+
        foundationRows(['Requisito','Apurado','Exigido','Resultado'],result.checks.map(c=>[
            esc(c.label),esc(typeof c.actual==='object'?JSON.stringify(c.actual):c.actual),
            esc(typeof c.required==='object'?JSON.stringify(c.required):c.required),esc(c.state)]))+
        foundationTimeMarkup(result.time)+'<p>A concessão exige a decisão do processo e o ato administrativo.</p>';
}
const foundationOldSimulation=openSimulation;
openSimulation=async()=>{
    const p=selected;if(!p)return;
    try {
        const result=await api('rules?'+new URLSearchParams({tenant:tenant(),planId:p.planId}));
        const executable=result.rows.filter(rule=>rule.engineVersion===2);
        if(!executable.length) {
            if(salaryDemoMunicipality())return foundationOldSimulation();
            throw Error('Cadastre e valide uma regra executável deste plano para conferir os requisitos.');
        }
        ruleRows=result.rows;
        foundationForm('Selecionar versão para conferência',
            select('Versão','ruleId',executable.map(rule=>[rule.id,'V'+rule.sequence+' · '+rule.config.label+' · '+rule.status])),
            'Selecionar',async values=>{foundationSimulate(values.ruleId,p.id);return false;});
    }catch(error){toast(error.message);}
};
