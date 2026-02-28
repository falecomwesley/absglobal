# Documentação de Integração
## ABS Loja Protheus Connector

**Versão:** 1.0.0  
**Data:** Fevereiro 2024  
**Desenvolvido por:** Fale Agência Digital  
**Cliente:** ABS Global

---

## Índice

1. [Resumo Executivo](#resumo-executivo)
2. [Sobre o Plugin](#sobre-o-plugin)
3. [Arquitetura da Solução](#arquitetura-da-solução)
4. [Requisitos Técnicos](#requisitos-técnicos)
5. [Endpoints Necessários](#endpoints-necessários)
6. [Especificações de API](#especificações-de-api)
7. [Fluxos de Integração](#fluxos-de-integração)
8. [Segurança e Autenticação](#segurança-e-autenticação)
9. [Tratamento de Erros](#tratamento-de-erros)
10. [Cronograma de Implementação](#cronograma-de-implementação)

---

## 1. Resumo Executivo

### Objetivo do Projeto

Desenvolver uma integração bidirecional entre a loja virtual WooCommerce e o sistema ERP TOTVS Protheus, automatizando processos de sincronização de dados e reduzindo trabalho manual.

### Benefícios Esperados

- ✅ **Automação Total**: Sincronização automática de pedidos, clientes, produtos e estoque
- ✅ **Redução de Erros**: Eliminação de digitação manual e inconsistências de dados
- ✅ **Tempo Real**: Atualização imediata de informações entre sistemas
- ✅ **Escalabilidade**: Suporte a alto volume de transações
- ✅ **Rastreabilidade**: Logs completos de todas as operações

### Escopo da Integração

| Módulo | Direção | Frequência |
|--------|---------|------------|
| Pedidos | WooCommerce → Protheus | Tempo Real |
| Clientes | WooCommerce → Protheus | Tempo Real |
| Produtos | Protheus → WooCommerce | Agendada |
| Estoque | Protheus → WooCommerce | Agendada |
| Status de Pedidos | Protheus → WooCommerce | Webhook |

---

## 2. Sobre o Plugin

### O que é o ABS Loja Protheus Connector?

O **ABS Loja Protheus Connector** é um plugin WordPress desenvolvido especificamente para integrar lojas WooCommerce com o ERP TOTVS Protheus através de REST API.

### Funcionalidades Principais

#### 2.1 Sincronização de Pedidos
- Envio automático de pedidos do WooCommerce para o Protheus
- Criação de clientes automaticamente se não existirem
- Mapeamento de formas de pagamento
- Aplicação de regras de TES por estado
- Cálculo automático de impostos

#### 2.2 Sincronização de Clientes
- Criação/atualização de cadastros de clientes
- Validação de CPF/CNPJ
- Sincronização de endereços de entrega e cobrança
- Mapeamento de campos customizados

#### 2.3 Sincronização de Catálogo
- Importação de produtos do Protheus
- Atualização de preços e descrições
- Sincronização de imagens
- Mapeamento de categorias

#### 2.4 Sincronização de Estoque
- Atualização em tempo real de quantidades
- Controle de disponibilidade
- Ocultação automática de produtos sem estoque
- Restauração de produtos quando voltam ao estoque


#### 2.5 Webhooks
- Recebimento de atualizações do Protheus
- Notificações de mudança de status
- Atualização de estoque via webhook
- Autenticação segura com token

#### 2.6 Sistema de Retry
- Tentativas automáticas em caso de falha
- Fila de operações pendentes
- Intervalo configurável entre tentativas
- Limite de tentativas configurável

#### 2.7 Logs e Monitoramento
- Registro detalhado de todas as operações
- Filtros por tipo, status e data
- Exportação de logs para CSV
- Retenção configurável de logs

### Tecnologias Utilizadas

- **WordPress:** 6.0+
- **WooCommerce:** 7.0+
- **PHP:** 7.4+
- **REST API:** JSON
- **Autenticação:** Basic Auth / OAuth2
- **Banco de Dados:** MySQL 5.7+

---

## 3. Arquitetura da Solução

### Diagrama de Arquitetura

```
┌─────────────────────────────────────────────────────────────┐
│                    LOJA WOOCOMMERCE                         │
│  ┌──────────────────────────────────────────────────────┐  │
│  │         ABS Loja Protheus Connector Plugin          │  │
│  │                                                       │  │
│  │  ┌─────────────┐  ┌──────────────┐  ┌────────────┐ │  │
│  │  │   Order     │  │   Customer   │  │  Catalog   │ │  │
│  │  │    Sync     │  │     Sync     │  │    Sync    │ │  │
│  │  └─────────────┘  └──────────────┘  └────────────┘ │  │
│  │                                                       │  │
│  │  ┌─────────────┐  ┌──────────────┐  ┌────────────┐ │  │
│  │  │   Webhook   │  │    Retry     │  │   Logger   │ │  │
│  │  │   Handler   │  │   Manager    │  │            │ │  │
│  │  └─────────────┘  └──────────────┘  └────────────┘ │  │
│  │                                                       │  │
│  │  ┌──────────────────────────────────────────────┐   │  │
│  │  │         Protheus REST API Client             │   │  │
│  │  └──────────────────────────────────────────────┘   │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            ↕ HTTPS/REST API
┌─────────────────────────────────────────────────────────────┐
│                    TOTVS PROTHEUS ERP                       │
│  ┌──────────────────────────────────────────────────────┐  │
│  │              REST API Endpoints                      │  │
│  │                                                       │  │
│  │  • Clientes (SA1)                                    │  │
│  │  • Produtos (SB1)                                    │  │
│  │  • Pedidos de Venda (SC5/SC6)                       │  │
│  │  • Estoque (SB2)                                     │  │
│  │  • Condições de Pagamento (SE4)                     │  │
│  │  • TES (SF4)                                         │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

### Componentes do Sistema

#### 3.1 Módulos do Plugin

| Módulo | Responsabilidade |
|--------|------------------|
| **Auth Manager** | Gerenciamento de autenticação (Basic/OAuth2) |
| **Protheus Client** | Cliente HTTP para comunicação com API |
| **Order Sync** | Sincronização de pedidos |
| **Customer Sync** | Sincronização de clientes |
| **Catalog Sync** | Sincronização de produtos |
| **Webhook Handler** | Processamento de webhooks |
| **Retry Manager** | Gerenciamento de tentativas |
| **Logger** | Sistema de logs |
| **Mapping Engine** | Mapeamento de campos |

#### 3.2 Fluxo de Dados

**Pedidos (WooCommerce → Protheus):**
1. Cliente finaliza compra no WooCommerce
2. Plugin captura evento de novo pedido
3. Valida dados do pedido
4. Sincroniza/cria cliente no Protheus
5. Cria pedido de venda no Protheus
6. Registra ID do Protheus no WooCommerce
7. Atualiza status do pedido

**Estoque (Protheus → WooCommerce):**
1. Cron job executa sincronização agendada
2. Plugin consulta estoque no Protheus
3. Atualiza quantidades no WooCommerce
4. Oculta produtos sem estoque
5. Registra operação em log

---

## 4. Requisitos Técnicos

### 4.1 Requisitos do Servidor Protheus

| Item | Especificação |
|------|---------------|
| **Versão Protheus** | 12.1.27 ou superior |
| **REST API** | Habilitada e configurada |
| **Protocolo** | HTTPS (obrigatório) |
| **Porta** | 443 (padrão) ou customizada |
| **Certificado SSL** | Válido e confiável |
| **Timeout** | Mínimo 30 segundos |
| **Rate Limit** | Mínimo 100 req/min |

### 4.2 Autenticação Suportada

#### Opção 1: Basic Authentication
- Usuário e senha do Protheus
- Transmissão via header Authorization
- Encoding Base64

#### Opção 2: OAuth 2.0
- Client ID e Client Secret
- Token endpoint configurável
- Refresh token automático
- Expiração de token gerenciada

### 4.3 Formato de Dados

- **Content-Type:** application/json
- **Charset:** UTF-8
- **Date Format:** ISO 8601 (YYYY-MM-DD)
- **Decimal Separator:** . (ponto)
- **Currency:** BRL (Real Brasileiro)


---

## 5. Endpoints Necessários

### 5.1 Gestão de Clientes

#### POST /api/v1/customers
**Descrição:** Criar novo cliente no Protheus

**Request Body:**
```json
{
  "codigo": "CLI001",
  "loja": "01",
  "tipo": "F",
  "nome": "João da Silva",
  "nome_fantasia": "João Silva",
  "cpf_cnpj": "12345678901",
  "inscricao_estadual": "",
  "email": "joao@email.com",
  "telefone": "11987654321",
  "endereco": {
    "logradouro": "Rua Exemplo",
    "numero": "123",
    "complemento": "Apto 45",
    "bairro": "Centro",
    "cidade": "São Paulo",
    "estado": "SP",
    "cep": "01234567",
    "pais": "Brasil"
  },
  "endereco_entrega": {
    "logradouro": "Rua Entrega",
    "numero": "456",
    "complemento": "",
    "bairro": "Jardim",
    "cidade": "São Paulo",
    "estado": "SP",
    "cep": "01234999"
  }
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "codigo": "CLI001",
    "loja": "01",
    "recno": 12345
  },
  "message": "Cliente criado com sucesso"
}
```

#### PUT /api/v1/customers/{codigo}/{loja}
**Descrição:** Atualizar cliente existente

**Request Body:** (mesmo formato do POST)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "codigo": "CLI001",
    "loja": "01",
    "recno": 12345
  },
  "message": "Cliente atualizado com sucesso"
}
```

#### GET /api/v1/customers/{codigo}/{loja}
**Descrição:** Consultar cliente por código

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "codigo": "CLI001",
    "loja": "01",
    "nome": "João da Silva",
    "cpf_cnpj": "12345678901",
    "email": "joao@email.com"
  }
}
```

---

### 5.2 Gestão de Produtos

#### GET /api/v1/products
**Descrição:** Listar produtos com paginação

**Query Parameters:**
- `page` (int): Número da página (padrão: 1)
- `per_page` (int): Itens por página (padrão: 50, máx: 500)
- `grupo` (string): Filtrar por grupo de produtos
- `ativo` (boolean): Filtrar apenas ativos

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "codigo": "PROD001",
      "descricao": "Produto Exemplo",
      "tipo": "PA",
      "unidade_medida": "UN",
      "grupo": "01",
      "preco_venda": 99.90,
      "peso_liquido": 1.5,
      "peso_bruto": 2.0,
      "ncm": "12345678",
      "ativo": true,
      "observacoes": "Produto de teste"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 50,
    "total_items": 150,
    "total_pages": 3
  }
}
```

#### GET /api/v1/products/{codigo}
**Descrição:** Consultar produto específico

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "codigo": "PROD001",
    "descricao": "Produto Exemplo",
    "descricao_completa": "Descrição detalhada do produto",
    "tipo": "PA",
    "unidade_medida": "UN",
    "grupo": "01",
    "preco_venda": 99.90,
    "peso_liquido": 1.5,
    "peso_bruto": 2.0,
    "ncm": "12345678",
    "ativo": true,
    "imagens": [
      "https://cdn.exemplo.com/produtos/PROD001.jpg"
    ]
  }
}
```

---

### 5.3 Gestão de Estoque

#### GET /api/v1/stock
**Descrição:** Consultar estoque de produtos

**Query Parameters:**
- `produtos` (array): Lista de códigos de produtos
- `armazem` (string): Código do armazém (padrão: "01")

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "codigo_produto": "PROD001",
      "armazem": "01",
      "quantidade": 150,
      "quantidade_reservada": 10,
      "quantidade_disponivel": 140,
      "data_atualizacao": "2024-02-27T10:30:00Z"
    }
  ]
}
```

#### GET /api/v1/stock/{codigo_produto}
**Descrição:** Consultar estoque de um produto específico

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "codigo_produto": "PROD001",
    "armazem": "01",
    "quantidade": 150,
    "quantidade_reservada": 10,
    "quantidade_disponivel": 140
  }
}
```

---

### 5.4 Gestão de Pedidos

#### POST /api/v1/orders
**Descrição:** Criar pedido de venda

**Request Body:**
```json
{
  "cliente": {
    "codigo": "CLI001",
    "loja": "01"
  },
  "tipo_pedido": "N",
  "condicao_pagamento": "001",
  "transportadora": "",
  "tipo_frete": "C",
  "valor_frete": 15.00,
  "observacoes": "Pedido da loja virtual #12345",
  "itens": [
    {
      "produto": "PROD001",
      "quantidade": 2,
      "preco_unitario": 99.90,
      "valor_total": 199.80,
      "tes": "501",
      "armazem": "01"
    }
  ],
  "totais": {
    "subtotal": 199.80,
    "desconto": 0.00,
    "frete": 15.00,
    "total": 214.80
  },
  "dados_adicionais": {
    "pedido_ecommerce": "12345",
    "forma_pagamento": "credit_card",
    "ip_cliente": "192.168.1.1"
  }
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "numero_pedido": "000123",
    "serie": "1",
    "data_emissao": "2024-02-27",
    "valor_total": 214.80,
    "status": "pending"
  },
  "message": "Pedido criado com sucesso"
}
```

#### GET /api/v1/orders/{numero_pedido}
**Descrição:** Consultar status do pedido

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "numero_pedido": "000123",
    "serie": "1",
    "status": "approved",
    "data_emissao": "2024-02-27",
    "data_aprovacao": "2024-02-27T14:30:00Z",
    "numero_nota_fiscal": "000456",
    "serie_nota_fiscal": "1",
    "chave_nfe": "12345678901234567890123456789012345678901234",
    "valor_total": 214.80
  }
}
```

#### PUT /api/v1/orders/{numero_pedido}/cancel
**Descrição:** Cancelar pedido

**Request Body:**
```json
{
  "motivo": "Cancelamento solicitado pelo cliente"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Pedido cancelado com sucesso"
}
```


---

### 5.5 Webhooks (Protheus → WooCommerce)

#### Endpoint do WooCommerce
**URL:** `https://seusite.com.br/wp-json/absloja-protheus/v1/webhook`

#### Autenticação
**Header:** `X-Protheus-Token: {token_configurado}`

#### Webhook: Atualização de Status de Pedido

**POST /wp-json/absloja-protheus/v1/webhook/order-status**

**Request Body:**
```json
{
  "event": "order.status.updated",
  "timestamp": "2024-02-27T15:30:00Z",
  "data": {
    "numero_pedido": "000123",
    "serie": "1",
    "status": "invoiced",
    "numero_nota_fiscal": "000456",
    "serie_nota_fiscal": "1",
    "chave_nfe": "12345678901234567890123456789012345678901234",
    "data_emissao_nf": "2024-02-27",
    "url_danfe": "https://protheus.exemplo.com/danfe/000456.pdf"
  }
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Webhook processado com sucesso"
}
```

#### Webhook: Atualização de Estoque

**POST /wp-json/absloja-protheus/v1/webhook/stock-update**

**Request Body:**
```json
{
  "event": "stock.updated",
  "timestamp": "2024-02-27T15:30:00Z",
  "data": {
    "produtos": [
      {
        "codigo": "PROD001",
        "armazem": "01",
        "quantidade_disponivel": 140
      },
      {
        "codigo": "PROD002",
        "armazem": "01",
        "quantidade_disponivel": 0
      }
    ]
  }
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Estoque atualizado com sucesso",
  "updated": 2
}
```

---

## 6. Especificações de API

### 6.1 Códigos de Status HTTP

| Código | Significado | Uso |
|--------|-------------|-----|
| 200 | OK | Requisição bem-sucedida |
| 201 | Created | Recurso criado com sucesso |
| 400 | Bad Request | Dados inválidos na requisição |
| 401 | Unauthorized | Falha na autenticação |
| 403 | Forbidden | Sem permissão para o recurso |
| 404 | Not Found | Recurso não encontrado |
| 422 | Unprocessable Entity | Validação de dados falhou |
| 429 | Too Many Requests | Limite de requisições excedido |
| 500 | Internal Server Error | Erro no servidor |
| 503 | Service Unavailable | Serviço temporariamente indisponível |

### 6.2 Formato de Erro Padrão

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Dados inválidos na requisição",
    "details": [
      {
        "field": "cpf_cnpj",
        "message": "CPF inválido"
      }
    ]
  }
}
```

### 6.3 Códigos de Erro Customizados

| Código | Descrição |
|--------|-----------|
| `VALIDATION_ERROR` | Erro de validação de dados |
| `CUSTOMER_NOT_FOUND` | Cliente não encontrado |
| `PRODUCT_NOT_FOUND` | Produto não encontrado |
| `ORDER_NOT_FOUND` | Pedido não encontrado |
| `DUPLICATE_ENTRY` | Registro duplicado |
| `INSUFFICIENT_STOCK` | Estoque insuficiente |
| `INVALID_TES` | TES inválido |
| `INVALID_PAYMENT_CONDITION` | Condição de pagamento inválida |

### 6.4 Headers Obrigatórios

**Request:**
```
Content-Type: application/json
Accept: application/json
Authorization: Basic {base64_credentials}
User-Agent: ABS-Protheus-Connector/1.0.0
```

**Response:**
```
Content-Type: application/json; charset=utf-8
X-Request-ID: {uuid}
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1234567890
```

---

## 7. Fluxos de Integração

### 7.1 Fluxo de Criação de Pedido

```
┌─────────────┐
│  Cliente    │
│  Finaliza   │
│   Compra    │
└──────┬──────┘
       │
       ▼
