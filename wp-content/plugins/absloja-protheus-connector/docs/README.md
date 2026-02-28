# ABS Loja Protheus Connector
## Documentação Completa

**Versão:** 1.0.0  
**Desenvolvido por:** Fale Agência Digital  
**Cliente:** ABS Global  
**Data:** Fevereiro 2024

---

## Índice

1. [Visão Geral](#visão-geral)
2. [Instalação e Configuração](#instalação-e-configuração)
3. [Documentação de Integração](#documentação-de-integração)
4. [Guia de Desenvolvimento](#guia-de-desenvolvimento)
5. [Documentação da API](#documentação-da-api)
6. [Segurança](#segurança)
7. [Performance](#performance)
8. [Testes](#testes)
9. [Suporte](#suporte)

---

## Visão Geral

### O que é o ABS Loja Protheus Connector?

Plugin WordPress que integra WooCommerce com TOTVS Protheus ERP através de REST API, automatizando sincronização de pedidos, clientes, produtos e estoque.

### Funcionalidades Principais

- ✅ **Sincronização de Pedidos**: Envio automático para Protheus
- ✅ **Sincronização de Clientes**: Criação/atualização automática
- ✅ **Sincronização de Catálogo**: Importação de produtos
- ✅ **Sincronização de Estoque**: Atualização em tempo real
- ✅ **Webhooks**: Recebimento de atualizações do Protheus
- ✅ **Sistema de Retry**: Tentativas automáticas em falhas
- ✅ **Logs Completos**: Rastreabilidade total

### Requisitos

- WordPress 6.0+
- WooCommerce 7.0+
- PHP 7.4+
- MySQL 5.7+
- HTTPS (obrigatório)

---

## Instalação e Configuração

### 1. Instalação

1. Faça upload da pasta do plugin para `/wp-content/plugins/`
2. Ative o plugin em **WordPress Admin → Plugins**
3. Verifique se o WooCommerce está ativo

### 2. Configuração Inicial

Acesse **WooCommerce → Protheus Connector**

#### Aba Conexão

1. **URL da API**: Insira a URL base do Protheus
2. **Tipo de Autenticação**: Escolha Basic ou OAuth2
3. **Credenciais**: Preencha usuário/senha ou client_id/secret
4. **Testar Conexão**: Valide as configurações

#### Aba Mapeamentos

1. **Formas de Pagamento**: Mapeie métodos WooCommerce → Protheus
2. **Categorias**: Mapeie grupos Protheus → Categorias WooCommerce
3. **TES por Estado**: Defina códigos TES por UF
4. **Status**: Mapeie status Protheus → WooCommerce

#### Aba Agendamento

1. **Frequência de Catálogo**: Configure sincronização de produtos
2. **Frequência de Estoque**: Configure sincronização de estoque
3. **Sincronização Manual**: Botões para sync imediata

#### Aba Avançado

1. **Tamanho do Lote**: Itens por batch (padrão: 50)
2. **Timeout**: Tempo máximo de requisição (padrão: 30s)
3. **Max Retries**: Tentativas máximas (padrão: 5)
4. **Retenção de Logs**: Dias para manter logs (padrão: 30)
5. **Webhook Token**: Token de autenticação para webhooks

---

## Documentação de Integração

### Endpoints Necessários no Protheus

#### 1. Clientes (SA1)

**POST /api/v1/customers** - Criar cliente
```json
{
  "codigo": "CLI001",
  "loja": "01",
  "nome": "João da Silva",
  "cpf_cnpj": "12345678901",
  "email": "joao@email.com",
  "endereco": {
    "logradouro": "Rua Exemplo",
    "numero": "123",
    "cidade": "São Paulo",
    "estado": "SP",
    "cep": "01234567"
  }
}
```

**PUT /api/v1/customers/{codigo}/{loja}** - Atualizar cliente

**GET /api/v1/customers/{codigo}/{loja}** - Consultar cliente

#### 2. Produtos (SB1)

**GET /api/v1/products** - Listar produtos
- Query params: `page`, `per_page`, `grupo`, `ativo`

**GET /api/v1/products/{codigo}** - Consultar produto específico

#### 3. Estoque (SB2)

**GET /api/v1/stock** - Consultar estoque
- Query params: `produtos[]`, `armazem`

**GET /api/v1/stock/{codigo}** - Consultar estoque de um produto

#### 4. Pedidos (SC5/SC6)

**POST /api/v1/orders** - Criar pedido
```json
{
  "cliente": {
    "codigo": "CLI001",
    "loja": "01"
  },
  "condicao_pagamento": "001",
  "itens": [
    {
      "produto": "PROD001",
      "quantidade": 2,
      "preco_unitario": 99.90,
      "tes": "501"
    }
  ]
}
```

**GET /api/v1/orders/{numero}** - Consultar pedido

**PUT /api/v1/orders/{numero}/cancel** - Cancelar pedido

#### 5. Webhooks (Protheus → WooCommerce)

**URL do WooCommerce:**
```
https://seusite.com.br/wp-json/absloja-protheus/v1/webhook
```

**Autenticação:**
```
Header: X-Protheus-Token: {token_configurado}
```

**Eventos:**
- `order.status.updated` - Atualização de status de pedido
- `stock.updated` - Atualização de estoque

### Fluxo de Integração

#### Criação de Pedido
```
Cliente finaliza compra
    ↓
WooCommerce cria pedido (status: processing)
    ↓
Plugin captura evento
    ↓
Valida dados do pedido
    ↓
Sincroniza/Cria cliente no Protheus
    ↓
Cria pedido no Protheus
    ↓
Salva ID do Protheus no WooCommerce
    ↓
Atualiza status (ou adiciona à fila de retry)
```

#### Sincronização de Estoque
```
WP-Cron executa job (a cada 15 min)
    ↓
Consulta produtos WooCommerce ativos
    ↓
Consulta estoque no Protheus
    ↓
Processa em lotes de 50
    ↓
Atualiza quantidades
    ↓
Oculta produtos sem estoque
    ↓
Registra em log
```

### Autenticação

#### Basic Authentication
```
Authorization: Basic base64(username:password)
```

#### OAuth 2.0
```
1. POST /oauth2/token
2. Recebe access_token
3. Authorization: Bearer {access_token}
```

### Códigos de Status HTTP

| Código | Significado |
|--------|-------------|
| 200 | OK |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 404 | Not Found |
| 422 | Validation Error |
| 500 | Internal Server Error |

---

## Guia de Desenvolvimento

### Estrutura do Plugin

```
absloja-protheus-connector/
├── includes/
│   ├── class-plugin.php          # Classe principal
│   ├── class-loader.php           # Gerenciador de hooks
│   ├── class-activator.php        # Ativação
│   ├── class-deactivator.php      # Desativação
│   ├── admin/                     # Interface admin
│   │   ├── class-admin.php
│   │   ├── class-settings.php
│   │   └── views/                 # Templates
│   ├── api/
│   │   └── class-protheus-client.php  # Cliente HTTP
│   ├── modules/
│   │   ├── class-auth-manager.php     # Autenticação
│   │   ├── class-order-sync.php       # Sync pedidos
│   │   ├── class-customer-sync.php    # Sync clientes
│   │   ├── class-catalog-sync.php     # Sync produtos
│   │   ├── class-webhook-handler.php  # Webhooks
│   │   ├── class-retry-manager.php    # Retries
│   │   ├── class-logger.php           # Logs
│   │   └── class-mapping-engine.php   # Mapeamentos
│   └── database/
│       └── class-schema.php       # Schema do BD
├── assets/
│   ├── css/
│   └── js/
├── languages/                     # Traduções
├── tests/                         # Testes
└── docs/                          # Documentação
```

### Principais Classes

#### Plugin
Classe principal que inicializa todos os módulos.

```php
$plugin = ABSLoja\ProtheusConnector\Plugin::get_instance();
$plugin->run();
```

#### Protheus_Client
Cliente HTTP para comunicação com API.

```php
$client = new Protheus_Client($auth_manager, $api_url);
$response = $client->post('/api/v1/orders', $data);
```

#### Order_Sync
Sincronização de pedidos.

```php
$order_sync = new Order_Sync($client, $customer_sync, $mapper, $logger, $retry_manager);
$order_sync->sync_order($order);
```

### Hooks Disponíveis

#### Actions
```php
// Após sincronização de pedido
do_action('absloja_protheus_order_synced', $order_id, $protheus_order_id);

// Após sincronização de cliente
do_action('absloja_protheus_customer_synced', $customer_id, $protheus_customer_id);

// Após sincronização de estoque
do_action('absloja_protheus_stock_synced', $product_id, $quantity);
```

#### Filters
```php
// Modificar dados do pedido antes de enviar
apply_filters('absloja_protheus_order_data', $order_data, $order);

// Modificar dados do cliente antes de enviar
apply_filters('absloja_protheus_customer_data', $customer_data, $customer);

// Modificar mapeamento de TES
apply_filters('absloja_protheus_tes_code', $tes_code, $state, $order);
```

### Banco de Dados

#### Tabela: wp_absloja_logs
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
  INDEX idx_timestamp (timestamp),
  INDEX idx_type (type),
  INDEX idx_status (status)
);
```

#### Tabela: wp_absloja_retry_queue
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
  INDEX idx_next_attempt (next_attempt)
);
```

---

## Documentação da API

### Formato de Resposta Padrão

#### Sucesso
```json
{
  "success": true,
  "data": { ... },
  "message": "Operação realizada com sucesso"
}
```

#### Erro
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Dados inválidos",
    "details": [
      {
        "field": "cpf_cnpj",
        "message": "CPF inválido"
      }
    ]
  }
}
```

### Headers Obrigatórios

**Request:**
```
Content-Type: application/json
Accept: application/json
Authorization: Basic {credentials} ou Bearer {token}
User-Agent: ABS-Protheus-Connector/1.0.0
```

**Response:**
```
Content-Type: application/json; charset=utf-8
X-Request-ID: {uuid}
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
```

---

## Segurança

### Boas Práticas Implementadas

✅ **HTTPS Obrigatório**: Todas as comunicações usam SSL/TLS  
✅ **Credenciais Criptografadas**: Senhas armazenadas com criptografia  
✅ **Validação de Dados**: Sanitização de todos os inputs  
✅ **Rate Limiting**: Proteção contra abuso  
✅ **Logs de Auditoria**: Registro de todas as operações  
✅ **Webhook Authentication**: Token único para validação  

### Configurações de Segurança

1. **Regenerar Token de Webhook**: Aba Avançado → Webhook Token → Gerar Novo
2. **Rotação de Credenciais**: Alterar senha/token periodicamente
3. **Monitoramento de Logs**: Verificar logs regularmente
4. **Limite de Tentativas**: Configurar max_retries adequadamente

---

## Performance

### Otimizações Implementadas

✅ **Processamento em Lote**: Batch de 50 itens por vez  
✅ **Cache de Autenticação**: Token OAuth2 em cache  
✅ **Índices de Banco**: Otimização de queries  
✅ **Cron Jobs Assíncronos**: Não bloqueia requisições  
✅ **Timeout Configurável**: Evita travamentos  

### Recomendações

- **Batch Size**: 50-100 para melhor performance
- **Frequência de Sync**: 15-30 minutos para estoque
- **Retenção de Logs**: 30 dias (limpar periodicamente)
- **Timeout**: 30-60 segundos dependendo da API

---

## Testes

### Testes Manuais

Execute o script de teste:
```
http://seusite.com.br/wp-content/plugins/absloja-protheus-connector/test-activation.php
```

### Checklist de Testes

- [ ] Ativação do plugin
- [ ] Configuração de conexão
- [ ] Teste de conexão com API
- [ ] Criação de pedido
- [ ] Sincronização de cliente
- [ ] Sincronização de estoque
- [ ] Recebimento de webhook
- [ ] Sistema de retry
- [ ] Visualização de logs
- [ ] Exportação de logs

---

## Suporte

### Fale Agência Digital

- 🌐 **Website:** https://faleagencia.digital
- 📧 **Email:** contato@faleagencia.digital
- 📧 **Suporte Técnico:** dev@faleagencia.digital
- ⏰ **Horário:** Segunda a Sexta, 9h às 18h

### Documentação Adicional

- **Guia de Testes Manuais**: `MANUAL-TESTING-GUIDE.md`
- **Guia de Tradução**: `TRADUCAO-COMPLETA.md`
- **Correções de Ativação**: `ACTIVATION-FIX.md`
- **Compatibilidade WooCommerce**: `WOOCOMMERCE-COMPATIBILITY-FIX.md`

---

## Changelog

### Versão 1.0.0 (Fevereiro 2024)

**Lançamento Inicial**

- ✅ Sincronização de pedidos
- ✅ Sincronização de clientes
- ✅ Sincronização de catálogo
- ✅ Sincronização de estoque
- ✅ Sistema de webhooks
- ✅ Sistema de retry
- ✅ Logs completos
- ✅ Interface administrativa
- ✅ Tradução pt_BR completa
- ✅ Compatibilidade WooCommerce 10.5+
- ✅ Compatibilidade HPOS

---

## Licença

GPL v3 ou posterior

---

© 2024 Fale Agência Digital. Desenvolvido para ABS Global.
