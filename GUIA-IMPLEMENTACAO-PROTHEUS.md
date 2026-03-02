# Guia de Implementação Protheus TOTVS (Atualizado)

Data: 2026-03-02
Projeto: ABS Loja (WooCommerce + Protheus)

## Objetivo
Este guia descreve a implementação final do plugin **focado em TOTVS E-commerce API**.

## Contrato adotado no plugin
- Pedidos: `api/ecommerce/v1/retailSalesOrders`
- Mudança de status/cancelamento/reembolso: `api/ecommerce/v1/orderChangeStatus`
- Produtos: `api/ecommerce/v1/retailItem`
- Produto por SKU: `api/ecommerce/v1/retailItem/{sku}`
- Estoque: `api/ecommerce/v1/stock-product`
- Healthcheck (configurável): `api/v1/health`

## O que está dinâmico (admin do plugin)
Em `WooCommerce > Protheus Connector`:
- Perfil de contrato: `totvs_ecommerce_v1` ou `custom`
- Override de endpoints (se sua instância Protheus tiver rotas diferentes)
- Parâmetro de documento cliente (ex.: `cgc`, `cpfCnpj`)
- Parâmetros de contexto (empresa/filial) para query string

## Formato esperado pelo plugin
### Catálogo
- Aceita coleções em `items` ou `products`
- Paginação com `page` + `pageSize`
- Reconhece `hasNext` quando disponível

### Estoque
- Aceita coleções em `items` ou `stock`
- SKU: `B2_COD`, `sku`, `productCode`, `code`
- Quantidade: `B2_QATU`, `quantity`, `stockQuantity`, `availableStock`

## Webhooks WordPress (entrada)
- `POST /wp-json/absloja-protheus/v1/webhook/order-status`
- `POST /wp-json/absloja-protheus/v1/webhook/stock`

Autenticação:
- `X-Protheus-Token` ou
- `X-Protheus-Signature` (HMAC SHA256)
- Formato aceito para assinatura: `sha256=<hash>` ou `<hash>`

## Passos para produção
1. Configurar `API URL` do Protheus
2. Configurar autenticação (Basic ou OAuth2)
3. Confirmar endpoints (usar defaults ou overrides)
4. Configurar company/branch params se exigidos pela sua API
5. Testar conexão
6. Rodar sync de catálogo e estoque
7. Testar pedido real ponta a ponta

## Observação importante
Sem `API URL` e credenciais reais do Protheus, o plugin não consegue executar sincronização real.
