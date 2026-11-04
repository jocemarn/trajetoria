# Segurança

Não publique vulnerabilidades com credenciais ou dados pessoais. O responsável deve definir um canal privado de reporte antes de receber informações sensíveis.

Nunca versione config.php, install.key, demo-accounts.json, .signing, .runtime, sessões, bancos, backups ou documentos enviados. O .gitignore e tools/check_repository.py fazem verificações complementares à revisão humana.

O administrador geral acessa municípios, contratos e manutenção. Perfis municipais acessam seu escopo. CPF identifica a pessoa e não substitui a senha.

Atualizações existentes exigem a chave privada original. Guarde-a em local protegido e restaure-a apenas no ambiente autorizado de empacotamento. Outra chave será rejeitada pelas instalações que confiam na chave anterior.

Não resolva falhas de sessão desativando CSRF, autenticação, autorização ou proteção dos documentos.
