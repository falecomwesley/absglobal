# Webhook Endpoints Documentation

## Overview

O plugin ABS Loja Protheus Connector expõe endpoints REST API para receber webhooks do Protheus ERP. Estes webhooks permitem atualizações em tempo real de status de pedidos e estoque.

## Endpoints Disponíveis

### 1. Atualização de Status de Pedido

**Endpoint:** `POST /wp-json/absloja-protheus/v1/webhook/order-status`

**Descrição:** Recebe atualizações de status de pedidos do Protheus e atualiza o pedido correspondente no WooCommerce.

**Autenticação:** Requerida (Token ou HMAC)

**Payload Esperado:**
```json
{
  "order_id": "123456",
  "woo_order_id": "789",
  "status": "approved",
  "tracking_code": "BR123456789",
  "invoice_number": "000123",
  "invoice_date": "2024-01-15"
}
```

**Campos:**
- `woo_order_id` (obrigatório): ID do pedido no WooCommerce
- `status` (obrigatório): Status do pedido no Protheus
- `order_id` (opcional): ID do pedido no Protheus
- `tracking_code` (opcional): Código de rastreamento
- `invoice_number` (opcional): Número da nota fiscal
- `invoice_date` (opcional): Data da nota fiscal

**Mapeamento de Status:**
- `pending` → `pending`
- `approved` → `processing`
- `invoiced` → `completed`
- `shipped` → `completed`
- `cancelled` → `cancelled`
- `rejected` → `failed`

**Respostas:**
- `200 OK`: Status atualizado com sucesso
- `400 Bad Request`: Campos obrigatórios ausentes
- `401 Unauthorized`: Falha na autenticação
- `404 Not Found`: Pedido não encontrado

**Exemplo de Resposta (Sucesso):**
```json
{
  "success": true,
  "message": "Order 789 status updated to processing"
}
```

**Exemplo de Resposta (Erro):**
```json
{
  "success": false,
  "message": "Order not found: 789"
}
```

### 2. Atualização de Estoque

**Endpoint:** `POST /wp-json/absloja-protheus/v1/webhook/stock`

**Descrição:** Recebe atualizações de estoque do Protheus e atualiza o produto correspondente no WooCommerce.

**Autenticação:** Requerida (Token ou HMAC)

**Payload Esperado:**
```json
{
  "sku": "PROD001",
  "quantity": 50,
  "warehouse": "01"
}
```

**Campos:**
- `sku` (obrigatório): SKU do produto
- `quantity` (obrigatório): Quantidade em estoque
- `warehouse` (opcional): Código do armazém

**Comportamento:**
- Se `quantity = 0`: Produto é ocultado (visibility = hidden)
- Se `quantity > 0` e produto estava oculto: Visibilidade é restaurada (visibility = visible)

**Respostas:**
- `200 OK`: Estoque atualizado com sucesso
- `400 Bad Request`: Campos obrigatórios ausentes
- `401 Unauthorized`: Falha na autenticação
- `404 Not Found`: Produto não encontrado

**Exemplo de Resposta (Sucesso):**
```json
{
  "success": true,
  "message": "Stock updated for product PROD001 (ID: 123) to 50"
}
```

## Autenticação

O plugin suporta dois métodos de autenticação para webhooks:

### Método 1: Token-Based Authentication

Envie um token secreto no header `X-Protheus-Token`:

```
POST /wp-json/absloja-protheus/v1/webhook/order-status
X-Protheus-Token: seu-token-secreto-aqui
Content-Type: application/json

{
  "woo_order_id": 789,
  "status": "approved"
}
```

**Configuração:**
1. Acesse as configurações do plugin no WordPress admin
2. Vá para a aba "Webhooks"
3. Gere ou configure o "Webhook Token"
4. Configure o mesmo token no Protheus para enviar nos webhooks

### Método 2: HMAC Signature Authentication

Envie uma assinatura HMAC-SHA256 do payload no header `X-Protheus-Signature`:

