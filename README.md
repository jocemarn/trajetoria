# Trajetória

Sistema de gestão de carreiras de servidores públicos municipais. Edição independente em PHP e MySQL para instalação em hospedagem com cPanel.

**Versão de referência:** 1.3.0. **Requisitos:** PHP 8.2+, MySQL 5.7 ou MariaDB compatível.

## Estrutura

| Caminho | Conteúdo |
| --- | --- |
| sistema/public/ | Entrada HTTP e instalador |
| sistema/private/releases/1.0.0/app/ | Backend e regras de acesso |
| sistema/private/releases/1.0.0/assets/workspace/ | Interface e Minha Trajetória |
| sistema/private/releases/1.0.0/assets/commercial/ | Site comercial |
| sistema/private/releases/1.0.0/assets/pdfjs/ | Visualizador e licenças de terceiros |
| sistema/private/releases/1.0.0/data/ | Base fictícia DEMONSTRAÇÃO |
| sistema/tools/ | Empacotamento e testes existentes |
| tools/ | Preparação local e verificações |
| docs/ | Arquitetura, desenvolvimento e instalação |

A pasta de fontes permanece em releases/1.0.0 por compatibilidade com as ferramentas. VERSION e o manifesto assinado identificam a entrega.

## Funcionalidades

- Municípios isolados, poderes, unidades gestoras e lotações.
- Pessoa por CPF, com matrículas e carreiras independentes.
- Planos, cargos, tabelas e vigências salariais.
- Progressão vertical e promoção horizontal conforme cada plano.
- Certificados PDF, comissões, pareceres e avaliações.
- Seleção de habilitados, portarias e impacto sobre o salário-base.
- Minha Trajetória, relatórios, contratos e atualizações assinadas.

## Começar

1. Leia [Desenvolvimento](docs/DESENVOLVIMENTO.md).
2. Execute `php tools/prepare-local.php` para gerar arquivos privados de uma instalação nova.
3. Execute `python tools/check_repository.py` e `python tools/run-tests.py`.
4. Siga [Instalação no Ubuntu](docs/INSTALACAO-UBUNTU.md) ou [Instalação no cPanel](docs/INSTALACAO-CPANEL.md).

Empacotamento: `python sistema/tools/package.py --version 1.1.0`. Exige preparação local e Python cryptography. Atualizações existentes exigem a chave privada original, mantida fora do GitHub.

Somente dados fictícios integram as fontes. Configurações reais, senhas, sessões, chaves, bancos, backups e documentos de servidores ficam fora do repositório. A integração GOV.br não está ativa. Projeções dependem da parametrização e não substituem decisões administrativas.

Consulte [Segurança](SECURITY.md), [Histórico](CHANGELOG.md) e [Terceiros](THIRD_PARTY_NOTICES.md).

A versão 1.1.0 permite importar tabelas oficiais em Quadros e salários, configurar regras executáveis em Regras e versões e apurar tempo na ficha da matrícula. O CSV utiliza nivel;referencia;salario_base. As tabelas e regras reais devem ser conferidas pelo município antes da concessão.

## Avaliações, processos e importações

O Gestor Municipal encontra **Formulários de avaliação**, **Processos de evolução** e **Importações** no menu. Os formulários possuem fatores, pesos, escala, periodicidade, mínimo e vigência próprios de cada plano. Titulares e suplentes ficam separados; substituir titulares exige fundamento e preserva os pareceres concluídos. Períodos incompletos são reiniciados com histórico.

Processos do Magistério e do Legislativo possuem dossiê integrado e decisão com ato e publicação. O Quadro Geral mantém a classificação e concessão anual. Pareceres de uma comissão designada devem estar completos e favoráveis antes da aprovação.

Importações usam CSV UTF-8 separado por ponto e vírgula, até 500 linhas e 1 MB. Baixe o modelo na tela. A prévia valida cada linha e a confirmação é bloqueada se o cadastro mudar ou houver inconsistência. Matrículas não são sobrescritas; CPFs iguais representam a mesma pessoa com vínculos independentes. Históricos aceitam períodos de tempo em curso, ocorrências e enquadramentos anteriores fechados. A ausência da tabela oficial permanece como pendência salarial explícita.

Cadastros são apresentados em listas, com filtros por coluna e ações acessíveis. Exclusões são lógicas e preservam históricos; vínculos pendentes ou dependências ativas podem impedir a inativação. Versões legais e salariais usadas são preservadas.

## Diligências, recursos e notificações

O menu **Prazos do plano** permite ao Gestor Municipal registrar versões de prazos com vigência, fundamento, dias corridos ou úteis, feriados e início por emissão ou ciência expressa. Nenhum prazo legal é presumido para clientes reais. Casos abertos preservam o calendário usado na abertura, mesmo após nova versão.

Em **Diligências e recursos**, gestores e membros designados solicitam correções em processos ou certificados. O servidor registra ciência, anexa PDFs e protocola sua resposta. Os documentos ficam criptografados e são visualizados na plataforma. Complementações preservam o original e os pareceres; novos pareceres exigem a conferência dos comprovantes por todos os titulares. Horas, conclusão e data original de envio do certificado são preservadas.

Indeferimentos e rejeições geram avisos com fundamento e ciência. O servidor pode protocolar um recurso, inclusive fora do prazo, com sinalização para análise motivada de admissibilidade. O Gestor Municipal mantém a decisão ou reabre a análise; essa reabertura não concede progressão. Tramitações pendentes impedem a decisão do objeto. Prorrogações exigem motivo e fundamento e permanecem no histórico. Vencimentos não decidem o mérito automaticamente.

**Notificações** são internas, restritas aos destinatários e ao escopo vigente. Marcar um aviso como lido não equivale à ciência expressa. Esta versão não envia e-mails ou mensagens externas.

## Diligências, recursos e notificações

O Gestor Municipal configura **Prazos do plano** com fundamento legal, vigência, dias corridos/úteis, feriados e início por emissão ou ciência expressa. Casos abertos preservam uma cópia dos parâmetros usados. Não há valores legais preenchidos automaticamente para clientes reais.

Em **Diligências e recursos**, gestor e integrantes designados solicitam correções em processos e certificados em análise. O próprio servidor registra ciência, anexa PDFs protegidos e protocola a resposta. A comissão e o gestor recebem avisos internos. O gestor pode prorrogar mediante fundamento. Prazos vencidos geram alertas e não provocam indeferimento automático.

Decisões desfavoráveis geram ciência e acesso ao recurso do próprio servidor. Recursos fora do prazo são sinalizados para decisão motivada de admissibilidade. Apenas o Gestor Municipal decide o recurso. Aceitar o recurso reabre a análise sem conceder progressão. As decisões anteriores e os pareceres ficam preservados. Tramitações pendentes bloqueiam a decisão do respectivo processo ou certificado.

O visualizador de PDF mantém os documentos dentro da plataforma e oferece acesso à análise da tramitação. Complementações preservam o arquivo original e os metadados do curso e reiniciam a conferência dos titulares. Cada integrante precisa visualizar os PDFs complementares antes de emitir novo parecer. **Notificações** são internas; esta versão não envia e-mails nem mensagens externas. Marcar um aviso como lido não substitui a ciência expressa.
