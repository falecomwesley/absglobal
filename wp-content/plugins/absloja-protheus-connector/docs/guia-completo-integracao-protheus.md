# Guia Completo de Integração Protheus

## Endereço do site (onde o plugin está instalado)
- https://absloja.jjconsulting.com.br/

## URL da API Protheus
- Definir no painel do plugin em `API URL` (Aba Conexão).
- Valor de produção: `PREENCHER_COM_URL_DA_API_PROTHEUS`.

## Objetivo da integração
Este guia define a integração entre WooCommerce e Protheus no plugin `absloja-protheus-connector`, cobrindo configuração, fluxo funcional, contratos JSON, segurança, operação, testes e diagnóstico.

## Premissa oficial da API Protheus
- As APIs necessárias do Protheus já existem e estão disponíveis no ambiente da operação.
- Não há necessidade de desenvolver novos endpoints no Protheus para este escopo.
- O trabalho do plugin é consumir corretamente os endpoints existentes, mapear os dados e manter a sincronização estável.

## Escopo funcional
- Sincronização de pedidos do WooCommerce para o Protheus.
- Verificação e criação de clientes durante o envio de pedidos.
- Sincronização de catálogo de produtos para a loja.
- Sincronização de estoque com atualização de disponibilidade.
- Recebimento de webhooks de status de pedido e estoque.
- Registro de logs operacionais e técnicos.
- Reprocessamento automático de falhas transitórias.

## Contrato de API utilizado
Perfil principal: `totvs_ecommerce_v1`.

Endpoints padrão:
- `api/ecommerce/v1/retailSalesOrders`
- `api/ecommerce/v1/orderChangeStatus`
- `api/ecommerce/v1/retailItem`
- `api/ecommerce/v1/retailItem/{sku}`
- `api/ecommerce/v1/stock-product`
- `api/v1/health` (verificação de conexão, configurável)

Observação: os endpoints podem ser sobrescritos no painel administrativo quando a instância Protheus utiliza rotas diferentes.

## Configuração no WordPress
Menu: `WooCommerce > Protheus Connector`.

### Aba Conexão
Configurar:
- `API URL` da sua API Protheus.
- Tipo de autenticação: `Basic` ou `OAuth2`.
- Para OAuth2: `client_id`, `client_secret` e `token_endpoint`.
- Perfil de contrato: manter `totvs_ecommerce_v1` como padrão.

### Aba Mapeamentos
Configurar:
- Mapeamento de métodos de pagamento.
- Mapeamento de categorias.
- Regras de TES por UF.
- Mapeamento de status de pedido.

### Aba Agendamento
Configurar frequência de:
- Sincronização de catálogo.
- Sincronização de estoque.

### Aba Avançado
Configurar:
- Lote de sincronização.
- Política de tentativas automáticas.
- Token/segredo de webhook.
- Padrão de URL de imagens.
- Overrides de endpoint.
- Parâmetros de contexto de query (empresa/filial).

## Fluxo detalhado
### Pedido
1. Pedido muda para processamento no WooCommerce.
2. Plugin valida se já existe `_protheus_order_id`.
3. Plugin garante cliente no Protheus.
4. Plugin monta o corpo de requisição do pedido (cabeçalho e itens).
5. Plugin envia para endpoint de pedidos.
6. Plugin grava metadados de sincronização no pedido.
7. Plugin registra logs de API e operação.

### Cliente
1. Plugin extrai CPF/CNPJ do pedido.
2. Consulta cliente por parâmetro configurado (`cgc` por padrão).
3. Caso não encontre, cria novo cliente com dados de cobrança.
4. Retorna código do cliente para uso no pedido.

### Catálogo
1. Plugin consulta endpoint de produtos com paginação.
2. Lê coleções em `items` ou `products`.
3. Cria/atualiza produtos no WooCommerce por SKU.
4. Atualiza preço, descrição, categoria e metadados Protheus.
5. Pode atualizar imagem por URL direta ou padrão com `{sku}`.