```
POST /wp-json/absloja-protheus/v1/webhook/order-status
X-Protheus-Signature: assinatura-hmac-sha256-aqui
Content-Type: application/json

{
  "woo_order_id": 789,
  "status": "approved"
}
```

**Como Gerar a Assinatura:**

```javascript
// Exemplo em JavaScript/Node.js
const crypto = require('crypto');

const payload = JSON.stringify({
  woo_order_id: 789,
  status: 'approved'
});

const secret = 'seu-webhook-secret';
const signature = crypto
  .createHmac('sha256', secret)
  .update(payload)
  .digest('hex');

// Envie 'signature' no header X-Protheus-Signature
```

```php
// Exemplo em PHP (para Protheus AdvPL via REST)
$payload = json_encode([
    'woo_order_id' => 789,
    'status' => 'approved'
]);

$secret = 'seu-webhook-secret';
$signature = hash_hmac('sha256', $payload, $secret);

// Envie $signature no header X-Protheus-Signature
```

**Configuração:**
1. Acesse as configurações do plugin no WordPress admin
2. Vá para a aba "Webhooks"
3. Gere ou configure o "Webhook Secret"
4. Configure o mesmo secret no Protheus para gerar as assinaturas

## Configuração no Protheus

### Exemplo de Código AdvPL para Enviar Webhook

```advpl
#Include "protheus.ch"
#Include "restful.ch"

/*/{Protheus.doc} SendOrderStatusWebhook
Envia webhook de atualização de status de pedido para WooCommerce
@type function
@author Seu Nome
@since 01/01/2024
@param cOrderId, character, ID do pedido no Protheus
@param cWooOrderId, character, ID do pedido no WooCommerce
@param cStatus, character, Status do pedido
@return logical, .T. se sucesso, .F. se erro
/*/
User Function SendOrderStatusWebhook(cOrderId, cWooOrderId, cStatus)
    Local cUrl := "https://seu-site.com.br/wp-json/absloja-protheus/v1/webhook/order-status"
    Local cToken := "seu-token-secreto"
    Local oRest
    Local cJson
    Local aHeader := {}
    
    // Monta o JSON do payload
    cJson := '{'
    cJson += '"order_id":"' + AllTrim(cOrderId) + '",'
    cJson += '"woo_order_id":"' + AllTrim(cWooOrderId) + '",'
    cJson += '"status":"' + AllTrim(cStatus) + '"'
    cJson += '}'
    
    // Configura headers
    aAdd(aHeader, "Content-Type: application/json")
    aAdd(aHeader, "X-Protheus-Token: " + cToken)
    
    // Cria objeto REST
    oRest := FWRest():New(cUrl)
    oRest:setPostParams(cJson)
    
    // Envia requisição POST
    If oRest:Post(aHeader)
        ConOut("Webhook enviado com sucesso: " + oRest:GetResult())
        Return .T.
    Else
        ConOut("Erro ao enviar webhook: " + oRest:GetLastError())
        Return .F.
    EndIf
Return .F.

/*/{Protheus.doc} SendStockWebhook
Envia webhook de atualização de estoque para WooCommerce
@type function
@author Seu Nome
@since 01/01/2024
@param cSku, character, SKU do produto
@param nQuantity, numeric, Quantidade em estoque
@return logical, .T. se sucesso, .F. se erro
/*/
User Function SendStockWebhook(cSku, nQuantity)
    Local cUrl := "https://seu-site.com.br/wp-json/absloja-protheus/v1/webhook/stock"
    Local cToken := "seu-token-secreto"
    Local oRest
    Local cJson
    Local aHeader := {}
    
    // Monta o JSON do payload
    cJson := '{'
    cJson += '"sku":"' + AllTrim(cSku) + '",'
    cJson += '"quantity":' + cValToChar(nQuantity)
    cJson += '}'
    
    // Configura headers
    aAdd(aHeader, "Content-Type: application/json")
    aAdd(aHeader, "X-Protheus-Token: " + cToken)
    
    // Cria objeto REST
    oRest := FWRest():New(cUrl)
    oRest:setPostParams(cJson)
    
    // Envia requisição POST
    If oRest:Post(aHeader)
        ConOut("Webhook de estoque enviado com sucesso: " + oRest:GetResult())
        Return .T.
    Else
        ConOut("Erro ao enviar webhook de estoque: " + oRest:GetLastError())
        Return .F.
    EndIf
Return .F.
```

