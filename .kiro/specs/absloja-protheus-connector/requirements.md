# Requirements Document

## Introduction

Este documento especifica os requisitos para o plugin WordPress "ABS Loja Protheus Connector", que integra WooCommerce com TOTVS Protheus ERP através de REST API. O plugin automatiza o fluxo de vendas, sincronização de catálogo, estoque e cadastro de clientes entre as plataformas.

## Glossary

- **Plugin**: O sistema WordPress "ABS Loja Protheus Connector"
- **WooCommerce**: Plataforma de e-commerce WordPress
- **Protheus**: Sistema ERP TOTVS com REST API
- **Order_Sync**: Módulo de sincronização de pedidos
- **Catalog_Sync**: Módulo de sincronização de catálogo
- **Customer_Sync**: Módulo de sincronização de clientes
- **Webhook_Handler**: Módulo receptor de webhooks do Protheus
- **Logger**: Sistema de registro de transações
- **Retry_Manager**: Sistema de reprocessamento de falhas
- **Mapping_Engine**: Motor de mapeamento de campos entre sistemas
- **Auth_Manager**: Gerenciador de autenticação com Protheus API

## Requirements

### Requirement 1: Sincronização de Pedidos para Protheus

**User Story:** Como administrador da loja, quero que pedidos WooCommerce sejam enviados automaticamente ao Protheus, para que o ERP processe as vendas sem intervenção manual.

#### Acceptance Criteria

1. WHEN a WooCommerce order status changes to "processing", THE Order_Sync SHALL send the order data to Protheus REST API
2. WHEN sending an order, THE Order_Sync SHALL map WooCommerce order fields to Protheus SC5 table fields according to the defined mapping
3. WHEN sending an order, THE Order_Sync SHALL map WooCommerce line items to Protheus SC6 table fields according to the defined mapping
4. WHEN the Protheus API returns success, THE Order_Sync SHALL store the Protheus order number in WooCommerce order metadata
5. IF the Protheus API returns an error, THEN THE Order_Sync SHALL log the error and schedule a retry
6. THE Order_Sync SHALL include the WooCommerce Order ID in the C5_PEDWOO field
7. THE Order_Sync SHALL map payment methods to Protheus payment conditions using a configurable mapping table
8. THE Order_Sync SHALL determine the TES (Tipo de Entrada/Saída) automatically based on customer billing state

### Requirement 2: Cadastro Automático de Clientes

**User Story:** Como administrador da loja, quero que clientes sejam cadastrados automaticamente no Protheus, para que não seja necessário duplicar o cadastro manualmente.

#### Acceptance Criteria

1. WHEN sending an order to Protheus, THE Customer_Sync SHALL check if the customer exists in Protheus using CPF or CNPJ
2. IF the customer does not exist in Protheus, THEN THE Customer_Sync SHALL create a new customer record in SA1 table before sending the order
3. WHEN creating a customer, THE Customer_Sync SHALL map WooCommerce billing fields to Protheus SA1 fields according to the defined mapping
4. THE Customer_Sync SHALL extract CPF from _billing_cpf field or CNPJ from _billing_cnpj field and map to A1_CGC
5. THE Customer_Sync SHALL concatenate _billing_first_name and _billing_last_name to populate A1_NOME
6. IF customer creation fails, THEN THE Customer_Sync SHALL abort the order sync and log the error

### Requirement 3: Sincronização de Catálogo do Protheus

**User Story:** Como administrador da loja, quero que produtos do Protheus sejam sincronizados automaticamente no WooCommerce, para que o catálogo esteja sempre atualizado.

#### Acceptance Criteria

1. WHEN the catalog sync process runs, THE Catalog_Sync SHALL fetch product data from Protheus SB1 table via REST API
2. WHEN receiving product data, THE Catalog_Sync SHALL map Protheus SB1 fields to WooCommerce product fields according to the defined mapping
3. IF a product with matching SKU exists in WooCommerce, THEN THE Catalog_Sync SHALL update the existing product
4. IF a product with matching SKU does not exist in WooCommerce, THEN THE Catalog_Sync SHALL create a new product
5. THE Catalog_Sync SHALL map B1_COD to WooCommerce SKU field
6. THE Catalog_Sync SHALL map B1_DESC to WooCommerce product name
7. THE Catalog_Sync SHALL map B1_PRV1 to WooCommerce regular price
8. WHEN B1_MSBLQL indicates a blocked product, THE Catalog_Sync SHALL set the WooCommerce product status to "draft"
9. THE Catalog_Sync SHALL map B1_GRUPO to WooCommerce categories using a configurable mapping table
10. THE Catalog_Sync SHALL prevent manual price editing in WooCommerce admin for synced products

### Requirement 4: Sincronização de Estoque

**User Story:** Como administrador da loja, quero que o estoque do WooCommerce reflita o estoque do Protheus, para evitar vendas de produtos indisponíveis.

#### Acceptance Criteria

