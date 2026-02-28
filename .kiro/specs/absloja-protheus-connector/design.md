# Design Document: ABS Loja Protheus Connector

## Overview

O ABS Loja Protheus Connector é um plugin WordPress que integra WooCommerce com TOTVS Protheus ERP através de REST API. O plugin automatiza o fluxo completo de vendas, incluindo sincronização bidirecional de pedidos, cadastro automático de clientes, sincronização de catálogo e estoque, e recebimento de atualizações em tempo real via webhooks.

### Objetivos Principais

- Eliminar entrada manual de dados entre WooCommerce e Protheus
- Garantir consistência de dados entre os sistemas
- Fornecer sincronização em tempo real e agendada
- Oferecer rastreabilidade completa através de logs detalhados
- Permitir configuração flexível através de mapeamentos customizáveis
- Garantir resiliência através de sistema de retry automático

### Princípios de Design

1. **Modularidade**: Cada funcionalidade principal é encapsulada em um módulo independente
2. **Extensibilidade**: Uso de hooks e filters do WordPress para permitir customizações
3. **Resiliência**: Sistema de retry automático para operações falhadas
4. **Observabilidade**: Logging detalhado de todas as operações
5. **Segurança**: Armazenamento seguro de credenciais e validação de webhooks
6. **Performance**: Processamento em batch e caching de mapeamentos

## Architecture

### Estrutura de Diretórios

```
absloja-protheus-connector/
├── absloja-protheus-connector.php    # Plugin principal
├── includes/
│   ├── class-plugin.php               # Classe principal do plugin
│   ├── class-activator.php            # Ativação do plugin
│   ├── class-deactivator.php          # Desativação do plugin
│   ├── class-loader.php               # Gerenciador de hooks
│   ├── modules/
│   │   ├── class-auth-manager.php     # Autenticação com Protheus
│   │   ├── class-order-sync.php       # Sincronização de pedidos
│   │   ├── class-customer-sync.php    # Sincronização de clientes
│   │   ├── class-catalog-sync.php     # Sincronização de catálogo
│   │   ├── class-webhook-handler.php  # Processamento de webhooks
│   │   ├── class-logger.php           # Sistema de logs
│   │   ├── class-retry-manager.php    # Gerenciamento de retries
│   │   └── class-mapping-engine.php   # Motor de mapeamentos
│   ├── api/
│   │   ├── class-protheus-client.php  # Cliente HTTP para Protheus
│   │   └── class-rest-controller.php  # Endpoints REST do plugin
│   ├── admin/
│   │   ├── class-admin.php            # Interface administrativa
│   │   ├── class-settings.php         # Gerenciamento de configurações
│   │   ├── class-log-viewer.php       # Visualizador de logs
│   │   └── views/                     # Templates administrativos
│   └── database/
│       └── class-schema.php           # Esquema de tabelas customizadas
├── assets/
│   ├── css/
│   │   └── admin.css                  # Estilos administrativos
│   └── js/
│       └── admin.js                   # Scripts administrativos
└── languages/                         # Arquivos de tradução
```

### Padrões de Design

**Namespace PSR-4**: Todos os componentes utilizam namespace `ABSLoja\ProtheusConnector`

**Autoloading**: Implementação de autoloader PSR-4 customizado

**Singleton Pattern**: Classe principal do plugin utiliza singleton para garantir instância única

**Dependency Injection**: Módulos recebem dependências via construtor

**Observer Pattern**: Uso extensivo de hooks e filters do WordPress

### Fluxo de Inicialização

1. WordPress carrega `absloja-protheus-connector.php`
2. Plugin registra autoloader PSR-4
3. Instância singleton da classe `Plugin` é criada
4. `Loader` registra todos os hooks e filters
5. Módulos são inicializados com suas dependências
6. Admin interface é registrada se usuário tem capabilities adequadas
7. REST API endpoints são registrados
8. WP-Cron jobs são verificados e agendados se necessário

## Components and Interfaces

### Auth_Manager

**Responsabilidade**: Gerenciar autenticação com Protheus REST API

**Métodos Públicos**:
```php
class Auth_Manager {
    public function __construct(array $config);
    public function get_auth_headers(): array;
    public function test_connection(): bool;
    public function refresh_token(): bool; // Para OAuth2
    public function is_authenticated(): bool;
}
```

**Configuração**:
- `auth_type`: 'basic' ou 'oauth2'
- `api_url`: URL base da API Protheus
- `username`: Usuário (Basic Auth)
- `password`: Senha (Basic Auth)
- `client_id`: Client ID (OAuth2)
- `client_secret`: Client Secret (OAuth2)
- `token_endpoint`: Endpoint de token (OAuth2)

**Armazenamento Seguro**:
- Credenciais armazenadas em `wp_options` com prefixo `absloja_protheus_`
- Senhas e secrets criptografados usando `openssl_encrypt()` com chave derivada de `AUTH_KEY`
- Tokens OAuth2 armazenados com timestamp de expiração

### Order_Sync

**Responsabilidade**: Sincronizar pedidos WooCommerce para Protheus

**Métodos Públicos**:
```php
class Order_Sync {
    public function __construct(
        Auth_Manager $auth,
        Customer_Sync $customer_sync,
        Mapping_Engine $mapper,
        Logger $logger,
        Retry_Manager $retry
    );
    
    public function sync_order(WC_Order $order): bool;
    public function sync_order_status(WC_Order $order, string $new_status): bool;
    public function cancel_order(WC_Order $order): bool;
    public function refund_order(WC_Order $order): bool;
}
```

**Hooks WordPress**:
- `woocommerce_order_status_processing`: Trigger para sincronização de novos pedidos
- `woocommerce_order_status_cancelled`: Trigger para cancelamento
- `woocommerce_order_status_refunded`: Trigger para reembolso

**Fluxo de Sincronização de Pedido**:
1. Verificar se pedido já foi sincronizado (metadata `_protheus_order_id`)
2. Validar dados do pedido
3. Verificar/criar cliente via `Customer_Sync`
4. Mapear dados do pedido para formato Protheus (SC5/SC6)
5. Determinar TES baseado no estado do cliente
6. Enviar requisição POST para `/api/v1/orders`
7. Se sucesso: armazenar `_protheus_order_id` e `_protheus_sync_date`
8. Se falha: logar erro e agendar retry