## Logs

Todos os webhooks recebidos são registrados no sistema de logs do plugin:

1. Acesse o WordPress admin
2. Vá para WooCommerce → Protheus Connector → Logs
3. Filtre por tipo "webhook"
4. Visualize detalhes de cada webhook recebido

Os logs incluem:
- Timestamp de recebimento
- Payload completo
- Resposta enviada
- Status (sucesso/erro)
- Mensagens de erro (se houver)

## Testes

### Testando com cURL

**Teste de Atualização de Status:**
```bash
curl -X POST https://seu-site.com.br/wp-json/absloja-protheus/v1/webhook/order-status \
  -H "Content-Type: application/json" \
  -H "X-Protheus-Token: seu-token-secreto" \
  -d '{
    "woo_order_id": 123,
    "status": "approved",
    "tracking_code": "BR123456789"
  }'
```

**Teste de Atualização de Estoque:**
```bash
curl -X POST https://seu-site.com.br/wp-json/absloja-protheus/v1/webhook/stock \
  -H "Content-Type: application/json" \
  -H "X-Protheus-Token: seu-token-secreto" \
  -d '{
    "sku": "PROD001",
    "quantity": 50
  }'
```

### Testando com Postman

1. Crie uma nova requisição POST
2. URL: `https://seu-site.com.br/wp-json/absloja-protheus/v1/webhook/order-status`
3. Headers:
   - `Content-Type: application/json`
   - `X-Protheus-Token: seu-token-secreto`
4. Body (raw JSON):
```json
{
  "woo_order_id": 123,
  "status": "approved"
}
```
5. Envie a requisição e verifique a resposta

## Troubleshooting

### Erro 401 Unauthorized

**Causa:** Token ou assinatura HMAC inválidos

**Solução:**
1. Verifique se o token/secret está configurado corretamente no WordPress
2. Verifique se o Protheus está enviando o header correto
3. Para HMAC, verifique se a assinatura está sendo calculada corretamente

### Erro 404 Not Found (Pedido/Produto)

**Causa:** Pedido ou produto não existe no WooCommerce

**Solução:**
1. Verifique se o `woo_order_id` ou `sku` está correto
2. Verifique se o pedido/produto não foi deletado
3. Consulte os logs para mais detalhes

### Webhook não está sendo recebido

**Causa:** Problemas de rede ou configuração

**Solução:**
1. Verifique se a URL está correta
2. Verifique se o WordPress está acessível externamente
3. Verifique logs do servidor web (Apache/Nginx)
4. Teste com cURL para isolar o problema

### Produto não está sendo ocultado quando estoque = 0

**Causa:** Configuração de visibilidade do produto

**Solução:**
1. Verifique se o produto tem "Gerenciar estoque" habilitado
2. Verifique os logs do webhook para confirmar que foi processado
3. Verifique manualmente a visibilidade do produto no admin

## Segurança

### Boas Práticas

1. **Use HTTPS:** Sempre use HTTPS para webhooks em produção
2. **Tokens Fortes:** Gere tokens com pelo menos 32 caracteres aleatórios
3. **Rotação de Tokens:** Altere tokens periodicamente
4. **Whitelist de IPs:** Configure firewall para aceitar webhooks apenas do IP do Protheus
5. **Monitoramento:** Monitore logs regularmente para detectar tentativas de acesso não autorizado

### Gerando Tokens Seguros

```bash
# Linux/Mac
openssl rand -hex 32

# Ou use o gerador do WordPress
# Acesse: https://api.wordpress.org/secret-key/1.1/salt/
```

## Suporte

Para problemas ou dúvidas sobre os webhooks:

1. Consulte os logs do plugin
2. Verifique a documentação do Protheus REST API
3. Entre em contato com o suporte técnico
