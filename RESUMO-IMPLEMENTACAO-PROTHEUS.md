# Resumo Único - Status da Integração Protheus

Data: 2026-03-02

## Status atual
- Plugin alinhado ao contrato TOTVS E-commerce
- Deploy em produção concluído
- Documentação consolidada

## Implementado
- Endpoints padrão TOTVS para pedido/status/produto/estoque
- Resolvedor de contrato com overrides no admin
- Paginação e parser de payloads flexíveis (`items/products/stock`, `hasNext`)
- Webhook com autenticação por token e HMAC (`sha256=<hash>` suportado)

## O que falta para validação final em produção
- Configurar `absloja_protheus_api_url`
- Configurar credenciais reais (Basic ou OAuth2)
- Rodar teste ponta a ponta (pedido + produto + estoque)

## APIs relevantes ao plugin
- `RetailSalesOrders`
- `RetailItem`
- `ECommerceStockProduct` / `stock-product`
- `OrderChangeStatus`

## Fora do escopo principal da loja
APIs de domínios MRP, contábil, compras, relatórios e módulos de sistema não são núcleo da integração WooCommerce.
