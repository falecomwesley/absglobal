# Implementation Plan: ABS Loja Protheus Connector

## Overview

Este plano de implementação detalha as tarefas necessárias para desenvolver o plugin WordPress "ABS Loja Protheus Connector", que integra WooCommerce com TOTVS Protheus ERP através de REST API. O plugin será desenvolvido em PHP seguindo padrões WordPress e WooCommerce, com arquitetura modular, sistema de logs robusto, retry automático e testes abrangentes.

A implementação seguirá uma abordagem incremental, construindo primeiro a infraestrutura base (autoloader, ativação, hooks), depois os módulos core (autenticação, cliente HTTP, mapeamentos), seguido pelos módulos de sincronização (clientes, pedidos, catálogo), webhooks, interface administrativa e finalmente testes.

## Tasks

- [x] 1. Configurar estrutura base do plugin
  - Criar arquivo principal do plugin com headers WordPress
  - Implementar autoloader PSR-4
  - Criar classes de ativação e desativação
  - Implementar classe Loader para gerenciamento de hooks
  - Criar classe Plugin principal com padrão Singleton
  - _Requirements: 7.6, 13.1_

- [x] 2. Implementar módulo de autenticação (Auth_Manager)
  - [x] 2.1 Criar classe Auth_Manager com suporte a Basic Auth e OAuth2
    - Implementar construtor que aceita configuração
    - Implementar método get_auth_headers() para Basic Auth
    - Implementar método get_auth_headers() para OAuth2 com Bearer token
    - Implementar método test_connection() para validar credenciais
    - Implementar método refresh_token() para renovação OAuth2
    - Implementar método is_authenticated() para verificar estado
    - _Requirements: 7.1, 7.2, 7.4_

  - [x] 2.2 Implementar armazenamento seguro de credenciais
    - Criar métodos para criptografar credenciais usando openssl_encrypt()
    - Criar métodos para descriptografar credenciais usando openssl_decrypt()
    - Usar AUTH_KEY do WordPress como base para chave de criptografia
    - Armazenar credenciais em wp_options com prefixo absloja_protheus_
    - _Requirements: 7.3_

  - [x] 2.3 Escrever testes unitários para Auth_Manager
    - Testar Basic Auth header generation
    - Testar OAuth2 token refresh
    - Testar criptografia/descriptografia de credenciais
    - Testar falha de autenticação
    - _Requirements: 7.5_

  - [x] 2.4 Escrever teste de propriedade para armazenamento seguro
    - **Property 33: Secure Credential Storage**
    - **Validates: Requirements 7.3**

- [x] 3. Implementar cliente HTTP (Protheus_Client)
  - [x] 3.1 Criar classe Protheus_Client como wrapper para wp_remote_post/get
    - Implementar método post() com tratamento de erros
    - Implementar método get() com tratamento de erros
    - Integrar Auth_Manager para headers de autenticação
    - Implementar timeout configurável
    - Implementar parsing de respostas JSON
    - _Requirements: 7.4_

  - [x] 3.2 Implementar tratamento de erros HTTP
    - Detectar erros de rede (timeout, DNS, SSL)
    - Detectar erros de autenticação (401, 403)
    - Detectar erros de servidor (5xx)
    - Detectar erros de cliente (4xx)
    - Retornar estrutura padronizada de erro
    - _Requirements: 7.5, 12.3_

  - [x] 3.3 Escrever testes unitários para Protheus_Client
    - Testar requisições GET e POST bem-sucedidas
    - Testar tratamento de timeout
    - Testar tratamento de erro 401
    - Testar tratamento de erro 500
    - Testar parsing de JSON inválido

  - [x] 3.4 Escrever teste de propriedade para inclusão de headers
    - **Property 34: Authentication Headers Inclusion**
    - **Validates: Requirements 7.4**

