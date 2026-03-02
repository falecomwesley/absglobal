# Guia de APIs TOTVS Nativas (Filtro para este plugin)

Data: 2026-03-02

## APIs relevantes para o plugin de loja
- `RetailSalesOrders_v1_000` (pedido)
- `RetailItem_v1_000` (produto/catálogo)
- `ECommerceStockProduct_v1_000` (estoque)
- `OrderChangeStatus` (alterações de status)

## APIs não prioritárias para este plugin
As APIs abaixo pertencem a outros domínios e não são núcleo do fluxo WooCommerce:
- `MRPWareHouseInventoryPolicies_v1_000`
- `SystemModules_v1_000`
- `CatReport_v1_000`
- `Annotation_v1_000`
- `CottonGinBreakPointings_v1_000`
- `Events_v1_000` (pode ser complementar, não obrigatório)
- `PurchaseGroups_v1_000`
- `MRPProduct_v1_000`
- `AccountingItems_v1_000`

## Regra prática
Se não impacta diretamente:
- criação/sincronização de pedidos,
- sincronização de produto,
- sincronização de estoque,
- retorno de status para WooCommerce,
então não entra no escopo principal do plugin.

## Links de referência
- https://api.totvs.com.br/
- https://api.totvs.com.br/referencelist
- https://api.totvs.com.br/apidetails/RetailSalesOrders_v1_000.json
- https://api.totvs.com.br/apidetails/RetailItem_v1_000.json
