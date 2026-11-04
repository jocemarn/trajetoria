# Instalação no cPanel

PHP 8.2+, PDO MySQL, OpenSSL, ZIP, mbstring e fileinfo. MySQL 5.7 ou MariaDB compatível. A hospedagem deve aceitar .htaccess e reescrita de URLs.

Referência de configuração: upload_max_filesize=32M, post_max_size=40M, memory_limit=256M, max_execution_time=120.

1. Prepare arquivos privados e gere o pacote conforme PUBLICACAO.md.
2. Extraia o instalador em pasta exclusiva.
3. Aponte o domínio para public/. Mantenha private/ ao lado e fora da raiz pública. Preserve os .htaccess.
4. Crie banco e usuário MySQL exclusivos.
5. Ative HTTPS, abra /instalar e informe private/install.key e os dados solicitados.
6. Crie o administrador real. Após concluir, o instalador fica bloqueado.
7. Valide a base DEMONSTRAÇÃO antes de cadastrar informações oficiais.

As pastas privadas de runtime, sessões, documentos, backups e versões precisam permitir escrita pelo PHP da conta. Não use 777. Confira a proteção HTTP de todos os arquivos privados.

## Reparo do login 1.0.10

Leia private/active.json, identifique a versão e faça backup de app/core.php e app/login.php da pasta ativa. Se necessário, substitua apenas esses dois arquivos pelas fontes corrigidas. Não altere config.php, banco ou active.json. Recarregue /login. Confira espaço, inodes, permissões de runtime/sessions e mensagens recentes de runtime/errors.log se o problema persistir.

Após recuperar o acesso, aplique o pacote completo assinado pelo atualizador.
