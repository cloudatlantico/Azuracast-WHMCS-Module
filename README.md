# Módulo de Provisionamento WHMCS - Azuracast V4

Este repositório contém um módulo de provisionamento para WHMCS chamado **Azuracast V4**, preparado para integrar com a API do Azuracast.

## Recursos implementados

- Configuração de servidor (Hostname/IP + API Key no `Access Hash` do WHMCS).
- Criação de conta de usuário no Azuracast com:
  - E-mail do cliente WHMCS.
  - Senha aleatória gerada automaticamente (quando o usuário ainda não existe).
  - Reutilização de usuário existente pelo mesmo e-mail para evitar erro de duplicidade.
- Criação de estação de rádio com parâmetros configuráveis no produto:
  - Quota de disco.
  - Limite de ouvintes simultâneos.
  - Limite de bitrate.
  - AutoDJ habilitado/desabilitado.
  - Quantidade de mount points.
  - Tipo de streamer (Icecast/Shoutcast), com mapeamento automático para valores da API.
- Criação de Role dedicada por serviço para acesso total **apenas à estação do cliente**, sem permissões globais.
- Vinculação automática da Role ao usuário criado/reutilizado para que a estação apareça corretamente no painel do usuário.
- Suspensão e remoção de suspensão administrativa da estação.
- Terminação do serviço removendo estação, role e usuário vinculados.
- Área do cliente com informações da estação:
  - Botões de iniciar/desligar rádio.
  - Status online/offline.
  - Portas, credenciais e dados gerais.

## Estrutura

- `modules/servers/azuracastv4/azuracastv4.php`: lógica principal do módulo.
- `modules/servers/azuracastv4/templates/clientarea.tpl`: template da área do cliente.

## Instalação

1. Copie a pasta `modules/servers/azuracastv4` para a instalação do WHMCS.
2. Em **System Settings > Servers**, crie um servidor com:
   - Hostname ou IP do Azuracast.
   - API Key no campo **Access Hash**.
   - Pode informar o host com ou sem sufixo `/api` (o módulo normaliza automaticamente).
3. Em **System Settings > Products/Services**, associe o módulo **Azuracast V4** ao produto.
4. Ajuste os `Config Options` do produto conforme o plano comercial.

## Observações

- O módulo usa chamadas REST para endpoints administrativos e de estação do Azuracast.
- Dependendo da versão da API do seu Azuracast, alguns endpoints podem variar e requerer ajuste fino.