### Estoque
1. Plugin consulta endpoint de estoque.
2. Lê coleções em `items` ou `stock`.
3. Extrai SKU e quantidade por chaves compatíveis.
4. Atualiza estoque no WooCommerce.
5. Oculta produto sem estoque e restaura visibilidade quando retornar.

## Contratos JSON da integração

### 1) JSON enviado pelo plugin para criar pedido
Rota padrão:
- `POST api/ecommerce/v1/retailSalesOrders`

Exemplo real do corpo enviado:
```json
{
  "header": {
    "C5_FILIAL": "01",
    "C5_NUM": "",
    "C5_TIPO": "N",
    "C5_CLIENTE": "000123",
    "C5_LOJACLI": "01",
    "C5_CONDPAG": "001",
    "C5_TABELA": "001",
    "C5_VEND1": "000001",
    "C5_PEDWOO": "789",
    "C5_EMISSAO": "20260302",
    "C5_FRETE": 15.5,
    "C5_DESCONT": 5
  },
  "items": [
    {
      "C6_FILIAL": "01",
      "C6_NUM": "",
      "C6_ITEM": "01",
      "C6_PRODUTO": "PROD001",
      "C6_QTDVEN": 2,
      "C6_PRCVEN": 99.9,
      "C6_VALOR": 199.8,
      "C6_TES": "501"
    }
  ]
}
```

### 2) JSON de retorno esperado do Protheus para pedido
O plugin aceita retorno `2xx` com JSON e extrai o identificador por qualquer uma das chaves abaixo:
- `C5_NUM`
- `order_id`
- `order_number`
- `id`

Exemplos válidos:
```json
{ "C5_NUM": "123456" }
```

```json
{ "order_id": "123456" }
```

### 3) JSON enviado pelo plugin para mudança de status
Rota padrão:
- `POST api/ecommerce/v1/orderChangeStatus`

Exemplo:
```json
{
  "order_id": "123456",
  "status": "processing"
}
```

### 4) JSON enviado para cancelamento
Rota padrão:
- `POST api/ecommerce/v1/orderChangeStatus`

Exemplo:
```json
{
  "order_id": "123456",
  "action": "cancel",
  "reason": "Cancelled in WooCommerce"
}
```

### 5) JSON enviado para reembolso
Rota padrão:
- `POST api/ecommerce/v1/orderChangeStatus`

Exemplo:
```json
{
  "order_id": "123456",
  "action": "refund",
  "amount": 199.8,
  "reason": "Refunded in WooCommerce"
}
```

### 6) Consulta de cliente por documento
Rota padrão:
- `GET api/v1/customers`

Query enviada (exemplo):
```text
?cgc=12345678901
```

Se configurado, também envia contexto de empresa/filial em query.

### 7) JSON enviado para criar cliente
Rota padrão:
- `POST api/v1/customers`

Exemplo real do corpo enviado:
```json
{
  "A1_FILIAL": "01",
  "A1_COD": "",
  "A1_LOJA": "01",
  "A1_NOME": "João Silva",
  "A1_NREDUZ": "João",
  "A1_CGC": "12345678901",
  "A1_TIPO": "F",
  "A1_END": "Rua Exemplo, 123",
  "A1_BAIRRO": "Centro",
  "A1_MUN": "São Paulo",
  "A1_EST": "SP",
  "A1_CEP": "01001000",
  "A1_DDD": "11",
  "A1_TEL": "999999999",
  "A1_EMAIL": "cliente@exemplo.com"
}
```

### 8) JSON de retorno aceito para cliente
Para considerar sucesso na identificação de cliente, o plugin aceita:
- `A1_COD`
- `customer_code`
- `code`

Exemplos válidos:
```json
{ "A1_COD": "000123", "A1_LOJA": "01" }
```

```json
{ "customer_code": "000123" }
```

### 9) JSON esperado no retorno de catálogo
Rota padrão:
- `GET api/ecommerce/v1/retailItem?page=1&pageSize=50`