┌─────────────────────────────────────────┐
│  WooCommerce cria pedido                │
│  Status: "processing"                   │
└──────┬──────────────────────────────────┘
       │
       ▼
┌─────────────────────────────────────────┐
│  Plugin captura evento                  │
│  "woocommerce_order_status_processing"  │
└──────┬──────────────────────────────────┘
       │
       ▼
┌─────────────────────────────────────────┐
│  Valida dados do pedido                 │
│  - Cliente existe?                      │
│  - Produtos válidos?                    │
│  - Estoque disponível?                  │
└──────┬──────────────────────────────────┘
       │
       ▼
┌─────────────────────────────────────────┐
│  Sincroniza/Cria Cliente                │
│  POST /api/v1/customers                 │
└──────┬──────────────────────────────────┘
       │
       ▼
┌─────────────────────────────────────────┐
│  Cria Pedido no Protheus                │
│  POST /api/v1/orders                    │
└──────┬──────────────────────────────────┘
       │
       ├─── Sucesso ───┐
       │               │
       │               ▼
       │        ┌─────────────────────────┐
       │        │  Salva ID do Protheus   │
       │        │  no pedido WooCommerce  │
       │        └─────────────────────────┘
       │
       └─── Falha ────┐
                      │
                      ▼
               ┌─────────────────────────┐
               │  Adiciona à fila de     │
               │  retry com backoff      │
               └─────────────────────────┘
