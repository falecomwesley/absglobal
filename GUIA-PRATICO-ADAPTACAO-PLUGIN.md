# Guia Prático - Configuração do Plugin para Protheus TOTVS

Data: 2026-03-02

## 1) Configurar conexão
No WordPress:
- `WooCommerce > Protheus Connector > Connection`
- Preencher `API URL` com a URL real da API Protheus da sua empresa
- Escolher autenticação (`Basic` ou `OAuth2`)
- Em OAuth2, preencher `client_id`, `client_secret`, `token_endpoint`

## 2) Confirmar contrato
- Manter `API Contract Profile = TOTVS E-commerce v1`
- Só usar `custom` se a sua instância tiver rotas diferentes

## 3) Ajustar endpoints (se necessário)
No tab `Advanced`, usar overrides apenas quando exigido pelo ambiente:
- `orders_create`
- `orders_status`
- `customers`
- `products`
- `product_by_sku`
- `stock`

## 4) Ajustar parâmetros de contexto
Se sua API exige empresa/filial em query:
- `company_param` + `company_value`
- `branch_param` + `branch_value`

## 5) Webhooks
Endpoints WordPress:
- `/wp-json/absloja-protheus/v1/webhook/order-status`
- `/wp-json/absloja-protheus/v1/webhook/stock`

Autenticação aceita:
- `X-Protheus-Token`
- `X-Protheus-Signature` (`sha256=<hash>` ou `<hash>`)

## 6) Checklist de validação
1. Testar conexão no admin
2. Rodar sync de catálogo
3. Rodar sync de estoque
4. Criar 1 pedido de teste
5. Confirmar log de integração sem erro

## Observação
Não usar URL fixa `https://api.totvs.com.br` como endpoint de operação do cliente, a menos que seu contrato/tenant realmente use esse host para chamadas transacionais.