- [x] 4. Implementar sistema de logs (Logger)
  - [x] 4.1 Criar tabela customizada wp_absloja_logs
    - Implementar classe Schema com método create_logs_table()
    - Criar índices para timestamp, type, status, operation
    - Executar criação de tabela no hook de ativação do plugin
    - _Requirements: 8.1, 8.2, 8.3, 8.4_

  - [x] 4.2 Criar classe Logger com métodos de logging
    - Implementar log_api_request() para requisições API
    - Implementar log_webhook() para webhooks recebidos
    - Implementar log_sync_operation() para operações de sincronização
    - Implementar log_error() para erros e exceções
    - Armazenar payloads e responses como JSON
    - Registrar duração de operações
    - _Requirements: 8.1, 8.2, 8.3, 8.4_

  - [x] 4.3 Implementar recuperação e filtragem de logs
    - Implementar get_logs() com suporte a filtros
    - Suportar filtros por date range, type, status, operation
    - Implementar paginação de resultados
    - _Requirements: 8.6_

  - [x] 4.4 Implementar exportação de logs para CSV
    - Implementar export_logs_csv() que gera arquivo CSV
    - Incluir todos os campos relevantes no CSV
    - Aplicar filtros antes da exportação
    - _Requirements: 8.7_

  - [x] 4.5 Implementar limpeza automática de logs
    - Implementar cleanup_old_logs() executado via WP-Cron
    - Deletar logs com mais de 30 dias quando total > 1000
    - Preservar logs de erro independente da data
    - Agendar execução diária via WP-Cron
    - _Requirements: 8.8_

  - [x] 4.6 Escrever testes unitários para Logger
    - Testar criação de log entries
    - Testar filtragem de logs
    - Testar exportação CSV
    - Testar limpeza automática

  - [x] 4.7 Escrever testes de propriedade para logging
    - **Property 36: API Request Logging**
    - **Property 37: Webhook Request Logging**
    - **Property 38: Sync Operation Logging**
    - **Property 39: Error Logging**
    - **Property 40: Log Export to CSV**
    - **Property 41: Automatic Log Cleanup**
    - **Validates: Requirements 8.1, 8.2, 8.3, 8.4, 8.7, 8.8**

- [x] 5. Implementar sistema de retry (Retry_Manager)
  - [x] 5.1 Criar tabela customizada wp_absloja_retry_queue
    - Implementar método create_retry_queue_table() na classe Schema
    - Criar índices para status, next_attempt, operation_type
    - Executar criação de tabela no hook de ativação
    - _Requirements: 9.1_

  - [x] 5.2 Criar classe Retry_Manager
    - Implementar schedule_retry() para agendar reprocessamento
    - Implementar process_retries() para processar fila
    - Implementar get_pending_retries() para listar pendências
    - Implementar manual_retry() para retry manual
    - Implementar mark_as_failed() para falhas permanentes
    - Usar intervalo fixo de 1 hora entre tentativas
    - Limitar a 5 tentativas máximas
    - _Requirements: 9.1, 9.2, 9.3, 9.7_

  - [x] 5.3 Integrar Retry_Manager com WP-Cron
    - Registrar hook absloja_protheus_process_retries
    - Agendar execução horária via wp_schedule_event()
    - Implementar callback que chama process_retries()
    - _Requirements: 9.1, 9.2_

  - [x] 5.4 Implementar notificação de falhas permanentes
    - Enviar email ao administrador quando retry esgotado
    - Incluir detalhes da operação e último erro
    - _Requirements: 9.4_

  - [x] 5.5 Escrever testes unitários para Retry_Manager
    - Testar agendamento de retry
    - Testar processamento de retry bem-sucedido
    - Testar limite de tentativas
    - Testar remoção da fila após sucesso

  - [x] 5.6 Escrever testes de propriedade para retry
    - **Property 42: Retry Scheduling on Failure**
    - **Property 43: Maximum Retry Attempts**
    - **Property 44: Permanent Failure Notification**
    - **Property 45: Retry Queue Removal on Success**
    - **Validates: Requirements 9.1, 9.3, 9.4, 9.5**

- [x] 6. Implementar motor de mapeamentos (Mapping_Engine)
  - [x] 6.1 Criar classe Mapping_Engine
    - Implementar get_customer_mapping() para SA1
    - Implementar get_order_mapping() para SC5/SC6
    - Implementar get_product_mapping() para SB1
    - Implementar get_payment_mapping() para condições de pagamento
    - Implementar get_category_mapping() para categorias
    - Implementar get_tes_by_state() para regras de TES
    - Implementar get_status_mapping() para status de pedidos
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6_

  - [x] 6.2 Implementar mapeamentos padrão
    - Definir mapeamento padrão de payment methods
    - Definir mapeamento padrão de TES por estado
    - Definir mapeamento padrão de status
    - Carregar mapeamentos padrão na primeira ativação
    - _Requirements: 10.7_

  - [x] 6.3 Implementar validação de mapeamentos
    - Implementar validate_mapping() para cada tipo
    - Validar campos obrigatórios
    - Validar tipos de dados
    - Retornar array de erros de validação
    - _Requirements: 10.8_

  - [x] 6.4 Implementar persistência de mapeamentos
    - Implementar save_mapping() para armazenar em wp_options
    - Serializar arrays de mapeamento
    - Usar prefixo absloja_protheus_ para options
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6_

  - [x] 6.5 Escrever testes unitários para Mapping_Engine
    - Testar recuperação de mapeamentos
    - Testar validação de mapeamentos inválidos
    - Testar persistência de mapeamentos
    - Testar fallback para mapeamentos padrão

  - [x] 6.6 Escrever teste de propriedade para validação
    - **Property 46: Mapping Validation**
    - **Validates: Requirements 10.8**

