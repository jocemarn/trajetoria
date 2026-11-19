import assert from 'node:assert/strict';
import fs from 'node:fs';import vm from 'node:vm';
const root=new URL('../private/releases/1.0.0/assets/workspace/',import.meta.url);
const source=fs.readFileSync(new URL('registry-lists.js',root),'utf8');
const assignment=source.slice(source.lastIndexOf('filterServerLinks='),source.indexOf('const registryClearServers'));
const sandbox={};vm.createContext(sandbox);vm.runInContext("let registryServerColumns={0:'Pessoa 51'};let workspace={units:[],plans:[]};let registryServerFilterBase=links=>links;let filterServerLinks;const registryNormalize=value=>String(value??'').toLowerCase();"+assignment+"globalThis.result=filterServerLinks(Array.from({length:60},(_,i)=>({name:'Pessoa '+(i+1),registration:String(i+1),level:'I',stage:'A'})),{});",sandbox);
assert.equal(sandbox.result.length,1);assert.equal(sandbox.result[0].registration,'51');
const access=fs.readFileSync(new URL('individual-access.js',root),'utf8');assert.ok(access.includes("['Minha Trajetória', 'Minha comissão', 'Meu perfil', 'Processos de evolução']"));
console.log('OK: filtro de cabeçalho alcança servidores além da primeira página e a comissão mantém a navegação dos processos.');
