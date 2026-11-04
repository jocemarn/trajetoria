const fields = { service: 'Antiguidade da admissão até o fechamento', performance: 'Nota de desempenho', hours: 'Horas de certificados aprovados' };
export const rankingCriteria = fields;
export function validateCriteria(criteria) { if (!Array.isArray(criteria) || !criteria.length || criteria.length > 3 || new Set(criteria).size !== criteria.length || criteria.some(c => !fields[c]))
    throw Error('Selecione critérios distintos e sua ordem de prioridade.'); return criteria; }
export function rankCandidates(candidates, criteria, cycle) { validateCriteria(criteria); const cutoff = cycle + '-06-30', groups = new Map(); for (const c of candidates) {
    const values = criteria.map(key => { if (key === 'service') {
        if (!c.admission || c.admission > cutoff)
            throw Error('Admissão incompatível com o ciclo: ' + c.registration);
        return Math.floor((Date.parse(cutoff + 'T12:00:00Z') - Date.parse(c.admission + 'T12:00:00Z')) / 86400000);
    } if (key === 'performance') {
        if (c.performance == null || !Number.isFinite(Number(c.performance)))
            throw Error('Nota de desempenho não informada para ' + c.registration);
        return Number(c.performance);
    } return c.evidence.reduce((n, e) => n + Number(e.hours || 0), 0); });
    const group = String(c.role || '').normalize('NFKC').trim().toLocaleLowerCase('pt-BR');
    if (!group)
        throw Error('Cargo não informado.');
    if (!groups.has(group))
        groups.set(group, []);
    groups.get(group).push({ ...c, group, values });
} const output = []; for (const rows of groups.values()) {
    rows.sort((a, b) => { for (let i = 0; i < criteria.length; i++) {
        const d = b.values[i] - a.values[i];
        if (d)
            return d;
    } return String(a.registration).localeCompare(String(b.registration), 'pt-BR', { numeric: true }); });
    let position = 1;
    rows.forEach((r, i) => { const tied = i > 0 && r.values.every((v, k) => v === rows[i - 1].values[k]); if (!tied)
        position = i + 1; r.position = position; r.tie = tied || !!rows[i + 1]?.values.every((v, k) => v === r.values[k]); output.push(r); });
} return output.sort((a, b) => a.group.localeCompare(b.group, 'pt-BR') || a.position - b.position); }
export function validateSelection(ranked, selected) { const ids = new Set(selected); if (!ids.size || ids.size !== selected.length || selected.some(id => !ranked.some(r => r.id === id)))
    throw Error('Seleção de habilitados inválida.'); for (const row of ranked) {
    if (!ids.has(row.id))
        continue;
    const omitted = ranked.find(r => r.group === row.group && r.position <= row.position && !ids.has(r.id));
    if (omitted)
        throw Error(omitted.position === row.position ? 'Empate na posição de corte: inclua todos os empatados ou aplique um desempate legal.' : 'Não é permitido preterir servidor com melhor classificação no mesmo cargo.');
} return ranked.filter(r => !ids.has(r.id)); }