**Mapeamento de Pedido (SC5)**:
```php
[
    'C5_FILIAL'   => '01',                    // Filial fixa
    'C5_NUM'      => '',                      // Gerado pelo Protheus
    'C5_TIPO'     => 'N',                     // Tipo Normal
    'C5_CLIENTE'  => $customer_code,          // Código do cliente
    'C5_LOJACLI'  => '01',                    // Loja padrão
    'C5_CONDPAG'  => $payment_condition,      // Mapeado de payment_method
    'C5_TABELA'   => '001',                   // Tabela de preço padrão
    'C5_VEND1'    => '000001',                // Vendedor padrão
    'C5_PEDWOO'   => $order->get_id(),        // ID do pedido WooCommerce
    'C5_EMISSAO'  => $order->get_date_created()->format('Ymd'),
    'C5_FRETE'    => $order->get_shipping_total(),
    'C5_DESCONT'  => $order->get_discount_total(),
]
```

**Mapeamento de Itens (SC6)**:
```php
foreach ($order->get_items() as $item) {
    [
        'C6_FILIAL'  => '01',
        'C6_NUM'     => '',                   // Mesmo número do SC5
        'C6_ITEM'    => $item_sequence,       // Sequencial 01, 02, 03...
        'C6_PRODUTO' => $product->get_sku(),
        'C6_QTDVEN'  => $item->get_quantity(),
        'C6_PRCVEN'  => $item->get_total() / $item->get_quantity(),
        'C6_VALOR'   => $item->get_total(),
        'C6_TES'     => $tes_code,            // Determinado por regra
    ]
}
```

**Determinação de TES**:
- Regras configuráveis por estado (UF)
- Exemplo: SP = '501', Outros = '502'
- Fallback para TES padrão se estado não mapeado

### Customer_Sync

**Responsabilidade**: Gerenciar cadastro de clientes no Protheus

**Métodos Públicos**:
```php
class Customer_Sync {
    public function __construct(
        Auth_Manager $auth,
        Mapping_Engine $mapper,
        Logger $logger
    );
    
    public function ensure_customer_exists(WC_Order $order): string;
    public function create_customer(WC_Order $order): string;
    public function check_customer_exists(string $cpf_cnpj): ?string;
}
```

**Fluxo de Verificação/Criação**:
1. Extrair CPF/CNPJ dos campos customizados do pedido
2. Limpar formatação (remover pontos, traços)
3. Consultar Protheus: `GET /api/v1/customers?cgc={cpf_cnpj}`
4. Se existe: retornar código do cliente
5. Se não existe: criar novo cliente e retornar código

**Mapeamento de Cliente (SA1)**:
```php
[
    'A1_FILIAL'  => '01',
    'A1_COD'     => '',                       // Gerado pelo Protheus
    'A1_LOJA'    => '01',
    'A1_NOME'    => $billing_first_name . ' ' . $billing_last_name,
    'A1_NREDUZ'  => substr($billing_first_name, 0, 20),
    'A1_CGC'     => $cpf_cnpj,                // Limpo, sem formatação
    'A1_TIPO'    => strlen($cpf_cnpj) == 11 ? 'F' : 'J',  // F=Física, J=Jurídica
    'A1_END'     => $billing_address_1,
    'A1_BAIRRO'  => $billing_neighborhood,    // Campo customizado
    'A1_MUN'     => $billing_city,
    'A1_EST'     => $billing_state,
    'A1_CEP'     => preg_replace('/\D/', '', $billing_postcode),
    'A1_DDD'     => substr(preg_replace('/\D/', '', $billing_phone), 0, 2),
    'A1_TEL'     => substr(preg_replace('/\D/', '', $billing_phone), 2),
    'A1_EMAIL'   => $billing_email,
]
```

### Catalog_Sync

**Responsabilidade**: Sincronizar produtos e estoque do Protheus para WooCommerce

**Métodos Públicos**:
```php
class Catalog_Sync {
    public function __construct(
        Auth_Manager $auth,
        Mapping_Engine $mapper,
        Logger $logger
    );
    
    public function sync_products(int $batch_size = 50): array;
    public function sync_stock(): array;
    public function sync_single_product(string $sku): bool;
    public function sync_single_stock(string $sku, int $quantity): bool;
}
```

**Fluxo de Sincronização de Produtos**:
1. Buscar produtos do Protheus: `GET /api/v1/products?page={n}&limit={batch_size}`
2. Para cada produto:
   - Verificar se existe no WooCommerce por SKU
   - Se existe: atualizar dados
   - Se não existe: criar novo produto
3. Mapear campos SB1 para WooCommerce
4. Processar categorias (mapeamento B1_GRUPO)
5. Processar imagens (se URL configurada)
6. Definir status baseado em B1_MSBLQL

**Mapeamento de Produto (SB1 → WooCommerce)**:
```php
[
    'sku'               => $protheus_data['B1_COD'],
    'name'              => $protheus_data['B1_DESC'],
    'regular_price'     => $protheus_data['B1_PRV1'],
    'description'       => $protheus_data['B1_DESC'] ?? '',
    'short_description' => $protheus_data['B1_DESCMAR'] ?? '',
    'weight'            => $protheus_data['B1_PESO'],
    'status'            => $protheus_data['B1_MSBLQL'] == '1' ? 'draft' : 'publish',
    'manage_stock'      => true,
    'stock_quantity'    => 0,  // Atualizado por sync_stock
    'meta_data'         => [
        '_protheus_synced' => true,
        '_protheus_sync_date' => current_time('mysql'),
        '_protheus_b1_grupo' => $protheus_data['B1_GRUPO'],
    ]
]
```

**Fluxo de Sincronização de Estoque**:
1. Buscar estoque do Protheus: `GET /api/v1/stock`
2. Para cada item de estoque:
   - Localizar produto WooCommerce por SKU (B2_COD)
   - Atualizar quantidade (B2_QATU)
   - Se quantidade = 0: ocultar produto
   - Se quantidade > 0 e estava oculto: restaurar visibilidade

