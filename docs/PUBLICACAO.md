# Publicação de versões

Atualize VERSION e CHANGELOG.md e execute as verificações. Para instalação nova, execute php tools/prepare-local.php. Para atualizar uma instalação existente, restaure a chave original em sistema/.signing/update-private.pem no ambiente autorizado. Não gere outra chave para essa finalidade.

```text
python sistema/tools/package.py --version 1.0.11
```

O empacotador usa Python cryptography e cria ZIPs em sistema/output/. O instalador contém arquivos privados e contas demonstrativas específicos daquela preparação. Não publique esses instaladores indiscriminadamente em repositórios ou Releases públicas.

Entregue atualizações pelo canal autorizado e confira assinatura, versão e hashes. Não inclua chaves privadas, configurações, bancos ou documentos pessoais.

Teste em homologação, faça backup e aplique pelo menu Atualizações com o administrador real. Os dados ficam fora das pastas de versão.

Publicar código no GitHub não implanta alterações na hospedagem. Não há deploy automático de produção configurado.
