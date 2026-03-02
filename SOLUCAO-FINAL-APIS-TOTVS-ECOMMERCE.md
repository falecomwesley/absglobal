# Solução Final - APIs TOTVS E-commerce

Data: 2026-03-02
Status: Implementado no plugin e deployado em produção

## Decisão técnica
O plugin foi simplificado para operar com contrato TOTVS E-commerce por padrão, com overrides de endpoint no admin quando necessário.

## Implementado
- Resolvedor de contrato TOTVS (`totvs_ecommerce_v1`)
- Endpoints padrão de pedido/status/produto/estoque
- Paginação `page/pageSize`
- Parser de resposta compatível com `items/products/stock` + `hasNext`
- Webhook HMAC aceitando `sha256=<hash>`
- Campos administrativos para override de endpoint e parâmetros de contexto

## Benefício
- Menos lógica paralela
- Menos risco de divergência de contrato
- Ajuste rápido por configuração sem editar código

## Ponto pendente de ativação completa
Em produção, o plugin precisa de:
- `absloja_protheus_api_url` (endpoint real da sua API Protheus)
- credenciais válidas (Basic/OAuth2)

Sem isso, o código está correto, mas sem conexão real para sincronizar.
