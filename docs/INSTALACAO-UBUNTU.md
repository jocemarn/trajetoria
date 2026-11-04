# Instalação no Ubuntu

Alvo: Ubuntu 24.04 LTS, domínio trajetoria.net.br, IP definido pelo responsável. Os scripts não alteram SSH nem ativam firewall automaticamente.

## WinSCP e preparação

Conecte o WinSCP ao servidor correto. Envie deploy/ubuntu para uma pasta temporária do usuário ubuntu. Via terminal SSH, execute com sudo prepare-server.sh. A leitura do repositório usa HTTPS público e não exige token do GitHub no servidor.

Serviços: Nginx, MySQL local, PHP 8.3 FPM e extensões. Configuração de banco em /etc/trajetoria/database.json, fora do site. O domínio fica em manutenção enquanto o HTTPS não estiver pronto.

## Conectar o GitHub

Execute deploy-initial.sh com sudo. Ele clona jocemarn/trajetoria em /srv/trajetoria/repository, copia a versão indicada por VERSION para /srv/trajetoria/app/private/releases e gera arquivos privados. O commit implantado fica registrado em private/runtime/github-deployment.json.

O script recusa nova implantação inicial sobre um sistema já instalado. Atualizações posteriores devem usar os pacotes assinados e o atualizador da aplicação. Sincronizar o GitHub não altera automaticamente dados nem publica uma nova versão em produção.

## DNS e HTTPS

No provedor DNS, configure o registro A do domínio para o IP aprovado. Crie www somente se for necessário, com configuração adicional de certificado e servidor web. Retire registros AAAA que apontem para outra máquina ou configure o IPv6 correspondente.

Libere TCP 80 e 443 no firewall da nuvem, preservando SSH. Execute activate-https.sh IP_APROVADO com sudo. Ele verifica o registro A, solicita certificado Let’s Encrypt e ativa HTTPS e renovação. A emissão inicial usa uma conta ACME sem e-mail; o responsável pode cadastrar contato posteriormente.

## Concluir a instalação

No WinSCP, consulte /home/ubuntu/trajetoria-acesso-instalacao.txt. Esse arquivo privado contém os dados do banco e a chave de instalação. Abra https://trajetoria.net.br/instalar e crie sua conta pessoal. Não envie as credenciais para o GitHub.

O instalador cria a base fictícia DEMONSTRAÇÃO. A migração do banco e documentos de uma instalação anterior é uma etapa separada, que exige cópia consistente e preservação da chave de criptografia original.

Fontes de referência: https://ubuntu.com/server/docs/how-to/web-services/install-nginx/ e https://ubuntu.com/server/docs/how-to/web-services/install-php/ e https://ubuntu.com/server/docs/how-to/databases/install-mysql/