**Prevenção de Edição Manual de Preços**:
- Hook `woocommerce_product_options_pricing`: Adicionar campo readonly
- Hook `woocommerce_process_product_meta`: Restaurar preço original se modificado
- Adicionar notice administrativa explicando que preço é sincronizado

### Webhook_Handler

**Responsabilidade**: Receber e processar webhooks do Protheus

**Métodos Públicos**:
```php
class Webhook_Handler {
    public function __construct(
        Auth_Manager $auth,
        Logger $logger
    );
    
    public function register_routes(): void;
    public function handle_order_status_update(WP_REST_Request $request): WP_REST_Response;
    public function handle_stock_update(WP_REST_Request $request): WP_REST_Response;
    public function authenticate_webhook(WP_REST_Request $request): bool;
}
```

**Endpoints REST**:
- `POST /wp-json/absloja-protheus/v1/webhook/order-status`
- `POST /wp-json/absloja-protheus/v1/webhook/stock`

**Autenticação de Webhooks**:
- Método 1: Token secreto no header `X-Protheus-Token`
- Método 2: Assinatura HMAC no header `X-Protheus-Signature`
- Token/secret configurável nas settings

**Payload Esperado - Order Status**:
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

**Mapeamento de Status Protheus → WooCommerce**:
```php
[
    'pending'    => 'pending',
    'approved'   => 'processing',
    'invoiced'   => 'completed',
    'shipped'    => 'completed',
    'cancelled'  => 'cancelled',
    'rejected'   => 'failed',
]
```

**Payload Esperado - Stock Update**:
```json
{
    "sku": "PROD001",
    "quantity": 50,
    "warehouse": "01"
}
```

### Logger

**Responsabilidade**: Registrar todas as transações e eventos do plugin

**Métodos Públicos**:
```php
class Logger {
    public function __construct();
    
    public function log_api_request(string $endpoint, array $payload, $response, float $duration): void;
    public function log_webhook(string $type, array $payload, $response): void;
    public function log_sync_operation(string $type, array $data, bool $success, ?string $error = null): void;
    public function log_error(string $message, \Throwable $exception, array $context = []): void;
    public function get_logs(array $filters = []): array;
    public function export_logs_csv(array $filters = []): string;
    public function cleanup_old_logs(): int;
}
```

**Estrutura de Log**:
```php
[
    'id'          => int,
    'timestamp'   => datetime,
    'type'        => string,  // 'api_request', 'webhook', 'sync', 'error'
    'operation'   => string,  // 'order_sync', 'product_sync', etc.
    'status'      => string,  // 'success', 'error', 'retry'
    'message'     => string,
    'payload'     => json,
    'response'    => json,
    'duration'    => float,   // Em segundos
    'error_trace' => text,
    'context'     => json,
]
```

**Limpeza Automática**:
- Executada diariamente via WP-Cron
- Remove logs com mais de 30 dias quando total > 1000 registros
- Mantém sempre logs de erro independente da data

### Retry_Manager

**Responsabilidade**: Gerenciar reprocessamento de operações falhadas

**Métodos Públicos**:
```php
class Retry_Manager {
    public function __construct(Logger $logger);
    
    public function schedule_retry(string $operation_type, array $data, ?string $error = null): void;
    public function process_retries(): void;
    public function get_pending_retries(): array;
    public function manual_retry(int $retry_id): bool;
    public function mark_as_failed(int $retry_id): void;
}
```

**Estrutura de Retry Queue**:
```php
[
    'id'             => int,
    'operation_type' => string,  // 'order_sync', 'customer_sync', etc.
    'data'           => json,    // Dados necessários para retry
    'attempts'       => int,     // Número de tentativas
    'max_attempts'   => int,     // Máximo de tentativas (padrão: 5)
    'next_attempt'   => datetime,
    'last_error'     => text,
    'status'         => string,  // 'pending', 'processing', 'failed', 'success'
    'created_at'     => datetime,
    'updated_at'     => datetime,
]
```

**Estratégia de Retry**:
- Intervalo fixo: 1 hora entre tentativas
- Máximo de 5 tentativas
- Após esgotamento: marcar como failed e notificar admin
- WP-Cron job: `absloja_protheus_process_retries` (hourly)

### Mapping_Engine

**Responsabilidade**: Gerenciar mapeamentos configuráveis entre sistemas

**Métodos Públicos**:
```php
class Mapping_Engine {
    public function __construct();
    
    public function get_customer_mapping(): array;
    public function get_order_mapping(): array;
    public function get_product_mapping(): array;
    public function get_payment_mapping(string $woo_method): ?string;
    public function get_category_mapping(string $protheus_group): ?int;
    public function get_tes_by_state(string $state): string;
    public function get_status_mapping(string $protheus_status): string;
    public function validate_mapping(string $type, array $mapping): array;
    public function save_mapping(string $type, array $mapping): bool;
}
```

**Tipos de Mapeamento**:

1. **Customer Fields** (WooCommerce → Protheus SA1)
2. **Order Fields** (WooCommerce → Protheus SC5/SC6)
3. **Product Fields** (Protheus SB1 → WooCommerce)
4. **Payment Methods** (WooCommerce → Protheus Conditions)
5. **Categories** (Protheus B1_GRUPO → WooCommerce Category ID)
6. **TES Rules** (Estado → Código TES)
7. **Status** (Protheus → WooCommerce)

**Mapeamentos Padrão**:

Payment Methods:
```php
[
    'bacs'        => '001',  // Transferência bancária
    'cheque'      => '002',  // Cheque
    'cod'         => '003',  // Dinheiro
    'credit_card' => '004',  // Cartão de crédito
    'pix'         => '005',  // PIX
]
```

TES Rules:
```php
[
    'SP' => '501',  // Venda dentro do estado
    'RJ' => '502',  // Venda fora do estado
    'MG' => '502',
    // ... outros estados
    'default' => '502',
]
```

Categories:
```php
[
    '01' => 15,  // Eletrônicos
    '02' => 16,  // Roupas
    '03' => 17,  // Alimentos
    // ... outros grupos
]
```