```

### 7.2 Fluxo de Sincronização de Estoque

```
┌─────────────────────────────────────────┐
│  WP-Cron executa job agendado           │
│  Frequência: A cada 15 minutos          │
└──────┬──────────────────────────────────┘
       │
       ▼
┌─────────────────────────────────────────┐
│  Plugin consulta produtos WooCommerce   │
│  Obtém lista de SKUs ativos             │
└──────┬──────────────────────────────────┘
       │
       ▼
┌─────────────────────────────────────────┐
│  Consulta estoque no Protheus           │
│  GET /api/v1/stock?produtos=[...]       │
└──────┬──────────────────────────────────┘
       │
       ▼
┌─────────────────────────────────────────┐
│  Processa resposta em lote              │
│  Batch size: 50 produtos                │
└──────┬──────────────────────────────────┘
       │
       ▼
┌─────────────────────────────────────────┐
│  Para cada produto:                     │
│  - Atualiza quantidade                  │
│  - Define status (in_stock/out_stock)   │
│  - Oculta se quantidade = 0             │
│  - Restaura se voltou ao estoque        │
└──────┬──────────────────────────────────┘
       │
       ▼
┌─────────────────────────────────────────┐
│  Registra operação em log               │
│  - Total processado                     │
│  - Atualizados                          │
│  - Ocultados                            │
│  - Erros                                │
└─────────────────────────────────────────┘
```

### 7.3 Fluxo de Webhook de Status

```
┌─────────────────────────────────────────┐
│  Protheus: Pedido faturado              │
│  Nota fiscal emitida                    │
└──────┬──────────────────────────────────┘
       │
       ▼