- [x] 7. Implementar sincronização de clientes (Customer_Sync)
  - [x] 7.1 Criar classe Customer_Sync
    - Implementar ensure_customer_exists() que verifica/cria cliente
    - Implementar check_customer_exists() que consulta Protheus
    - Implementar create_customer() que cria no Protheus
    - Implementar clean_document() para limpar CPF/CNPJ
    - Injetar dependências: Auth_Manager, Mapping_Engine, Logger
    - _Requirements: 2.1, 2.2_

  - [x] 7.2 Implementar mapeamento de campos de cliente
    - Mapear billing fields para campos SA1
    - Extrair CPF de _billing_cpf ou CNPJ de _billing_cnpj
    - Concatenar first_name e last_name para A1_NOME
    - Determinar A1_TIPO baseado no tamanho do documento (11=F, 14=J)
    - Limpar formatação de CEP e telefone
    - _Requirements: 2.3, 2.4, 2.5_

  - [x] 7.3 Implementar tratamento de erros de criação
    - Logar erro se criação falhar
    - Retornar null para abortar sync de pedido
    - Agendar retry via Retry_Manager
    - _Requirements: 2.6_

  - [x] 7.4 Escrever testes unitários para Customer_Sync
    - Testar verificação de cliente existente
    - Testar criação de novo cliente
    - Testar limpeza de CPF/CNPJ
    - Testar concatenação de nome
    - Testar determinação de tipo (F/J)

  - [x] 7.5 Escrever testes de propriedade para Customer_Sync
    - **Property 8: Customer Existence Check**
    - **Property 9: Customer Creation on Non-Existence**
    - **Property 10: Customer Field Mapping**
    - **Property 11: CPF/CNPJ Extraction and Cleaning**
    - **Property 12: Name Concatenation**
    - **Property 13: Order Sync Abortion on Customer Creation Failure**
    - **Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.5, 2.6**

- [x] 8. Implementar sincronização de pedidos (Order_Sync)
  - [x] 8.1 Criar classe Order_Sync
    - Implementar sync_order() para enviar pedido ao Protheus
    - Implementar sync_order_status() para atualizar status
    - Implementar cancel_order() para cancelamento
    - Implementar refund_order() para reembolso
    - Injetar dependências: Auth_Manager, Customer_Sync, Mapping_Engine, Logger, Retry_Manager
    - _Requirements: 1.1, 15.1, 15.2_

  - [x] 8.2 Implementar hook woocommerce_order_status_processing
    - Registrar hook no Loader
    - Chamar sync_order() quando status muda para processing
    - Verificar se pedido já foi sincronizado (_protheus_order_id)
    - _Requirements: 1.1_

  - [x] 8.3 Implementar mapeamento de pedido para SC5/SC6
    - Mapear campos do pedido para SC5 (header)
    - Mapear line items para SC6 (itens)
    - Incluir WooCommerce Order ID em C5_PEDWOO
    - Mapear payment_method usando Mapping_Engine
    - Determinar TES usando get_tes_by_state()
    - Calcular valores de frete e desconto
    - _Requirements: 1.2, 1.3, 1.6, 1.7, 1.8_

  - [x] 8.4 Implementar verificação/criação de cliente
    - Chamar Customer_Sync->ensure_customer_exists() antes de enviar pedido
    - Abortar sync se criação de cliente falhar
    - Usar código do cliente retornado em C5_CLIENTE
    - _Requirements: 2.1, 2.2, 2.6_

  - [x] 8.5 Implementar armazenamento de resultado
    - Armazenar Protheus order ID em _protheus_order_id
    - Armazenar data de sync em _protheus_sync_date
    - Armazenar status em _protheus_sync_status
    - Armazenar código do cliente em _protheus_customer_code
    - _Requirements: 1.4_

  - [x] 8.6 Implementar tratamento de erros
    - Logar erro se API retornar erro
    - Agendar retry via Retry_Manager
    - Adicionar admin note ao pedido com detalhes do erro
    - Tratar erros específicos: TES, estoque insuficiente
    - _Requirements: 1.5, 12.1, 12.2, 12.4_

  - [x] 8.7 Implementar sincronização de cancelamento e reembolso
    - Registrar hooks para status cancelled e refunded
    - Mapear status WooCommerce para Protheus
    - Enviar requisição de atualização ao Protheus
    - Logar e agendar retry em caso de falha
    - _Requirements: 15.1, 15.2, 15.3, 15.4_

  - [x] 8.8 Implementar prevenção de mudança de status em falha
    - Verificar _protheus_sync_status antes de permitir mudança
    - Bloquear mudanças se sync falhou
    - Exibir notice administrativa explicando bloqueio
    - _Requirements: 15.5_

  - [x] 8.9 Escrever testes unitários para Order_Sync
    - Testar sync de pedido bem-sucedido
    - Testar mapeamento de campos SC5/SC6
    - Testar determinação de TES por estado
    - Testar cancelamento e reembolso
    - Testar tratamento de erro de TES
    - Testar tratamento de estoque insuficiente

  - [x] 8.10 Escrever testes de propriedade para Order_Sync
    - **Property 1: Order Sync Trigger on Status Change**
    - **Property 2: Complete Order Field Mapping**
    - **Property 3: Protheus Order ID Storage**
    - **Property 4: Error Logging and Retry Scheduling**
    - **Property 5: WooCommerce Order ID Inclusion**
    - **Property 6: Payment Method Mapping**
    - **Property 7: TES Determination by State**
    - **Property 49: TES Error Handling**
    - **Property 50: Stock Insufficient Error Handling**
    - **Property 57: Order Cancellation Sync**
    - **Property 58: Order Refund Sync**
    - **Property 59: Bidirectional Status Mapping**
    - **Property 60: Status Update Retry on Failure**
    - **Property 61: Status Change Prevention on Sync Failure**
    - **Validates: Requirements 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 12.1, 12.2, 15.1, 15.2, 15.3, 15.4, 15.5**