1. WHEN the stock sync process runs, THE Catalog_Sync SHALL fetch stock quantities from Protheus SB2 table via REST API
2. WHEN receiving stock data, THE Catalog_Sync SHALL update WooCommerce stock quantity with the B2_QATU value
3. WHEN a product stock quantity reaches zero, THE Catalog_Sync SHALL set the product visibility to "hidden" in WooCommerce
4. WHEN a previously out-of-stock product receives stock, THE Catalog_Sync SHALL restore the product visibility in WooCommerce
5. THE Catalog_Sync SHALL map product by matching B2_COD with WooCommerce SKU

### Requirement 5: Webhook para Atualização de Status de Pedidos

**User Story:** Como administrador da loja, quero que o Protheus possa atualizar o status dos pedidos no WooCommerce, para que os clientes vejam o andamento de seus pedidos.

#### Acceptance Criteria

1. THE Webhook_Handler SHALL expose a REST API endpoint to receive order status updates from Protheus
2. WHEN receiving a status update webhook, THE Webhook_Handler SHALL authenticate the request using the configured authentication method
3. WHEN receiving a valid status update, THE Webhook_Handler SHALL locate the WooCommerce order using the C5_PEDWOO field
4. WHEN the order is found, THE Webhook_Handler SHALL update the WooCommerce order status according to the received Protheus status
5. IF authentication fails, THEN THE Webhook_Handler SHALL return HTTP 401 Unauthorized
6. IF the order is not found, THEN THE Webhook_Handler SHALL return HTTP 404 Not Found and log the error
7. WHEN the status update succeeds, THE Webhook_Handler SHALL return HTTP 200 OK

### Requirement 6: Webhook para Atualização de Estoque em Tempo Real

**User Story:** Como administrador da loja, quero que o Protheus possa atualizar o estoque em tempo real no WooCommerce, para que as informações estejam sempre precisas.

#### Acceptance Criteria

1. THE Webhook_Handler SHALL expose a REST API endpoint to receive stock updates from Protheus
2. WHEN receiving a stock update webhook, THE Webhook_Handler SHALL authenticate the request using the configured authentication method
3. WHEN receiving a valid stock update, THE Webhook_Handler SHALL locate the WooCommerce product using the SKU field
4. WHEN the product is found, THE Webhook_Handler SHALL update the WooCommerce stock quantity with the received value
5. WHEN stock reaches zero, THE Webhook_Handler SHALL set the product visibility to "hidden"
6. IF authentication fails, THEN THE Webhook_Handler SHALL return HTTP 401 Unauthorized
7. WHEN the stock update succeeds, THE Webhook_Handler SHALL return HTTP 200 OK

### Requirement 7: Autenticação com Protheus API

**User Story:** Como administrador da loja, quero que o plugin se autentique de forma segura com a API do Protheus, para proteger os dados da integração.

#### Acceptance Criteria

1. THE Auth_Manager SHALL support Basic Authentication for Protheus API requests
2. WHERE OAuth2 is configured, THE Auth_Manager SHALL support OAuth2 authentication for Protheus API requests
3. THE Auth_Manager SHALL store API credentials securely in WordPress options table
4. WHEN making API requests, THE Auth_Manager SHALL include authentication headers in all requests to Protheus
5. IF authentication fails, THEN THE Auth_Manager SHALL log the failure and return an error to the calling module
6. THE Auth_Manager SHALL provide a configuration interface in WordPress admin for entering API credentials

### Requirement 8: Sistema de Logs de Transação

**User Story:** Como administrador da loja, quero visualizar logs detalhados de todas as transações da integração, para diagnosticar problemas e auditar operações.

#### Acceptance Criteria

1. THE Logger SHALL record all API requests sent to Protheus with timestamp, endpoint, payload, and response
2. THE Logger SHALL record all webhook requests received from Protheus with timestamp, endpoint, payload, and response
3. THE Logger SHALL record all sync operations with timestamp, operation type, affected records, and result status
4. THE Logger SHALL record all errors with timestamp, error message, stack trace, and context data
5. THE Logger SHALL provide a log viewer interface in WordPress admin
6. THE Logger SHALL allow filtering logs by date range, operation type, and status
7. THE Logger SHALL allow exporting logs to CSV format
8. WHILE log storage exceeds 1000 entries, THE Logger SHALL automatically delete logs older than 30 days

### Requirement 9: Sistema de Retry Automático

**User Story:** Como administrador da loja, quero que operações falhadas sejam reprocessadas automaticamente, para que problemas temporários não causem perda de dados.

#### Acceptance Criteria

1. WHEN an order sync fails, THE Retry_Manager SHALL schedule a retry attempt using WP-Cron
2. THE Retry_Manager SHALL retry failed operations every 1 hour
3. THE Retry_Manager SHALL attempt a maximum of 5 retries for each failed operation
4. WHEN all retry attempts are exhausted, THE Retry_Manager SHALL mark the operation as permanently failed and send an admin notification
5. WHEN a retry succeeds, THE Retry_Manager SHALL remove the operation from the retry queue and log the success
6. THE Retry_Manager SHALL provide an admin interface to view pending retries
7. THE Retry_Manager SHALL allow manual retry triggering from the admin interface