┌─────────────────────────────────────────┐
│  Protheus envia webhook                 │
│  POST /wp-json/.../webhook/order-status │
└──────┬──────────────────────────────────┘
       │
       ▼
┌─────────────────────────────────────────┐
│  Plugin valida token de autenticação    │
│  Header: X-Protheus-Token               │
└──────┬──────────────────────────────────┘
       │
       ├─── Token válido ───┐
       │                    │
       │                    ▼
       │             ┌─────────────────────┐
       │             │  Localiza pedido    │
       │             │  pelo número        │
       │             └──────┬──────────────┘
       │                    │
       │                    ▼
       │             ┌─────────────────────┐
       │             │  Atualiza status    │
       │             │  Adiciona notas     │
       │             │  Salva dados NF-e   │
       │             └──────┬──────────────┘
       │                    │
       │                    ▼
       │             ┌─────────────────────┐
       │             │  Envia email ao     │
       │             │  cliente (opcional) │
       │             └─────────────────────┘
       │
       └─── Token inválido ───┐
                               │
                               ▼
                        ┌─────────────────┐
                        │  Retorna 401    │
                        │  Unauthorized   │
                        └─────────────────┘
```


---

## 8. Segurança e Autenticação

### 8.1 Métodos de Autenticação

#### Basic Authentication
```
Authorization: Basic base64(username:password)
```

**Exemplo:**
```
Username: api_user
Password: S3cur3P@ssw0rd
Base64: YXBpX3VzZXI6UzNjdXIzUEBzc3cwcmQ=
Header: Authorization: Basic YXBpX3VzZXI6UzNjdXIzUEBzc3cwcmQ=
```

#### OAuth 2.0
```
1. POST /oauth2/token
   Body: {
     "grant_type": "client_credentials",
     "client_id": "your_client_id",
     "client_secret": "your_client_secret"
   }