- [x] 9. Checkpoint - Validar fluxo de pedidos
  - Testar criação de pedido WooCommerce
  - Verificar se cliente é criado/verificado no Protheus
  - Verificar se pedido é enviado ao Protheus
  - Verificar se metadata é armazenado corretamente
  - Verificar logs de operação
  - Ensure all tests pass, ask the user if questions arise.

- [x] 10. Implementar sincronização de catálogo (Catalog_Sync)
  - [x] 10.1 Criar classe Catalog_Sync
    - Implementar sync_products() para sincronização em batch
    - Implementar sync_stock() para atualização de estoque
    - Implementar sync_single_product() para produto individual
    - Implementar sync_single_stock() para estoque individual
    - Injetar dependências: Auth_Manager, Mapping_Engine, Logger
    - _Requirements: 3.1, 4.1_

  - [x] 10.2 Implementar busca e processamento de produtos
    - Buscar produtos do Protheus via GET /api/v1/products
    - Implementar paginação com batch_size configurável
    - Para cada produto: verificar existência por SKU
    - Se existe: atualizar produto WooCommerce
    - Se não existe: criar novo produto WooCommerce
    - _Requirements: 3.1, 3.3, 3.4_

  - [x] 10.3 Implementar mapeamento de campos de produto
    - Mapear B1_COD para SKU
    - Mapear B1_DESC para product name
    - Mapear B1_PRV1 para regular_price
    - Mapear B1_PESO para weight
    - Mapear B1_MSBLQL para status (1=draft, 2=publish)
    - Mapear B1_GRUPO para categories usando Mapping_Engine
    - Armazenar metadata _protheus_synced e _protheus_sync_date
    - _Requirements: 3.2, 3.5, 3.6, 3.7, 3.8, 3.9_

  - [x] 10.4 Implementar sincronização de estoque
    - Buscar estoque do Protheus via GET /api/v1/stock
    - Localizar produto WooCommerce por SKU (B2_COD)
    - Atualizar stock_quantity com B2_QATU
    - Se quantidade = 0: ocultar produto (visibility = hidden)
    - Se quantidade > 0 e estava oculto: restaurar visibilidade
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

  - [x] 10.5 Implementar gestão de imagens
    - Verificar se image_url_pattern está configurado
    - Substituir {sku} no pattern pelo SKU do produto
    - Baixar imagem via wp_remote_get()
    - Fazer upload para media library
    - Anexar imagem ao produto
    - Preservar imagens existentes se URL não fornecida
    - _Requirements: 14.1, 14.2, 14.3, 14.4_

  - [x] 10.6 Implementar prevenção de edição manual de preços
    - Adicionar hook woocommerce_product_options_pricing
    - Tornar campo de preço readonly para produtos sincronizados
    - Adicionar hook woocommerce_process_product_meta
    - Restaurar preço original se modificado manualmente
    - Exibir notice explicando que preço é sincronizado
    - _Requirements: 3.10_

  - [x] 10.7 Integrar com WP-Cron para sincronização agendada
    - Registrar hook absloja_protheus_sync_catalog
    - Registrar hook absloja_protheus_sync_stock
    - Agendar execução baseada em frequência configurada
    - _Requirements: 11.1, 11.2, 11.3, 11.4_

  - [x] 10.8 Escrever testes unitários para Catalog_Sync
    - Testar criação de novo produto
    - Testar atualização de produto existente
    - Testar mapeamento de campos
    - Testar atualização de estoque
    - Testar ocultação de produto com estoque zero
    - Testar download e anexação de imagem

  - [x] 10.9 Escrever testes de propriedade para Catalog_Sync
    - **Property 14: Product Data Fetching**
    - **Property 15: Product Field Mapping**
    - **Property 16: Product Update on Existing SKU**
    - **Property 17: Product Creation on New SKU**
    - **Property 18: Blocked Product Status**
    - **Property 19: Category Mapping**
    - **Property 20: Stock Data Fetching**
    - **Property 21: Stock Quantity Update**
    - **Property 22: Product Visibility on Zero Stock**
    - **Property 23: Product Visibility Restoration**
    - **Property 24: Stock Product Matching**
    - **Property 54: Image Download and Attachment**
    - **Property 55: Image Preservation**
    - **Property 56: Image URL Pattern Processing**
    - **Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9, 4.1, 4.2, 4.3, 4.4, 4.5, 14.2, 14.3, 14.4**