Coleções aceitas:
- `items`
- `products`
- lista direta no corpo

Exemplo válido com `items`:
```json
{
  "items": [
    {
      "B1_COD": "PROD001",
      "B1_DESC": "Produto Exemplo",
      "B1_PRV1": 99.9,
      "B1_DESCMAR": "Descrição curta",
      "B1_PESO": "1.2",
      "B1_MSBLQL": "2",
      "B1_GRUPO": "GRUPO01",
      "image_url": "https://cdn.exemplo.com/prod001.jpg"
    }
  ],
  "hasNext": true
}
```

### 10) JSON esperado no retorno de estoque
Rota padrão:
- `GET api/ecommerce/v1/stock-product`

Coleções aceitas:
- `items`
- `stock`
- lista direta no corpo

SKU aceito por chave:
- `B2_COD`, `sku`, `productCode`, `code`, `product_code`

Quantidade aceita por chave:
- `B2_QATU`, `quantity`, `stockQuantity`, `availableStock`, `available`, `saldo`

Exemplo válido:
```json
{
  "stock": [
    { "B2_COD": "PROD001", "B2_QATU": 50 },
    { "sku": "PROD002", "quantity": 0 }
  ]
}
```

### 11) JSON recebido pelo plugin no webhook de status
Rota WordPress:
- `POST /wp-json/absloja-protheus/v1/webhook/order-status`

Corpo esperado:
```json
{
  "order_id": "123456",
  "woo_order_id": "789",
  "status": "approved",
  "tracking_code": "BR123456789",
  "invoice_number": "000123",
  "invoice_date": "2026-03-02"
}
```

Campos obrigatórios:
- `woo_order_id`
- `status`

Resposta de sucesso do plugin:
```json
{
  "success": true,
  "message": "Order 789 status updated to processing"
}
```

Respostas de erro comuns:
```json
{ "success": false, "message": "Missing required fields: woo_order_id and status are required" }
```

```json
{ "success": false, "message": "Order not found: 789" }
```

### 12) JSON recebido pelo plugin no webhook de estoque
Rota WordPress:
- `POST /wp-json/absloja-protheus/v1/webhook/stock`

Corpo esperado:
```json
{
  "sku": "PROD001",
  "quantity": 50,
  "warehouse": "01"
}
```

Campos obrigatórios:
- `sku`
- `quantity`

Resposta de sucesso do plugin:
```json
{
  "success": true,
  "message": "Stock updated for product PROD001 (ID: 123) to 50"
}
```

Respostas de erro comuns:
```json
{ "success": false, "message": "Missing required fields: sku and quantity are required" }
```

```json
{ "success": false, "message": "Product not found with SKU: PROD001" }
```

## Regras de autenticação
### API Protheus
- `Basic` ou `OAuth2` conforme configuração no plugin.

### Webhooks de entrada no WordPress
- `X-Protheus-Token`, ou
- `X-Protheus-Signature` com HMAC SHA256.
- Assinatura aceita em dois formatos: `<hash>` e `sha256=<hash>`.

## Compatibilidade de payload
### Coleções aceitas
- Produtos: `items` ou `products`.
- Estoque: `items` ou `stock`.

### Chaves de SKU aceitas
- `B1_COD`, `B2_COD`, `sku`, `productCode`, `code`, `product_code`.

### Chaves de quantidade aceitas
- `B2_QATU`, `quantity`, `stockQuantity`, `availableStock`, `available`, `saldo`.

### Paginação
- Requisição: `page` + `pageSize`.
- Continuação: usa `hasNext` quando disponível; fallback por tamanho do lote.

## Segurança
- Utilizar HTTPS em todo o tráfego.
- Manter segredo de webhook e credenciais protegidos.
- Restringir acesso ao painel administrativo.
- Não versionar `wp-config.php`.
- Monitorar logs e fila de retry.
- Revisar permissões do servidor e plugins ativos.