### Requirement 10: Mapeamento Configurável de Campos

**User Story:** Como administrador da loja, quero configurar o mapeamento de campos entre WooCommerce e Protheus, para adaptar a integração às necessidades específicas do negócio.

#### Acceptance Criteria

1. THE Mapping_Engine SHALL provide a configuration interface for customer field mappings (WooCommerce → Protheus SA1)
2. THE Mapping_Engine SHALL provide a configuration interface for order field mappings (WooCommerce → Protheus SC5/SC6)
3. THE Mapping_Engine SHALL provide a configuration interface for product field mappings (Protheus SB1 → WooCommerce)
4. THE Mapping_Engine SHALL provide a configuration interface for payment method mappings (WooCommerce → Protheus payment conditions)
5. THE Mapping_Engine SHALL provide a configuration interface for category mappings (Protheus B1_GRUPO → WooCommerce categories)
6. THE Mapping_Engine SHALL provide a configuration interface for TES rules based on customer state
7. THE Mapping_Engine SHALL load default mappings based on the provided technical specifications
8. THE Mapping_Engine SHALL validate mapping configurations and display errors for invalid mappings

### Requirement 11: Agendamento de Sincronização

**User Story:** Como administrador da loja, quero configurar a frequência de sincronização de catálogo e estoque, para balancear atualização e performance.

#### Acceptance Criteria

1. THE Plugin SHALL provide a configuration interface for catalog sync frequency
2. THE Plugin SHALL provide a configuration interface for stock sync frequency
3. THE Plugin SHALL support sync frequencies of: 15 minutes, 30 minutes, 1 hour, 6 hours, 12 hours, and 24 hours
4. THE Plugin SHALL use WP-Cron to schedule automatic sync operations
5. THE Plugin SHALL provide a manual sync button in the admin interface for immediate synchronization
6. WHEN manual sync is triggered, THE Plugin SHALL execute the sync operation immediately and display the result

### Requirement 12: Tratamento de Erros de Negócio

**User Story:** Como administrador da loja, quero que o plugin trate erros de negócio do Protheus de forma inteligente, para evitar bloqueios operacionais.

#### Acceptance Criteria

1. IF Protheus returns a TES error, THEN THE Order_Sync SHALL log the error with details and mark the order for manual review
2. IF Protheus returns a stock insufficient error, THEN THE Order_Sync SHALL log the error and update WooCommerce stock to prevent further sales
3. IF Protheus API is unreachable, THEN THE Plugin SHALL log the connectivity error and schedule retry attempts
4. WHEN a business error occurs, THE Plugin SHALL add an admin note to the WooCommerce order with error details
5. THE Plugin SHALL provide an admin dashboard widget showing orders pending manual review due to errors

### Requirement 13: Painel de Configuração Administrativa

**User Story:** Como administrador da loja, quero um painel centralizado para configurar todos os aspectos da integração, para facilitar a gestão do plugin.

#### Acceptance Criteria

1. THE Plugin SHALL provide a settings page in WordPress admin under WooCommerce menu
2. THE Plugin SHALL organize settings into tabs: Connection, Mappings, Sync Schedule, Logs, and Advanced
3. THE Plugin SHALL display connection status with Protheus API on the Connection tab
4. THE Plugin SHALL provide a "Test Connection" button to verify Protheus API connectivity
5. THE Plugin SHALL display sync statistics on the dashboard: last sync time, products synced, orders synced, and errors
6. THE Plugin SHALL validate all configuration inputs and display error messages for invalid values
7. THE Plugin SHALL provide contextual help text for each configuration option

### Requirement 14: Gestão de Imagens de Produtos

**User Story:** Como administrador da loja, quero uma solução para gerenciar imagens de produtos, já que o Protheus não armazena imagens web-friendly.

#### Acceptance Criteria

1. THE Catalog_Sync SHALL support an optional external image URL field mapping from Protheus
2. WHEN an external image URL is provided, THE Catalog_Sync SHALL download and attach the image to the WooCommerce product
3. IF no external image URL is provided, THEN THE Catalog_Sync SHALL preserve existing WooCommerce product images
4. THE Plugin SHALL provide a configuration option to specify an image URL pattern using product SKU as variable
5. THE Plugin SHALL allow manual image upload in WooCommerce admin for products without external images

### Requirement 15: Sincronização Bidirecional de Status

**User Story:** Como administrador da loja, quero que mudanças de status no WooCommerce sejam refletidas no Protheus, para manter ambos os sistemas sincronizados.

#### Acceptance Criteria

1. WHEN a WooCommerce order status changes to "cancelled", THE Order_Sync SHALL send a cancellation request to Protheus
2. WHEN a WooCommerce order status changes to "refunded", THE Order_Sync SHALL send a refund notification to Protheus
3. THE Order_Sync SHALL map WooCommerce order statuses to Protheus order statuses using a configurable mapping table
4. IF status update to Protheus fails, THEN THE Order_Sync SHALL log the error and schedule a retry
5. THE Order_Sync SHALL prevent status changes in WooCommerce for orders that failed to sync to Protheus