- [x] 11. Implementar processamento de webhooks (Webhook_Handler)
  - [x] 11.1 Criar classe Webhook_Handler
    - Implementar register_routes() para registrar endpoints REST
    - Implementar handle_order_status_update() para status de pedidos
    - Implementar handle_stock_update() para estoque
    - Implementar authenticate_webhook() para validação
    - Injetar dependências: Auth_Manager, Logger
    - _Requirements: 5.1, 6.1_

  - [x] 11.2 Implementar autenticação de webhooks
    - Suportar autenticação via X-Protheus-Token header
    - Suportar autenticação via X-Protheus-Signature (HMAC)
    - Comparar token/signature com valor configurado
    - Retornar 401 se autenticação falhar
    - _Requirements: 5.2, 6.2_

  - [x] 11.3 Implementar endpoint de atualização de status de pedido
    - Registrar POST /wp-json/absloja-protheus/v1/webhook/order-status
    - Validar payload recebido
    - Localizar pedido WooCommerce usando woo_order_id ou C5_PEDWOO
    - Mapear status Protheus para WooCommerce usando Mapping_Engine
    - Atualizar status do pedido
    - Armazenar tracking_code, invoice_number se fornecidos
    - Retornar 200 em sucesso, 404 se pedido não encontrado
    - _Requirements: 5.1, 5.3, 5.4, 5.6, 5.7_

  - [x] 11.4 Implementar endpoint de atualização de estoque
    - Registrar POST /wp-json/absloja-protheus/v1/webhook/stock
    - Validar payload recebido
    - Localizar produto WooCommerce por SKU
    - Atualizar stock_quantity
    - Se quantidade = 0: ocultar produto
    - Se quantidade > 0: restaurar visibilidade
    - Retornar 200 em sucesso
    - _Requirements: 6.1, 6.3, 6.4, 6.5, 6.7_

  - [x] 11.5 Implementar logging de webhooks
    - Logar todos os webhooks recebidos via Logger
    - Incluir timestamp, endpoint, payload, response
    - Logar erros de autenticação
    - _Requirements: 8.2_

  - [x] 11.6 Escrever testes unitários para Webhook_Handler
    - Testar autenticação bem-sucedida
    - Testar falha de autenticação (401)
    - Testar atualização de status de pedido
    - Testar pedido não encontrado (404)
    - Testar atualização de estoque
    - Testar ocultação de produto com estoque zero

  - [x] 11.7 Escrever testes de propriedade para Webhook_Handler
    - **Property 25: Webhook Authentication**
    - **Property 26: Order Location by WooCommerce ID**
    - **Property 27: Order Status Update**
    - **Property 28: Webhook Authentication Failure Response**
    - **Property 29: Order Not Found Response**
    - **Property 30: Webhook Success Response**
    - **Property 31: Product Location by SKU**
    - **Property 32: Stock Quantity Update via Webhook**
    - **Validates: Requirements 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7**

- [x] 12. Checkpoint - Validar sincronização e webhooks
  - Testar sincronização de catálogo completa
  - Testar sincronização de estoque
  - Testar recebimento de webhook de status
  - Testar recebimento de webhook de estoque
  - Verificar logs de todas as operações
  - Ensure all tests pass, ask the user if questions arise.