2. Response: {
     "access_token": "eyJhbGciOiJIUzI1NiIs...",
     "token_type": "Bearer",
     "expires_in": 3600
   }

3. Uso: Authorization: Bearer eyJhbGciOiJIUzI1NiIs...
```

### 8.2 Segurança de Webhooks

**Token de Autenticação:**
- Token único gerado pelo plugin
- Enviado no header `X-Protheus-Token`
- Validado em cada requisição
- Pode ser regenerado a qualquer momento

**Validação de Assinatura (Opcional):**
```
X-Protheus-Signature: sha256=hash_do_payload
```

Cálculo:
```php
$signature = hash_hmac('sha256', $payload, $secret_key);
```

### 8.3 Boas Práticas de Segurança

✅ **HTTPS Obrigatório**
- Todas as comunicações devem usar HTTPS
- Certificado SSL válido e confiável

✅ **Validação de Dados**
- Validar todos os inputs
- Sanitizar dados antes de processar
- Usar prepared statements no banco

✅ **Rate Limiting**
- Limite de requisições por minuto
- Proteção contra ataques DDoS
- Backoff exponencial em caso de erro

✅ **Logs de Auditoria**
- Registrar todas as operações
- Incluir IP de origem
- Manter logs por período configurável

✅ **Credenciais Seguras**
- Senhas criptografadas no banco
- Não expor credenciais em logs
- Rotação periódica de tokens

---

## 9. Tratamento de Erros

### 9.1 Sistema de Retry

**Configurações:**
- Máximo de tentativas: 5 (configurável)
- Intervalo entre tentativas: 1 hora (configurável)
- Backoff exponencial: 1h, 2h, 4h, 8h, 16h

**Tipos de Erro que Acionam Retry:**
- Timeout de conexão
- Erro 500 (Internal Server Error)
- Erro 503 (Service Unavailable)
- Erro de rede

**Tipos de Erro que NÃO Acionam Retry:**
- Erro 400 (Bad Request)
- Erro 401 (Unauthorized)
- Erro 404 (Not Found)
- Erro 422 (Validation Error)

### 9.2 Notificações de Erro

**Email Automático:**
- Enviado ao administrador
- Após 3 tentativas falhadas
- Inclui detalhes do erro
- Link para visualizar logs

**Dashboard do WordPress:**
- Widget com status de sincronização
- Alertas de operações pendentes
- Contador de erros recentes

### 9.3 Recuperação de Erros

**Retry Manual:**
- Interface administrativa
- Botão "Tentar Novamente"
- Visualização de detalhes do erro

**Limpeza de Fila:**
- Remoção de itens antigos
- Após limite de tentativas
- Notificação ao administrador

---

## 10. Cronograma de Implementação

### Fase 1: Preparação (Semana 1-2)

**Responsável: Equipe Protheus**

- [ ] Análise de requisitos técnicos
- [ ] Definição de endpoints
- [ ] Configuração de ambiente de desenvolvimento
- [ ] Criação de usuário de API
- [ ] Configuração de permissões

**Entregáveis:**
- Documento de especificação técnica
- Credenciais de acesso (dev)
- URL base da API
- Exemplos de payloads

### Fase 2: Desenvolvimento (Semana 3-4)

**Responsável: Equipe Protheus**

- [ ] Implementação de endpoints de clientes
- [ ] Implementação de endpoints de produtos
- [ ] Implementação de endpoints de pedidos
- [ ] Implementação de endpoints de estoque
- [ ] Configuração de webhooks

**Responsável: Fale Agência Digital**

- [ ] Testes de integração
- [ ] Validação de payloads
- [ ] Ajustes de mapeamento
- [ ] Documentação de testes

### Fase 3: Testes (Semana 5-6)

**Responsável: Ambas as Equipes**

- [ ] Testes unitários
- [ ] Testes de integração
- [ ] Testes de carga
- [ ] Testes de segurança
- [ ] Validação de fluxos completos

**Cenários de Teste:**
1. Criação de cliente novo
2. Atualização de cliente existente
3. Criação de pedido simples
4. Criação de pedido com múltiplos itens
5. Cancelamento de pedido
6. Sincronização de estoque
7. Webhook de status
8. Tratamento de erros
9. Sistema de retry

### Fase 4: Homologação (Semana 7)

**Responsável: Cliente + Fale Agência**

- [ ] Testes em ambiente de homologação
- [ ] Validação de processos de negócio
- [ ] Ajustes finais
- [ ] Documentação de usuário
- [ ] Treinamento da equipe

### Fase 5: Go-Live (Semana 8)

**Responsável: Todas as Equipes**

- [ ] Migração para produção
- [ ] Configuração de credenciais produção
- [ ] Monitoramento intensivo
- [ ] Suporte dedicado
- [ ] Documentação final

---

## 11. Informações de Contato

### Fale Agência Digital
**Desenvolvimento e Suporte do Plugin**

- **Website:** https://faleagencia.digital
- **Email:** contato@faleagencia.digital
- **Telefone:** (11) XXXX-XXXX
- **Horário:** Segunda a Sexta, 9h às 18h

### Equipe Técnica

- **Desenvolvedor Responsável:** [Nome]
- **Email Técnico:** dev@faleagencia.digital
- **Suporte Emergencial:** suporte@faleagencia.digital

---

## 12. Anexos

### Anexo A: Glossário de Termos

| Termo | Descrição |
|-------|-----------|
| **API** | Application Programming Interface |
| **REST** | Representational State Transfer |
| **JSON** | JavaScript Object Notation |
| **HTTPS** | HTTP Secure |
| **OAuth** | Open Authorization |
| **TES** | Tipo de Entrada/Saída |
| **NF-e** | Nota Fiscal Eletrônica |
| **DANFE** | Documento Auxiliar da Nota Fiscal Eletrônica |
| **SKU** | Stock Keeping Unit |
| **ERP** | Enterprise Resource Planning |

### Anexo B: Tabelas de Mapeamento

#### Formas de Pagamento
| WooCommerce | Protheus | Descrição |
|-------------|----------|-----------|
| bacs | 001 | Transferência Bancária |
| cheque | 002 | Cheque |
| cod | 003 | Dinheiro |
| credit_card | 004 | Cartão de Crédito |
| pix | 005 | PIX |

#### Status de Pedidos
| Protheus | WooCommerce | Descrição |
|----------|-------------|-----------|
| pending | pending | Pendente |
| approved | processing | Aprovado |
| invoiced | completed | Faturado |
| shipped | completed | Enviado |
| cancelled | cancelled | Cancelado |
| rejected | failed | Rejeitado |

#### TES por Estado
| Estado | TES | Descrição |
|--------|-----|-----------|
| SP | 501 | Venda dentro do estado |
| Outros | 502 | Venda fora do estado |

### Anexo C: Exemplos de Código

#### Exemplo de Requisição cURL

```bash
# Criar Cliente
curl -X POST https://protheus.exemplo.com/api/v1/customers \
  -H "Content-Type: application/json" \
  -H "Authorization: Basic YXBpX3VzZXI6UzNjdXIzUEBzc3cwcmQ=" \
  -d '{
    "codigo": "CLI001",
    "loja": "01",
    "nome": "João da Silva",
    "cpf_cnpj": "12345678901",
    "email": "joao@email.com"
  }'

