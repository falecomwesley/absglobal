# Guia Único - Integração Protheus TOTVS (WooCommerce)

Data: 2026-03-02
Escopo: Operação do plugin ABS Loja Protheus Connector em produção

## 1. Objetivo
Garantir sincronização estável entre WooCommerce e Protheus usando o contrato TOTVS E-commerce.

## 2. Contrato padrão implementado no plugin
- Pedidos: `api/ecommerce/v1/retailSalesOrders`
- Alteração de status/cancelamento/reembolso: `api/ecommerce/v1/orderChangeStatus`
- Produtos: `api/ecommerce/v1/retailItem`
- Produto por SKU: `api/ecommerce/v1/retailItem/{sku}`
- Estoque: `api/ecommerce/v1/stock-product`
- Healthcheck (configurável): `api/v1/health`

## 3. Configuração no WordPress
Menu: `WooCommerce > Protheus Connector`

### Connection
1. Preencher `API URL` da sua instância Protheus
2. Definir autenticação:
- `Basic` (usuário/senha), ou
- `OAuth2` (client_id/client_secret/token_endpoint)
3. Manter `API Contract Profile = totvs_ecommerce_v1` (recomendado)

### Advanced
1. Usar override de endpoint apenas se sua instância tiver rotas diferentes
2. Ajustar parâmetro de documento de cliente quando necessário (ex.: `cgc`)
3. Configurar contexto de query se sua API exigir:
- `company_param` + `company_value`
- `branch_param` + `branch_value`

## 4. Comportamento de sincronização
### Catálogo
- Paginação: `page` + `pageSize`
- Coleções aceitas: `items` ou `products`
- Paginação avançada: lê `hasNext` quando existir

### Estoque
- Coleções aceitas: `items` ou `stock`
- SKU aceito em: `B2_COD`, `sku`, `productCode`, `code`
- Quantidade aceita em: `B2_QATU`, `quantity`, `stockQuantity`, `availableStock`

## 5. Webhooks de entrada (WordPress)
- `POST /wp-json/absloja-protheus/v1/webhook/order-status`
- `POST /wp-json/absloja-protheus/v1/webhook/stock`

Autenticação:
- `X-Protheus-Token`, ou
- `X-Protheus-Signature` com HMAC SHA256
- Assinatura aceita em dois formatos: `sha256=<hash>` ou `<hash>`

## 6. Checklist de go-live
1. Salvar `API URL` real
2. Salvar credenciais válidas
3. Testar conexão no painel
4. Executar sync de catálogo
5. Executar sync de estoque
6. Criar pedido real de teste
7. Confirmar logs sem erro

## 7. Critérios de pronto
A integração só é considerada 100% funcional quando houver:
- conexão autenticada com API Protheus,
- sincronização real de produto e estoque,
- criação de pedido e retorno de status validados em produção.
