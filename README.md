# Trajetória

Sistema de gestão de carreiras de servidores públicos municipais. Edição independente em PHP e MySQL para instalação em hospedagem com cPanel.

**Versão de referência:** 1.0.10. **Requisitos:** PHP 8.2+, MySQL 5.7 ou MariaDB compatível.

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
4. Siga [Instalação no cPanel](docs/INSTALACAO-CPANEL.md).

Empacotamento: `python sistema/tools/package.py --version 1.0.10`. Exige preparação local e Python cryptography. Atualizações existentes exigem a chave privada original, mantida fora do GitHub.

Somente dados fictícios integram as fontes. Configurações reais, senhas, sessões, chaves, bancos, backups e documentos de servidores ficam fora do repositório. A integração GOV.br não está ativa. Projeções dependem da parametrização e não substituem decisões administrativas.

Consulte [Segurança](SECURITY.md), [Histórico](CHANGELOG.md) e [Terceiros](THIRD_PARTY_NOTICES.md).