- [x] 13. Implementar interface administrativa
  - [x] 13.1 Criar classe Admin para gerenciar interface
    - Implementar add_menu_page() para adicionar menu no WordPress admin
    - Registrar menu sob WooCommerce
    - Implementar enqueue_scripts() para carregar assets
    - _Requirements: 13.1_

  - [x] 13.2 Criar classe Settings para gerenciar configurações
    - Implementar register_settings() usando Settings API
    - Organizar settings em tabs: Connection, Mappings, Sync Schedule, Logs, Advanced
    - Implementar validação de inputs
    - Exibir mensagens de erro para valores inválidos
    - _Requirements: 13.2, 13.6_

  - [x] 13.3 Implementar tab Connection
    - Campos para auth_type (Basic/OAuth2)
    - Campos para api_url, username, password
    - Campos para client_id, client_secret, token_endpoint (OAuth2)
    - Botão "Test Connection" que chama Auth_Manager->test_connection()
    - Exibir status da conexão (conectado/desconectado)
    - _Requirements: 7.6, 13.3, 13.4_

  - [x] 13.4 Implementar tab Mappings
    - Interface para configurar customer field mappings
    - Interface para configurar order field mappings
    - Interface para configurar product field mappings
    - Interface para configurar payment method mappings
    - Interface para configurar category mappings
    - Interface para configurar TES rules por estado
    - Interface para configurar status mappings
    - Botão "Reset to Defaults" para restaurar mapeamentos padrão
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6_

  - [x] 13.5 Implementar tab Sync Schedule
    - Dropdown para catalog_sync_frequency
    - Dropdown para stock_sync_frequency
    - Opções: 15min, 30min, 1hour, 6hours, 12hours, 24hours
    - Botão "Sync Catalog Now" para sincronização manual
    - Botão "Sync Stock Now" para sincronização manual
    - Exibir última sincronização e resultado
    - _Requirements: 11.1, 11.2, 11.3, 11.5, 11.6_

  - [x] 13.6 Implementar tab Logs (Log_Viewer)
    - Criar classe Log_Viewer
    - Exibir tabela de logs com paginação
    - Filtros por date range, type, status, operation
    - Botão "Export to CSV"
    - Link para visualizar detalhes de cada log
    - _Requirements: 8.5, 8.6, 8.7_

  - [x] 13.7 Implementar tab Advanced
    - Campo para batch_size
    - Campo para retry_interval
    - Campo para max_retries
    - Campo para log_retention (dias)
    - Campo para webhook_token
    - Campo para webhook_secret
    - Campo para image_url_pattern
    - Texto de ajuda contextual para cada opção
    - _Requirements: 13.7, 14.4_

  - [x] 13.8 Implementar dashboard widget
    - Criar widget para WordPress dashboard
    - Exibir estatísticas: last sync time, products synced, orders synced
    - Exibir contagem de erros recentes
    - Exibir pedidos pendentes de revisão manual
    - Link rápido para página de configurações
    - _Requirements: 12.5, 13.5_

  - [x] 13.9 Implementar interface de retry manual
    - Exibir lista de operações na fila de retry
    - Mostrar operation_type, attempts, next_attempt, last_error
    - Botão "Retry Now" para cada operação
    - Botão "Mark as Failed" para desistir de retry
    - _Requirements: 9.6, 9.7_

  - [x] 13.10 Escrever testes unitários para Admin
    - Testar registro de menu
    - Testar validação de configurações
    - Testar salvamento de settings
    - Testar test connection

  - [x] 13.11 Escrever teste de propriedade para validação de inputs
    - **Property 53: Configuration Input Validation**
    - **Validates: Requirements 13.6**

- [x] 14. Implementar assets (CSS e JavaScript)
  - [x] 14.1 Criar arquivo assets/css/admin.css
    - Estilos para tabs de configuração
    - Estilos para tabela de logs
    - Estilos para dashboard widget
    - Estilos para botões de ação
    - Estilos para mensagens de status

  - [x] 14.2 Criar arquivo assets/js/admin.js
    - Script para navegação entre tabs
    - Script para botão "Test Connection" (AJAX)
    - Script para botões de sincronização manual (AJAX)
    - Script para filtros de logs
    - Script para exportação de CSV
    - Script para retry manual (AJAX)
    - Feedback visual para operações assíncronas

- [x] 15. Implementar tratamento avançado de erros
  - [x] 15.1 Implementar detecção de erros de rede
    - Detectar timeout, DNS failure, SSL errors
    - Logar com contexto completo
    - Agendar retry automático
    - _Requirements: 12.3_

  - [x] 15.2 Implementar tratamento de erros de negócio
    - Detectar erro de TES não encontrado
    - Detectar erro de estoque insuficiente
    - Detectar erro de CPF/CNPJ inválido
    - Adicionar admin note ao pedido/produto
    - Marcar para revisão manual
    - Não agendar retry automático
    - _Requirements: 12.1, 12.2, 12.4_

  - [x] 15.3 Implementar notificações administrativas
    - Email para falhas permanentes
    - Dashboard notice para erros de configuração
    - Admin note em pedidos com erro
    - _Requirements: 9.4, 12.4_

  - [x] 15.4 Escrever testes unitários para tratamento de erros
    - Testar detecção de timeout
    - Testar detecção de erro de TES
    - Testar detecção de estoque insuficiente
    - Testar adição de admin notes

  - [x] 15.5 Escrever testes de propriedade para erros
    - **Property 35: Authentication Failure Handling**
    - **Property 51: API Unreachable Error Handling**
    - **Property 52: Business Error Order Notes**
    - **Validates: Requirements 7.5, 12.1, 12.2, 12.3, 12.4**