## Data Models

### Tabelas Customizadas WordPress

#### wp_absloja_logs

```sql
CREATE TABLE wp_absloja_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    timestamp DATETIME NOT NULL,
    type VARCHAR(50) NOT NULL,
    operation VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL,
    message TEXT,
    payload LONGTEXT,
    response LONGTEXT,
    duration DECIMAL(10,4),
    error_trace TEXT,
    context LONGTEXT,
    INDEX idx_timestamp (timestamp),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_operation (operation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### wp_absloja_retry_queue

```sql
CREATE TABLE wp_absloja_retry_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operation_type VARCHAR(100) NOT NULL,
    data LONGTEXT NOT NULL,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 5,
    next_attempt DATETIME NOT NULL,
    last_error TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_status (status),
    INDEX idx_next_attempt (next_attempt),
    INDEX idx_operation_type (operation_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### WordPress Options

Todas as configurações são armazenadas em `wp_options` com prefixo `absloja_protheus_`:

```php
// Autenticação
'absloja_protheus_auth_type'       => 'basic|oauth2'
'absloja_protheus_api_url'         => 'https://protheus.example.com'
'absloja_protheus_username'        => 'encrypted_value'
'absloja_protheus_password'        => 'encrypted_value'
'absloja_protheus_client_id'       => 'encrypted_value'
'absloja_protheus_client_secret'   => 'encrypted_value'
'absloja_protheus_token_endpoint'  => '/oauth2/token'
'absloja_protheus_access_token'    => 'encrypted_value'
'absloja_protheus_token_expires'   => timestamp

// Mapeamentos
'absloja_protheus_customer_mapping'  => serialized_array
'absloja_protheus_order_mapping'     => serialized_array
'absloja_protheus_product_mapping'   => serialized_array
'absloja_protheus_payment_mapping'   => serialized_array
'absloja_protheus_category_mapping'  => serialized_array
'absloja_protheus_tes_rules'         => serialized_array
'absloja_protheus_status_mapping'    => serialized_array

// Agendamento
'absloja_protheus_catalog_sync_frequency' => '1hour'
'absloja_protheus_stock_sync_frequency'   => '15min'

// Webhooks
'absloja_protheus_webhook_token'     => 'random_token'
'absloja_protheus_webhook_secret'    => 'random_secret'

// Imagens
'absloja_protheus_image_url_pattern' => 'https://cdn.example.com/products/{sku}.jpg'

// Avançado
'absloja_protheus_batch_size'        => 50
'absloja_protheus_retry_interval'    => 3600  // segundos
'absloja_protheus_max_retries'       => 5
'absloja_protheus_log_retention'     => 30    // dias
```

### Metadados de Pedidos WooCommerce

```php
// Armazenados em wp_postmeta
'_protheus_order_id'      => 'string'   // Número do pedido no Protheus
'_protheus_sync_date'     => 'datetime' // Data da sincronização
'_protheus_sync_status'   => 'string'   // 'synced', 'pending', 'error'
'_protheus_sync_error'    => 'string'   // Mensagem de erro se houver
'_protheus_customer_code' => 'string'   // Código do cliente no Protheus
'_protheus_invoice_number'=> 'string'   // Número da nota fiscal
'_protheus_tracking_code' => 'string'   // Código de rastreamento
```

### Metadados de Produtos WooCommerce

```php
// Armazenados em wp_postmeta
'_protheus_synced'        => 'boolean'  // Produto sincronizado do Protheus
'_protheus_sync_date'     => 'datetime' // Data da última sincronização
'_protheus_b1_grupo'      => 'string'   // Grupo do produto no Protheus
'_protheus_b1_cod'        => 'string'   // Código do produto (redundante com SKU)
'_protheus_price_locked'  => 'boolean'  // Preço bloqueado para edição manual
```


## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property Reflection

Após análise dos critérios de aceitação, identifiquei as seguintes redundâncias e consolidações:

**Redundâncias Identificadas**:
- Propriedades 5.2 e 6.2 (autenticação de webhooks) são idênticas → consolidar em uma única propriedade
- Propriedades 4.3 e 6.5 (ocultar produto quando estoque zero) são idênticas → consolidar
- Propriedades 5.5 e 6.6 (retornar 401 em falha de autenticação) são idênticas → consolidar
- Propriedades 5.7 e 6.7 (retornar 200 em sucesso) são idênticas → consolidar
- Propriedades 1.2 e 1.3 (mapeamento de campos) podem ser consolidadas em uma propriedade mais abrangente
- Propriedades 3.5, 3.6, 3.7 (mapeamentos específicos) são casos particulares da propriedade 3.2 → remover redundância

**Consolidações Realizadas**:
- Webhook authentication → Property única para ambos os endpoints
- Webhook HTTP responses → Properties únicas para códigos de status
- Field mappings → Properties consolidadas por tipo de entidade
- Stock visibility → Property única para comportamento de visibilidade

### Property 1: Order Sync Trigger on Status Change

*For any* WooCommerce order that changes status to "processing", the Order_Sync module should send the order data to Protheus REST API.

**Validates: Requirements 1.1**

### Property 2: Complete Order Field Mapping

*For any* WooCommerce order being synced, all configured field mappings (both SC5 header and SC6 line items) should be present and correctly formatted in the Protheus API payload.

**Validates: Requirements 1.2, 1.3**

### Property 3: Protheus Order ID Storage

*For any* order sync that receives a success response from Protheus, the returned Protheus order number should be stored in WooCommerce order metadata as `_protheus_order_id`.

**Validates: Requirements 1.4**

### Property 4: Error Logging and Retry Scheduling

*For any* API request that returns an error response, the system should create a log entry with error details and schedule a retry attempt.

**Validates: Requirements 1.5**

### Property 5: WooCommerce Order ID Inclusion

*For any* order being synced to Protheus, the payload should include the WooCommerce order ID in the C5_PEDWOO field.

**Validates: Requirements 1.6**

### Property 6: Payment Method Mapping

*For any* WooCommerce payment method, the Order_Sync should map it to a Protheus payment condition code according to the configured mapping table, with fallback to default if not mapped.

**Validates: Requirements 1.7**

### Property 7: TES Determination by State

*For any* order being synced, the TES code should be determined based on the customer's billing state according to the configured TES rules, with fallback to default TES if state not mapped.

**Validates: Requirements 1.8**

### Property 8: Customer Existence Check

*For any* order being synced to Protheus, the Customer_Sync should verify if the customer exists in Protheus by querying with CPF or CNPJ before proceeding.

**Validates: Requirements 2.1**

### Property 9: Customer Creation on Non-Existence

*For any* order where the customer does not exist in Protheus, the Customer_Sync should create a new customer record in SA1 table before sending the order.

**Validates: Requirements 2.2**

### Property 10: Customer Field Mapping

*For any* customer being created in Protheus, all WooCommerce billing fields should be mapped to Protheus SA1 fields according to the configured mapping.

**Validates: Requirements 2.3**

### Property 11: CPF/CNPJ Extraction and Cleaning

*For any* customer data, the CPF or CNPJ should be extracted from the appropriate billing field, cleaned of formatting characters (dots, dashes), and mapped to A1_CGC.

**Validates: Requirements 2.4**

### Property 12: Name Concatenation

*For any* customer being created, the A1_NOME field should contain the concatenation of billing_first_name and billing_last_name with a space separator.

**Validates: Requirements 2.5**

### Property 13: Order Sync Abortion on Customer Creation Failure

*For any* customer creation that fails, the order sync operation should be aborted, no order should be sent to Protheus, and an error should be logged.

**Validates: Requirements 2.6**

### Property 14: Product Data Fetching

*For any* catalog sync execution, the Catalog_Sync should fetch product data from Protheus SB1 table via REST API.

**Validates: Requirements 3.1**

### Property 15: Product Field Mapping

*For any* product data received from Protheus, all SB1 fields should be mapped to WooCommerce product fields according to the configured mapping.

**Validates: Requirements 3.2, 3.5, 3.6, 3.7**

### Property 16: Product Update on Existing SKU

*For any* product received from Protheus where a WooCommerce product with matching SKU already exists, the existing product should be updated with the new data.

**Validates: Requirements 3.3**

### Property 17: Product Creation on New SKU

*For any* product received from Protheus where no WooCommerce product with matching SKU exists, a new WooCommerce product should be created.

**Validates: Requirements 3.4**

### Property 18: Blocked Product Status

*For any* product where B1_MSBLQL indicates blocked status, the WooCommerce product status should be set to "draft".

**Validates: Requirements 3.8**

### Property 19: Category Mapping

*For any* product with a B1_GRUPO value, the product should be assigned to WooCommerce categories according to the configured category mapping table.

**Validates: Requirements 3.9**

### Property 20: Stock Data Fetching

*For any* stock sync execution, the Catalog_Sync should fetch stock quantities from Protheus SB2 table via REST API.

**Validates: Requirements 4.1**

### Property 21: Stock Quantity Update

*For any* stock data received from Protheus, the corresponding WooCommerce product (matched by SKU) should have its stock quantity updated to the B2_QATU value.

**Validates: Requirements 4.2**

### Property 22: Product Visibility on Zero Stock

*For any* product where stock quantity reaches zero (either via sync or webhook), the product visibility should be set to "hidden" in WooCommerce.

**Validates: Requirements 4.3, 6.5**

### Property 23: Product Visibility Restoration

*For any* product that was previously hidden due to zero stock and receives stock quantity > 0, the product visibility should be restored in WooCommerce.

**Validates: Requirements 4.4**

### Property 24: Stock Product Matching

*For any* stock data, the product should be matched by comparing B2_COD with WooCommerce SKU field.

**Validates: Requirements 4.5**

### Property 25: Webhook Authentication

*For any* webhook request received (order status or stock update), the Webhook_Handler should authenticate the request using the configured authentication method before processing.

**Validates: Requirements 5.2, 6.2**

### Property 26: Order Location by WooCommerce ID

*For any* valid order status update webhook, the Webhook_Handler should locate the WooCommerce order using the C5_PEDWOO field value.

**Validates: Requirements 5.3**

### Property 27: Order Status Update

*For any* order status update webhook where the order is found, the WooCommerce order status should be updated according to the received Protheus status using the configured status mapping.

**Validates: Requirements 5.4**

### Property 28: Webhook Authentication Failure Response

*For any* webhook request where authentication fails, the Webhook_Handler should return HTTP 401 Unauthorized.

**Validates: Requirements 5.5, 6.6**

### Property 29: Order Not Found Response

*For any* order status update webhook where the order is not found, the Webhook_Handler should return HTTP 404 Not Found and log the error.

**Validates: Requirements 5.6**

### Property 30: Webhook Success Response

*For any* webhook request that is processed successfully, the Webhook_Handler should return HTTP 200 OK.

**Validates: Requirements 5.7, 6.7**

### Property 31: Product Location by SKU

*For any* valid stock update webhook, the Webhook_Handler should locate the WooCommerce product using the SKU field.

**Validates: Requirements 6.3**

### Property 32: Stock Quantity Update via Webhook

*For any* stock update webhook where the product is found, the WooCommerce stock quantity should be updated with the received value.

**Validates: Requirements 6.4**

### Property 33: Secure Credential Storage

*For any* API credentials stored by Auth_Manager, they should be encrypted before storage in WordPress options table.

**Validates: Requirements 7.3**

### Property 34: Authentication Headers Inclusion

*For any* API request made to Protheus, the Auth_Manager should include appropriate authentication headers (Basic Auth or OAuth2 Bearer token).

**Validates: Requirements 7.4**

### Property 35: Authentication Failure Handling

*For any* API request where authentication fails, the Auth_Manager should log the failure and return an error to the calling module.

**Validates: Requirements 7.5**

### Property 36: API Request Logging

*For any* API request sent to Protheus, the Logger should record a log entry containing timestamp, endpoint, payload, response, and duration.

**Validates: Requirements 8.1**

### Property 37: Webhook Request Logging

*For any* webhook request received from Protheus, the Logger should record a log entry containing timestamp, endpoint, payload, and response.

**Validates: Requirements 8.2**

### Property 38: Sync Operation Logging

*For any* sync operation executed, the Logger should record a log entry containing timestamp, operation type, affected records, and result status.

**Validates: Requirements 8.3**

### Property 39: Error Logging

*For any* error that occurs, the Logger should record a log entry containing timestamp, error message, stack trace, and context data.

**Validates: Requirements 8.4**

### Property 40: Log Export to CSV

*For any* log export request, the Logger should generate a CSV file containing all log entries matching the specified filters with all relevant fields.

**Validates: Requirements 8.7**

### Property 41: Automatic Log Cleanup

*For any* log cleanup execution where storage exceeds 1000 entries, the Logger should delete logs older than 30 days while preserving error logs.

**Validates: Requirements 8.8**

### Property 42: Retry Scheduling on Failure

*For any* order sync operation that fails, the Retry_Manager should schedule a retry attempt using WP-Cron with next execution time set to 1 hour from failure.

**Validates: Requirements 9.1**

### Property 43: Maximum Retry Attempts

*For any* failed operation in the retry queue, the Retry_Manager should attempt a maximum of 5 retries before marking as permanently failed.

**Validates: Requirements 9.3**

### Property 44: Permanent Failure Notification

*For any* operation where all retry attempts are exhausted, the Retry_Manager should mark the operation as permanently failed and send an admin notification.

**Validates: Requirements 9.4**

### Property 45: Retry Queue Removal on Success

*For any* retry attempt that succeeds, the Retry_Manager should remove the operation from the retry queue and log the success.

**Validates: Requirements 9.5**

### Property 46: Mapping Validation

*For any* mapping configuration submitted, the Mapping_Engine should validate the configuration and return errors for invalid mappings.

**Validates: Requirements 10.8**

### Property 47: WP-Cron Scheduling

*For any* sync operation configured with a frequency, the Plugin should create or update the corresponding WP-Cron event with the correct schedule.

**Validates: Requirements 11.4**

### Property 48: Manual Sync Execution

*For any* manual sync trigger, the Plugin should execute the sync operation immediately and return the result status.

**Validates: Requirements 11.6**

### Property 49: TES Error Handling

*For any* order sync that receives a TES error from Protheus, the Order_Sync should log the error with details and mark the order for manual review.

**Validates: Requirements 12.1**

### Property 50: Stock Insufficient Error Handling

*For any* order sync that receives a stock insufficient error from Protheus, the Order_Sync should log the error and update WooCommerce stock to prevent further sales.

**Validates: Requirements 12.2**

### Property 51: API Unreachable Error Handling

*For any* API request where Protheus is unreachable, the Plugin should log the connectivity error and schedule retry attempts.

**Validates: Requirements 12.3**

### Property 52: Business Error Order Notes

*For any* business error that occurs during order processing, the Plugin should add an admin note to the WooCommerce order with error details.

**Validates: Requirements 12.4**

### Property 53: Configuration Input Validation

*For any* configuration input submitted in the admin interface, the Plugin should validate the input and display error messages for invalid values.

**Validates: Requirements 13.6**

### Property 54: Image Download and Attachment

*For any* product sync where an external image URL is provided, the Catalog_Sync should download the image and attach it to the WooCommerce product.

**Validates: Requirements 14.2**

### Property 55: Image Preservation

*For any* product sync where no external image URL is provided, the Catalog_Sync should preserve existing WooCommerce product images without modification.

**Validates: Requirements 14.3**

### Property 56: Image URL Pattern Processing

*For any* product sync where an image URL pattern is configured, the Plugin should replace the SKU variable in the pattern with the actual product SKU to generate the image URL.

**Validates: Requirements 14.4**

### Property 57: Order Cancellation Sync

*For any* WooCommerce order that changes status to "cancelled", the Order_Sync should send a cancellation request to Protheus.

**Validates: Requirements 15.1**

### Property 58: Order Refund Sync

*For any* WooCommerce order that changes status to "refunded", the Order_Sync should send a refund notification to Protheus.

**Validates: Requirements 15.2**

### Property 59: Bidirectional Status Mapping

*For any* order status change in WooCommerce, the Order_Sync should map the WooCommerce status to Protheus status using the configured mapping table before sending.

**Validates: Requirements 15.3**

### Property 60: Status Update Retry on Failure

*For any* status update to Protheus that fails, the Order_Sync should log the error and schedule a retry attempt.

**Validates: Requirements 15.4**

### Property 61: Status Change Prevention on Sync Failure

*For any* order that failed to sync to Protheus, the Order_Sync should prevent status changes in WooCommerce until sync succeeds.

**Validates: Requirements 15.5**

## Error Handling

### Error Categories

**1. Network Errors**
- Connection timeout
- DNS resolution failure
- SSL/TLS errors
- Network unreachable

**Handling Strategy**:
- Log error with full context
- Schedule retry with exponential backoff
- Display admin notice if persistent
- Maintain operation in retry queue

**2. Authentication Errors**
- Invalid credentials
- Expired OAuth2 token
- Insufficient permissions

**Handling Strategy**:
- Log authentication failure
- For OAuth2: attempt token refresh
- If refresh fails: notify admin immediately
- Do not retry until credentials updated

**3. Business Logic Errors**
- TES not found for state
- Customer CPF/CNPJ invalid
- Product SKU not found
- Stock insufficient

**Handling Strategy**:
- Log error with business context
- Add admin note to related entity (order/product)
- Mark for manual review
- Send admin notification
- Do not retry automatically

**4. Data Validation Errors**
- Missing required fields
- Invalid field format
- Field length exceeded
- Invalid data type

**Handling Strategy**:
- Log validation error with field details
- Display user-friendly error message
- Prevent operation from proceeding
- Provide guidance for correction

**5. API Response Errors**
- HTTP 4xx client errors
- HTTP 5xx server errors
- Malformed JSON response
- Unexpected response structure

**Handling Strategy**:
- Log full request/response
- For 5xx: schedule retry
- For 4xx: log and notify (likely configuration issue)
- Parse error messages from Protheus when available

### Error Recovery Mechanisms

**Retry Queue**:
- Automatic retry for transient errors
- Configurable retry intervals
- Maximum retry attempts limit
- Manual retry capability

**Fallback Behaviors**:
- Default TES when state not mapped
- Default payment condition when method not mapped
- Preserve existing data when sync fails
- Skip problematic items in batch operations

**Admin Notifications**:
- Email notification for permanent failures
- Dashboard widget for pending manual reviews
- Admin notices for configuration issues
- Log viewer for detailed troubleshooting

### Logging Strategy

**Log Levels**:
- **ERROR**: Operation failures, exceptions
- **WARNING**: Recoverable issues, fallback usage
- **INFO**: Successful operations, status changes
- **DEBUG**: Detailed execution flow (optional, configurable)

**Contextual Information**:
- User ID (if applicable)
- Order/Product ID
- Request/Response payloads
- Stack traces for exceptions
- Execution duration
- Memory usage (for batch operations)

### Data Integrity Safeguards

**Transaction-like Behavior**:
- Customer creation before order sync
- Rollback order sync if customer creation fails
- Atomic updates for product data
- Metadata updates only on confirmed success

**Idempotency**:
- Check for existing sync before retrying
- Use `_protheus_order_id` to prevent duplicates
- SKU-based product matching prevents duplicates
- Webhook deduplication (optional: store processed webhook IDs)

**Validation Gates**:
- Pre-sync validation of required fields
- Post-mapping validation of payload structure
- Response validation before storing results
- Configuration validation on save

## Testing Strategy

### Dual Testing Approach

O plugin será testado usando uma combinação de testes unitários e testes baseados em propriedades (property-based testing), garantindo cobertura abrangente:

**Unit Tests**: Focam em exemplos específicos, casos extremos e condições de erro
**Property Tests**: Verificam propriedades universais através de múltiplas entradas geradas aleatoriamente

Ambos os tipos de teste são complementares e necessários para garantir a corretude do sistema.

### Property-Based Testing Configuration

**Framework**: PHPUnit com extensão [php-quickcheck](https://github.com/steos/php-quickcheck) ou [Eris](https://github.com/giorgiosironi/eris)

**Configuração Mínima**:
- 100 iterações por teste de propriedade (devido à randomização)
- Seed configurável para reproduzibilidade
- Shrinking automático para encontrar casos mínimos de falha

**Tag Format**: Cada teste de propriedade deve referenciar a propriedade do design:
```php
/**
 * @test
 * Feature: absloja-protheus-connector, Property 1: Order Sync Trigger on Status Change
 */
```

### Test Structure

```
tests/
├── unit/
│   ├── modules/
│   │   ├── AuthManagerTest.php
│   │   ├── OrderSyncTest.php
│   │   ├── CustomerSyncTest.php
│   │   ├── CatalogSyncTest.php
│   │   ├── WebhookHandlerTest.php
│   │   ├── LoggerTest.php
│   │   ├── RetryManagerTest.php
│   │   └── MappingEngineTest.php
│   ├── api/
│   │   └── ProtheusClientTest.php
│   └── admin/
│       └── SettingsTest.php
├── property/
│   ├── OrderSyncPropertiesTest.php
│   ├── CustomerSyncPropertiesTest.php
│   ├── CatalogSyncPropertiesTest.php
│   ├── WebhookPropertiesTest.php
│   ├── LoggerPropertiesTest.php
│   ├── RetryPropertiesTest.php
│   └── MappingPropertiesTest.php
├── integration/
│   ├── OrderFlowIntegrationTest.php
│   ├── CatalogSyncIntegrationTest.php
│   └── WebhookIntegrationTest.php
└── fixtures/
    ├── orders.php
    ├── customers.php
    ├── products.php
    └── api-responses.php
```

### Unit Testing Focus

**Specific Examples**:
- Order with single item
- Order with multiple items
- Customer with CPF (11 digits)
- Customer with CNPJ (14 digits)
- Product with all fields populated
- Product with minimal fields

**Edge Cases**:
- Empty order items
- Missing required fields
- Invalid CPF/CNPJ format
- Zero stock quantity
- Negative prices
- Very long text fields
- Special characters in names
- Null values in optional fields

**Error Conditions**:
- API returns 401 Unauthorized
- API returns 500 Internal Server Error
- Network timeout
- Malformed JSON response
- Missing response fields
- Invalid webhook signature

**Integration Points**:
- WooCommerce hooks firing correctly
- WordPress options storage/retrieval
- WP-Cron scheduling
- REST API endpoint registration
- Admin menu registration

### Property-Based Testing Focus

**Example Property Test - Order Field Mapping**:
```php
/**
 * @test
 * Feature: absloja-protheus-connector, Property 2: Complete Order Field Mapping
 */
public function test_order_field_mapping_completeness()
{
    $this->forAll(
        Generator::associative([
            'id' => Generator::pos(),
            'status' => Generator::elements(['processing', 'completed']),
            'billing' => Generator::associative([
                'first_name' => Generator::names(),
                'last_name' => Generator::names(),
                'address_1' => Generator::string(),
                'city' => Generator::string(),
                'state' => Generator::elements(['SP', 'RJ', 'MG']),
                'postcode' => Generator::regex('/\d{5}-\d{3}/'),
            ]),
            'items' => Generator::seq(Generator::associative([
                'sku' => Generator::string(),
                'quantity' => Generator::pos(),
                'total' => Generator::float(0.01, 10000),
            ]))
        ])
    )->then(function ($orderData) {
        $order = $this->createMockOrder($orderData);
        $mapper = new Mapping_Engine();
        $payload = $mapper->map_order_to_protheus($order);
        
        // Verify all required SC5 fields are present
        $this->assertArrayHasKey('C5_FILIAL', $payload);
        $this->assertArrayHasKey('C5_CLIENTE', $payload);
        $this->assertArrayHasKey('C5_PEDWOO', $payload);
        $this->assertArrayHasKey('C5_EMISSAO', $payload);
        
        // Verify all items have required SC6 fields
        foreach ($payload['items'] as $item) {
            $this->assertArrayHasKey('C6_PRODUTO', $item);
            $this->assertArrayHasKey('C6_QTDVEN', $item);
            $this->assertArrayHasKey('C6_PRCVEN', $item);
            $this->assertArrayHasKey('C6_TES', $item);
        }
    });
}
```

**Example Property Test - CPF/CNPJ Cleaning**:
```php
/**
 * @test
 * Feature: absloja-protheus-connector, Property 11: CPF/CNPJ Extraction and Cleaning
 */
public function test_cpf_cnpj_cleaning_removes_formatting()
{
    $this->forAll(
        Generator::oneOf(
            Generator::regex('/\d{3}\.\d{3}\.\d{3}-\d{2}/'),  // CPF formatted
            Generator::regex('/\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}/')  // CNPJ formatted
        )
    )->then(function ($formattedDocument) {
        $customerSync = new Customer_Sync($this->auth, $this->mapper, $this->logger);
        $cleaned = $customerSync->clean_document($formattedDocument);
        
        // Should contain only digits
        $this->assertMatchesRegularExpression('/^\d+$/', $cleaned);
        
        // Should be 11 or 14 digits
        $length = strlen($cleaned);
        $this->assertTrue($length === 11 || $length === 14);
    });
}
```

**Example Property Test - Retry Limit**:
```php
/**
 * @test
 * Feature: absloja-protheus-connector, Property 43: Maximum Retry Attempts
 */
public function test_retry_manager_respects_maximum_attempts()
{
    $this->forAll(
        Generator::associative([
            'operation_type' => Generator::elements(['order_sync', 'customer_sync']),
            'data' => Generator::associative(['id' => Generator::pos()]),
        ])
    )->then(function ($retryData) {
        $retryManager = new Retry_Manager($this->logger);
        $retryId = $retryManager->schedule_retry(
            $retryData['operation_type'],
            $retryData['data']
        );
        
        // Simulate 5 failed attempts
        for ($i = 0; $i < 5; $i++) {
            $retryManager->process_retry($retryId, false);
        }
        
        $retry = $retryManager->get_retry($retryId);
        
        // Should be marked as failed after 5 attempts
        $this->assertEquals('failed', $retry['status']);
        $this->assertEquals(5, $retry['attempts']);
        
        // Should not process further retries
        $retryManager->process_retry($retryId, false);
        $retry = $retryManager->get_retry($retryId);
        $this->assertEquals(5, $retry['attempts']); // Still 5, not 6
    });
}
```

### Generators for Property Tests

**Custom Generators**:
```php
class WooCommerceGenerators
{
    public static function order(): Generator
    {
        return Generator::associative([
            'id' => Generator::pos(),
            'status' => Generator::elements([
                'pending', 'processing', 'completed', 
                'cancelled', 'refunded', 'failed'
            ]),
            'payment_method' => Generator::elements([
                'bacs', 'cheque', 'cod', 'credit_card', 'pix'
            ]),
            'billing' => self::billingAddress(),
            'shipping' => self::shippingAddress(),
            'items' => Generator::seq(self::orderItem(), 1, 10),
            'total' => Generator::float(0.01, 100000),
            'shipping_total' => Generator::float(0, 1000),
            'discount_total' => Generator::float(0, 5000),
        ]);
    }
    
    public static function billingAddress(): Generator
    {
        return Generator::associative([
            'first_name' => Generator::names(),
            'last_name' => Generator::names(),
            'company' => Generator::string(),
            'address_1' => Generator::string(),
            'address_2' => Generator::string(),
            'city' => Generator::string(),
            'state' => Generator::elements(['SP', 'RJ', 'MG', 'RS', 'PR']),
            'postcode' => Generator::regex('/\d{5}-\d{3}/'),
            'country' => Generator::constant('BR'),
            'email' => Generator::regex('/[a-z]+@[a-z]+\.[a-z]{2,3}/'),
            'phone' => Generator::regex('/\(\d{2}\) \d{4,5}-\d{4}/'),
            'cpf' => Generator::regex('/\d{3}\.\d{3}\.\d{3}-\d{2}/'),
        ]);
    }
    
    public static function product(): Generator
    {
        return Generator::associative([
            'B1_COD' => Generator::regex('/[A-Z0-9]{6}/'),
            'B1_DESC' => Generator::string(10, 100),
            'B1_PRV1' => Generator::float(0.01, 10000),
            'B1_PESO' => Generator::float(0.001, 1000),
            'B1_GRUPO' => Generator::elements(['01', '02', '03', '04', '05']),
            'B1_MSBLQL' => Generator::elements(['1', '2']),
        ]);
    }
}
```

### Mock Strategy

**API Mocking**:
- Use WP_Mock for WordPress functions
- Use Mockery for class dependencies
- Create ProtheusClientMock for API responses
- Fixture files for realistic API responses

**Database Mocking**:
- Use WordPress test suite database
- Transaction rollback after each test
- Factory classes for test data creation

### Continuous Integration

**GitHub Actions Workflow**:
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:5.7
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: wordpress_test
    
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'
          extensions: mysqli, zip
          coverage: xdebug
      
      - name: Install dependencies
        run: composer install
      
      - name: Run unit tests
        run: vendor/bin/phpunit --testsuite unit
      
      - name: Run property tests
        run: vendor/bin/phpunit --testsuite property
      
      - name: Run integration tests
        run: vendor/bin/phpunit --testsuite integration
      
      - name: Generate coverage report
        run: vendor/bin/phpunit --coverage-html coverage
```

### Test Coverage Goals

- **Unit Tests**: 80%+ code coverage
- **Property Tests**: All 61 correctness properties implemented
- **Integration Tests**: All critical user flows covered
- **Edge Cases**: All identified edge cases tested

### Testing Best Practices

1. **Isolation**: Each test should be independent
2. **Clarity**: Test names should describe what is being tested
3. **Speed**: Unit tests should run in < 1 second each
4. **Reliability**: Tests should not be flaky
5. **Maintainability**: Use factories and fixtures to reduce duplication
6. **Documentation**: Complex tests should have explanatory comments

