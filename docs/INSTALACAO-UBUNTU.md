# Instalação no Ubuntu

Alvo: Ubuntu 24.04 LTS, domínio trajetoria.net.br, IP definido pelo responsável. Os scripts não alteram SSH nem ativam firewall automaticamente.

## WinSCP e preparação

Conecte o WinSCP ao servidor correto. Envie deploy/ubuntu para uma pasta temporária do usuário ubuntu. Via terminal SSH, execute com sudo prepare-server.sh NOME_VERIFICADO_DA_MAQUINA. A leitura do repositório usa HTTPS público e não exige token do GitHub no servidor.

Serviços: Nginx, MySQL local, PHP 8.3 FPM e extensões. Configuração de banco em /etc/trajetoria/database.json, fora do site. O domínio fica em manutenção enquanto o HTTPS não estiver pronto.

## Conectar o GitHub

Execute deploy-initial.sh NOME_VERIFICADO_DA_MAQUINA com sudo. Ele clona jocemarn/trajetoria em /srv/trajetoria/repository, copia a versão indicada por VERSION para /srv/trajetoria/app/private/releases e gera arquivos privados. O commit implantado fica registrado em private/runtime/github-deployment.json.

O script recusa nova implantação inicial sobre um sistema já instalado. Atualizações posteriores devem usar os pacotes assinados e o atualizador da aplicação. Sincronizar o GitHub não altera automaticamente dados nem publica uma nova versão em produção.

## DNS e HTTPS

No provedor DNS, configure o registro A do domínio para o IP aprovado. Crie www somente se for necessário, com configuração adicional de certificado e servidor web. Retire registros AAAA que apontem para outra máquina ou configure o IPv6 correspondente.

Libere TCP 80 e 443 no firewall da nuvem, preservando SSH. Execute activate-https.sh IP_APROVADO NOME_VERIFICADO_DA_MAQUINA com sudo. Ele verifica o registro A, solicita certificado Let’s Encrypt e ativa HTTPS e renovação. A emissão inicial usa uma conta ACME sem e-mail; o responsável pode cadastrar contato posteriormente.

## Concluir a instalação

No WinSCP, consulte /home/ubuntu/trajetoria-acesso-instalacao.txt. Esse arquivo privado contém os dados do banco e a chave de instalação. Abra https://trajetoria.net.br/instalar e crie sua conta pessoal. Não envie as credenciais para o GitHub.

O instalador cria a base fictícia DEMONSTRAÇÃO. A migração do banco e documentos de uma instalação anterior é uma etapa separada, que exige cópia consistente e preservação da chave de criptografia original.

Fontes de referência: https://ubuntu.com/server/docs/how-to/web-services/install-nginx/ e https://ubuntu.com/server/docs/how-to/web-services/install-php/ e https://ubuntu.com/server/docs/how-to/databases/install-mysql/

## Destino confirmado

Para esta implantação, o responsável confirmou o IP 177.93.129.219, o nome de máquina trajetoria e o IP privado 172.18.3.18. Confira ambos antes de executar os scripts. O IP 177.93.129.204 pertence à outra instância e não deve receber esta implantação.

### Acesso temporário pelo IP aprovado

Após a preparação inicial, execute `sudo bash enable-ip-http.sh 177.93.129.219 trajetoria` na pasta dos scripts. Acesse http://177.93.129.219/instalar e use os parâmetros do arquivo privado `/home/ubuntu/trajetoria-acesso-instalacao.txt`. Escolha sua conta pessoal no instalador. O HTTP temporário não criptografa a conexão.

Quando o registro A resolver para esse IP, execute `sudo bash activate-https.sh 177.93.129.219 trajetoria`. O procedimento ativa o certificado e altera o endereço da aplicação para https://trajetoria.net.br, preservando banco, contas e chave de aplicação. O acesso HTTP passa a redirecionar para o domínio seguro.