- [x] 16. Implementar agendamento WP-Cron
  - [x] 16.1 Registrar eventos WP-Cron na ativação
    - Registrar absloja_protheus_sync_catalog
    - Registrar absloja_protheus_sync_stock
    - Registrar absloja_protheus_process_retries
    - Registrar absloja_protheus_cleanup_logs
    - Usar frequências configuradas pelo usuário
    - _Requirements: 11.4_

  - [x] 16.2 Implementar callbacks dos eventos
    - Callback para sync_catalog que chama Catalog_Sync->sync_products()
    - Callback para sync_stock que chama Catalog_Sync->sync_stock()
    - Callback para process_retries que chama Retry_Manager->process_retries()
    - Callback para cleanup_logs que chama Logger->cleanup_old_logs()

  - [x] 16.3 Limpar eventos WP-Cron na desativação
    - Remover todos os eventos agendados
    - Implementar no Deactivator

  - [x] 16.4 Escrever teste de propriedade para agendamento
    - **Property 47: WP-Cron Scheduling**
    - **Property 48: Manual Sync Execution**
    - **Validates: Requirements 11.4, 11.6**

- [x] 17. Checkpoint - Validar interface administrativa
  - Testar acesso à página de configurações
  - Testar salvamento de configurações em cada tab
  - Testar botão "Test Connection"
  - Testar sincronização manual
  - Testar visualização de logs
  - Testar exportação de CSV
  - Testar dashboard widget
  - Ensure all tests pass, ask the user if questions arise.

- [x] 18. Configurar ambiente de testes
  - [x] 18.1 Configurar PHPUnit
    - Criar arquivo phpunit.xml
    - Configurar testsuites: unit, property, integration
    - Configurar bootstrap para WordPress test suite
    - Configurar coverage reporting

  - [x] 18.2 Instalar dependências de teste
    - Instalar PHPUnit via Composer
    - Instalar php-quickcheck ou Eris para property-based testing
    - Instalar WP_Mock para mocking de funções WordPress
    - Instalar Mockery para mocking de classes

  - [x] 18.3 Criar estrutura de diretórios de teste
    - Criar tests/unit/ com subdiretórios por módulo
    - Criar tests/property/ para testes de propriedade
    - Criar tests/integration/ para testes de integração
    - Criar tests/fixtures/ para dados de teste

  - [x] 18.4 Criar fixtures e mocks
    - Criar fixtures/orders.php com dados de pedidos
    - Criar fixtures/customers.php com dados de clientes
    - Criar fixtures/products.php com dados de produtos
    - Criar fixtures/api-responses.php com respostas simuladas
    - Criar ProtheusClientMock para simular API

  - [x] 18.5 Criar generators customizados para property tests
    - Criar WooCommerceGenerators::order()
    - Criar WooCommerceGenerators::billingAddress()
    - Criar WooCommerceGenerators::product()
    - Criar ProtheusGenerators::sa1()
    - Criar ProtheusGenerators::sc5()
    - Criar ProtheusGenerators::sb1()

- [x] 19. Escrever testes de integração
  - [x] 19.1 Escrever teste de integração para fluxo completo de pedido
    - Criar pedido WooCommerce
    - Verificar criação/verificação de cliente
    - Verificar envio de pedido ao Protheus
    - Verificar armazenamento de metadata
    - Verificar logs criados
    - Simular webhook de status
    - Verificar atualização de status no WooCommerce

  - [x] 19.2 Escrever teste de integração para sincronização de catálogo
    - Simular resposta da API com produtos
    - Executar sync_products()
    - Verificar criação de produtos no WooCommerce
    - Verificar mapeamento de campos
    - Verificar categorias atribuídas
    - Executar sync_stock()
    - Verificar atualização de quantidades

  - [x] 19.3 Escrever teste de integração para sistema de retry
    - Simular falha de API
    - Verificar agendamento de retry
    - Simular execução de WP-Cron
    - Verificar reprocessamento
    - Verificar remoção da fila após sucesso

- [x] 20. Implementar internacionalização
  - [x] 20.1 Preparar plugin para tradução
    - Adicionar text domain 'absloja-protheus-connector' em todas as strings
    - Usar __(), _e(), esc_html__() para strings traduzíveis
    - Criar arquivo languages/absloja-protheus-connector.pot

  - [x] 20.2 Criar tradução pt_BR
    - Criar arquivo languages/absloja-protheus-connector-pt_BR.po
    - Traduzir todas as strings da interface administrativa
    - Traduzir mensagens de erro
    - Gerar arquivo .mo

