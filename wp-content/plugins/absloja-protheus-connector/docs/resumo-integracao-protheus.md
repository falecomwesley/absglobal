# Resumo de Integração Protheus

## Endereço do site (onde o plugin está instalado)
- https://absloja.jjconsulting.com.br/

## URL da API Protheus
- Configurada no campo `API URL` do plugin.
- Valor de produção: `PREENCHER_COM_URL_DA_API_PROTHEUS`.

## Situação atual
- Plugin preparado para integração WooCommerce + Protheus.
- Contrato padrão configurado para TOTVS E-commerce.
- Fluxos de pedido, cliente, catálogo, estoque e webhook implementados.

## Pontos principais
- Pedido: envio do WooCommerce para o Protheus.
- Cliente: consulta/criação automática no fluxo de pedido.
- Catálogo: sincronização paginada com atualização de produtos.
- Estoque: atualização de saldo e visibilidade de produto.
- Webhook: atualização de status e estoque com autenticação por token ou HMAC.
- Operação: logs e retry para falhas transitórias.

## Endpoints padrão
- `api/ecommerce/v1/retailSalesOrders`
- `api/ecommerce/v1/orderChangeStatus`
- `api/ecommerce/v1/retailItem`
- `api/ecommerce/v1/retailItem/{sku}`
- `api/ecommerce/v1/stock-product`

## Premissa da integração
- As APIs do Protheus já estão prontas no ambiente.
- Não é necessário criar novos endpoints no Protheus para este escopo.

## Matriz rápida (endpoint x JSON)
| Endpoint | Método | JSON enviado pelo plugin | JSON esperado do Protheus |
|---|---|---|---|
| `api/ecommerce/v1/retailSalesOrders` | `POST` | `header` + `items` (campos SC5/SC6) | Sucesso `2xx` com identificador em `C5_NUM` ou `order_id` ou `order_number` ou `id` |
| `api/ecommerce/v1/orderChangeStatus` | `POST` | Status: `{ \"order_id\", \"status\" }` / Cancelamento: `{ \"order_id\", \"action\":\"cancel\", \"reason\" }` / Reembolso: `{ \"order_id\", \"action\":\"refund\", \"amount\", \"reason\" }` | Sucesso `2xx` com JSON válido |
| `api/v1/customers` | `GET` | Query por documento: ex. `?cgc=...` (com contexto empresa/filial se configurado) | Cliente em `A1_COD` (ou lista com `A1_COD`) |
| `api/v1/customers` | `POST` | Campos SA1 (`A1_NOME`, `A1_CGC`, `A1_END`, `A1_EMAIL` etc.) | Código de cliente em `A1_COD` ou `customer_code` ou `code` |
| `api/ecommerce/v1/retailItem` | `GET` | Query `page` + `pageSize` | Coleção em `items` ou `products` (ou lista direta) |
| `api/ecommerce/v1/retailItem/{sku}` | `GET` | Sem corpo | Produto único por SKU |
| `api/ecommerce/v1/stock-product` | `GET` | Sem corpo (com contexto opcional) | Coleção em `items` ou `stock` (ou lista direta), com SKU e quantidade |

## Configuração essencial
No admin do WordPress (`WooCommerce > Protheus Connector`):
1. Definir `API URL` da instância Protheus.
2. Definir autenticação (`Basic` ou `OAuth2`).
3. Manter perfil `totvs_ecommerce_v1`.
4. Ajustar parâmetros de empresa/filial se necessário.

## Segurança e operação
- HTTPS obrigatório.
- Credenciais e segredo de webhook protegidos.
- Monitoramento contínuo de logs.
- Reprocessamento de erros transitórios habilitado.

## Validação final
Para considerar a integração validada em produção:
1. conexão testada com sucesso,
2. catálogo e estoque sincronizados,
3. pedido enviado com retorno do Protheus,
4. status atualizado via webhook,
5. logs sem erro crítico.