# Consultar Estoque
curl -X GET "https://protheus.exemplo.com/api/v1/stock?produtos[]=PROD001&produtos[]=PROD002" \
  -H "Authorization: Basic YXBpX3VzZXI6UzNjdXIzUEBzc3cwcmQ="

# Criar Pedido
curl -X POST https://protheus.exemplo.com/api/v1/orders \
  -H "Content-Type: application/json" \
  -H "Authorization: Basic YXBpX3VzZXI6UzNjdXIzUEBzc3cwcmQ=" \
  -d @pedido.json
```

---

## Conclusão

Este documento apresenta todos os requisitos técnicos necessários para a implementação da integração entre WooCommerce e TOTVS Protheus.

A integração proporcionará:
- ✅ Automação completa de processos
- ✅ Redução de erros operacionais
- ✅ Sincronização em tempo real
- ✅ Escalabilidade para crescimento
- ✅ Rastreabilidade total de operações

Para dúvidas ou esclarecimentos adicionais, entre em contato com a equipe da Fale Agência Digital.

---

**Documento gerado em:** Fevereiro 2024  
**Versão:** 1.0.0  
**Desenvolvido por:** Fale Agência Digital  
**Cliente:** ABS Global

---

© 2024 Fale Agência Digital. Todos os direitos reservados.