## Logs, monitoramento e tentativas automáticas
- Logs de requisição API, sincronização, webhook e erro.
- Consulta por filtros e exportação.
- Tentativas automáticas para falhas transitórias (rede, timeout, indisponibilidade).
- Registro de erro de negócio para ação manual quando necessário.

## Diagnóstico e solução de problemas
### Conexão falhando
- Validar `API URL`.
- Validar credenciais/autenticação.
- Testar endpoint de health.
- Verificar firewall, DNS, SSL e timeout.

### Pedido não sincroniza
- Verificar existência de cliente no Protheus.
- Revisar TES, pagamento e mapeamentos.
- Conferir logs de erro de negócio.

### Produto/estoque não atualiza
- Revisar endpoints e chaves do JSON retornado.
- Validar parâmetros de contexto (empresa/filial).
- Conferir frequência de cron e execução de tarefas.

### Webhook rejeitado
- Revisar token/assinatura.
- Validar corpo bruto para HMAC.
- Confirmar cabeçalhos enviados pelo Protheus.

## Inventário de opções do plugin
Todas as opções abaixo são armazenadas no WordPress com prefixo `absloja_protheus_`.

### Conexão
- `auth_type`
- `api_url`
- `username`
- `password`
- `client_id`
- `client_secret`
- `token_endpoint`
- `contract_profile`
- `access_token`
- `token_expires`

### Mapeamentos
- `payment_mapping`
- `category_mapping`
- `tes_rules`
- `status_mapping`
- `customer_mapping`
- `order_mapping`
- `product_mapping`
- `mappings_initialized`

### Agendamento e execução
- `catalog_sync_frequency`
- `stock_sync_frequency`
- `last_catalog_sync`
- `last_stock_sync`
- `products_synced`

### Avançado
- `batch_size`
- `retry_interval`
- `max_retries`
- `log_retention`
- `webhook_token`
- `webhook_secret`
- `image_url_pattern`

### Overrides de endpoint
- `endpoint_orders_create`
- `endpoint_orders_status`
- `endpoint_orders_cancel`
- `endpoint_orders_refund`
- `endpoint_customers`
- `endpoint_products`
- `endpoint_product_by_sku`
- `endpoint_stock`
- `endpoint_health`

### Parâmetros de contexto e documento
- `customer_document_param`
- `company_param`
- `company_value`
- `branch_param`
- `branch_value`

### Controle interno de versão
- `version`

## Inventário de metadados e transientes
### Metadados em pedidos (WooCommerce Order Meta)
- `_protheus_order_id`
- `_protheus_customer_code`
- `_protheus_sync_status`
- `_protheus_sync_error`
- `_protheus_error_type`
- `_protheus_business_error`
- `_protheus_requires_manual_review`
- `_protheus_sync_date`
- `_protheus_tracking_code`
- `_protheus_invoice_number`
- `_protheus_invoice_date`

### Metadados em produtos (WooCommerce Product Meta)
- `_protheus_synced`
- `_protheus_sync_date`
- `_protheus_b1_grupo`
- `_protheus_b1_cod`
- `_protheus_price_locked`
- `_protheus_original_price`

### Metadados de entrada lidos no pedido (origem checkout)
- `_billing_cpf`
- `_billing_cnpj`
- `_billing_document`
- `_billing_persontype`
- `_billing_neighborhood`
- `_billing_bairro`

### Transientes usados pelo plugin
- `absloja_protheus_status_block_{order_id}`
- `protheus_price_restore_notice_{product_id}`

## Checklist de validação funcional
1. Teste de conexão no admin.
2. Execução de sincronização de catálogo.
3. Execução de sincronização de estoque.
4. Criação de pedido real e envio ao Protheus.
5. Recebimento de atualização de status por webhook.
6. Conferência final nos logs sem erro crítico.

## Critério de pronto
A integração é considerada pronta quando:
- autentica com sucesso na API Protheus,
- sincroniza catálogo e estoque,
- envia pedido com retorno válido,
- recebe status via webhook,
- mantém estabilidade operacional sem erro bloqueante.
