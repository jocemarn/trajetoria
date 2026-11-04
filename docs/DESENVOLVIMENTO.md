# Desenvolvimento

Use PHP 8.2+ com PDO SQLite/MySQL, mbstring, OpenSSL, ZIP e fileinfo; Node.js 22+; Python 3.11+. cryptography é necessária para assinatura de pacotes.

Na raiz:

```text
php tools/prepare-local.php
python tools/check_repository.py
python tools/run-tests.py
```

prepare-local.php não instala banco ou cria administrador real. Gera chave de instalação, ponteiro inicial e contas demonstrativas específicas desta cópia. Senhas legíveis ficam somente em sistema/output/usuarios-demonstracao.json, ignorado pelo Git.

Defina PHP_BIN e NODE_BIN para usar runtimes portáteis. O executor usa o diretório do executável PHP para compatibilidade com extensões configuradas por caminho relativo.

## Testes completos

Alguns testes antigos dependem da fixture SQLite. Copie sistema/private/releases/1.0.0 para sistema/.runtime/qa/private/releases/1.0.0 e sistema/private/demo-accounts.json para sistema/.runtime/qa/private/demo-accounts.json. Execute php sistema/tools/setup-qa.php. A ferramenta exige banco novo e se recusa a sobrescrever um existente.

Testes HTTP esperam servidor local em 127.0.0.1:8188 e contas da fixture. Não execute testes de importação, atualização ou concessão contra dados oficiais.

run-tests.py executa sintaxe PHP, casos isolados de sessão e testes da interface de comissões, portal, trilhas e login. Não exige banco de produção. check_repository.py verifica caminhos privados e marcadores de chaves nos arquivos publicáveis. Revise também os arquivos efetivos do commit.