- [x] 21. Criar documentação
  - [x] 21.1 Criar README.md
    - Descrição do plugin
    - Requisitos (WordPress, WooCommerce, PHP)
    - Instruções de instalação
    - Configuração inicial
    - Troubleshooting básico

  - [x] 21.2 Criar documentação de API
    - Documentar endpoints REST do plugin
    - Documentar formato de webhooks esperados
    - Documentar estrutura de mapeamentos
    - Exemplos de payloads

  - [x] 21.3 Criar guia de desenvolvimento
    - Estrutura do código
    - Como adicionar novos mapeamentos
    - Como estender funcionalidades
    - Como executar testes

- [x] 22. Finalizar e preparar para produção
  - [x] 22.1 Revisar segurança
    - Validar e sanitizar todos os inputs
    - Verificar nonces em formulários
    - Verificar capabilities em ações administrativas
    - Verificar escape de outputs
    - Revisar armazenamento de credenciais

  - [x] 22.2 Otimizar performance
    - Implementar caching de mapeamentos
    - Otimizar queries de banco de dados
    - Implementar processamento em batch para grandes volumes
    - Adicionar índices nas tabelas customizadas

  - [x] 22.3 Testar compatibilidade
    - Testar com WordPress 6.0+
    - Testar com WooCommerce 7.0+
    - Testar com PHP 7.4, 8.0, 8.1, 8.2
    - Testar em diferentes ambientes de hospedagem

  - [x] 22.4 Criar arquivo de distribuição
    - Criar arquivo composer.json
    - Criar arquivo .gitignore
    - Criar arquivo LICENSE
    - Preparar package para WordPress.org (se aplicável)

- [x] 23. Checkpoint final - Validação completa
  - Executar todos os testes unitários
  - Executar todos os testes de propriedade
  - Executar todos os testes de integração
  - Verificar cobertura de código (meta: 80%+)
  - Testar fluxo completo end-to-end
  - Verificar logs e tratamento de erros
  - Validar interface administrativa
  - Ensure all tests pass, ask the user if questions arise.


## Notes

- Tarefas marcadas com `*` são opcionais e podem ser puladas para um MVP mais rápido
- Cada tarefa referencia requisitos específicos para rastreabilidade
- Checkpoints garantem validação incremental
- Testes de propriedade validam as 61 propriedades de corretude do design
- Testes unitários validam exemplos específicos e casos extremos
- A implementação segue padrões WordPress e WooCommerce
- Todas as credenciais são criptografadas antes do armazenamento
- Sistema de retry automático garante resiliência
- Logging completo permite auditoria e troubleshooting
- Interface administrativa centraliza toda a configuração

## Property-Based Testing Summary

O plugin implementa 61 propriedades de corretude que devem ser validadas através de testes baseados em propriedades:

- Properties 1-7: Order Sync (sincronização de pedidos)
- Properties 8-13: Customer Sync (sincronização de clientes)
- Properties 14-19: Product Sync (sincronização de produtos)
- Properties 20-24: Stock Sync (sincronização de estoque)
- Properties 25-30: Order Status Webhooks
- Properties 31-32: Stock Webhooks
- Properties 33-35: Authentication
- Properties 36-41: Logging
- Properties 42-45: Retry Management
- Property 46: Mapping Validation
- Properties 47-48: WP-Cron Scheduling
- Properties 49-52: Error Handling
- Property 53: Configuration Validation
- Properties 54-56: Image Management
- Properties 57-61: Bidirectional Status Sync

Cada teste de propriedade deve:
- Executar no mínimo 100 iterações com dados gerados aleatoriamente
- Usar tag format: `@test Feature: absloja-protheus-connector, Property N: [Title]`
- Referenciar os requisitos validados
- Usar generators customizados para dados realistas

## Testing Configuration

```php
// phpunit.xml
<phpunit>
    <testsuites>
        <testsuite name="unit">
            <directory>tests/unit</directory>
        </testsuite>
        <testsuite name="property">
            <directory>tests/property</directory>
        </testsuite>
        <testsuite name="integration">
            <directory>tests/integration</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

## Execution Order

A ordem de implementação foi projetada para:
1. Estabelecer infraestrutura base primeiro
2. Construir módulos core (auth, HTTP, logs, retry, mapeamentos)
3. Implementar sincronizações (clientes, pedidos, catálogo)
4. Adicionar webhooks para comunicação bidirecional
5. Criar interface administrativa
6. Finalizar com testes abrangentes e documentação

Cada checkpoint permite validação incremental antes de prosseguir.
