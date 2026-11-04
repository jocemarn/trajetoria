# Arquitetura

A edição de produção utiliza PHP, PDO e JavaScript. public/index.php lê private/active.json e encaminha para a versão ativa em private/releases/VERSAO/app/bootstrap.php.

app concentra autenticação, autorização, contratos, cadastros, documentos e regras de evolução. assets/workspace contém a interface e assets/commercial o site institucional. PDFs pessoais são armazenados de forma privada e servidos após autorização.

Pessoa e matrícula são distintas. Cada vínculo tem cargo, lotação e carreira próprios. Escopos municipais e de comissão limitam o acesso. O administrador geral não recebe acesso ordinário aos prontuários municipais.

A pasta-fonte releases/1.0.0 é fixa por compatibilidade. Cada pacote recebe a versão no manifesto assinado. O atualizador prepara uma nova pasta e troca active.json, preservando dados fora das versões.

As pastas trajetoria, trajetoria-base e trajetoria-testes do workspace original são protótipos anteriores, preservados localmente. Este repositório tem a edição de produção como alvo principal. Scripts antigos de transformação de protótipos não compõem a rotina operacional aqui.
