# 🎯 Mudanças REAIS no Plugin WordPress

**Documento Corrigido - Março 2026**  
**O que REALMENTE precisa mudar**

---

## ⚠️ CORREÇÕES IMPORTANTES

### 1. URL da API
**CORREÇÃO:** A URL da API **NÃO precisa mudar** no código!

A URL `api_url` é configurável pelo usuário e aponta para o servidor Protheus do cliente. Se o cliente usar APIs TOTVS, ele configurará a URL como `https://api.totvs.com.br` nas configurações do plugin.

**Nada a fazer no código!** ✅

### 2. Documentação das APIs TOTVS
**PROBLEMA:** Os arquivos JSON em api.totvs.com.br não são acessíveis publicamente via web scraping.

**SOLUÇÃO:** A equipe precisa:
1. Ter conta/licença TOTVS
2. Acessar portal do desenvolvedor TOTVS
3. Obter documentação oficial (Swagger/OpenAPI)
4. Ou contatar suporte TOTVS para documentação

---

## 🔍 O QUE REALMENTE PRECISA MUDAR

### Mudança 1: Adicionar Suporte para Autenticação TOTVS

**Arquivo:** `includes/modules/class-auth-manager.php`

**Motivo:** APIs TOTVS podem usar autenticação diferente (Bearer Token, API Key, Tenant ID)

**Código a adicionar:**

```php
/**
 * Get authentication headers
 * Supports both custom Protheus and TOTVS APIs
 */
public function get_auth_headers(): array {
    $auth_type = get_option('absloja_protheus_auth_type', 'basic');
    
    // Se usar APIs TOTVS
    if ($auth_type === 'totvs') {
        $api_key = $this->decrypt_credential(
            get_option('absloja_protheus_totvs_api_key', '')
        );
        $tenant_id = get_option('absloja_protheus_totvs_tenant_id', '');
        
        return array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
            'tenantId' => $tenant_id,
            'Accept' => 'application/json'
        );
    }
    
    // Autenticação existente (Basic/OAuth2)
    if ($auth_type === 'basic') {
        return array(
            'Authorization' => 'Basic ' . base64_encode(
                $this->username . ':' . $this->decrypt_credential($this->password)
            ),
            'Content-Type' => 'application/json',
        );
    }
    
    // OAuth2 existente...
    return $this->get_oauth2_headers();
}
```

**Tempo:** 1 hora

---

### Mudança 2: Adicionar Campos de Configuração para TOTVS

**Arquivo:** `includes/admin/class-settings.php`

**Adicionar na aba "Connection":**

```php
// Adicionar opção de tipo de API
'api_type' => array(
    'title' => __('Tipo de API', 'absloja-protheus-connector'),
    'type' => 'select',
    'options' => array(
        'custom' => __('Protheus Customizado', 'absloja-protheus-connector'),
        'totvs' => __('APIs TOTVS E-Commerce', 'absloja-protheus-connector'),
    ),
    'default' => 'custom',
    'description' => __('Selecione o tipo de API que será utilizada', 'absloja-protheus-connector'),
),

// Campos específicos TOTVS (mostrar apenas se api_type = 'totvs')
'totvs_tenant_id' => array(
    'title' => __('Tenant ID (TOTVS)', 'absloja-protheus-connector'),
    'type' => 'text',
    'description' => __('ID do tenant fornecido pela TOTVS', 'absloja-protheus-connector'),
    'class' => 'totvs-field', // Para mostrar/ocultar com JavaScript
),

'totvs_api_key' => array(
    'title' => __('API Key (TOTVS)', 'absloja-protheus-connector'),
    'type' => 'password',
    'description' => __('Chave de API fornecida pela TOTVS', 'absloja-protheus-connector'),
    'class' => 'totvs-field',
),
```

**JavaScript para mostrar/ocultar campos:**

```javascript
// assets/js/admin.js
jQuery(document).ready(function($) {
    $('#absloja_protheus_api_type').on('change', function() {
        if ($(this).val() === 'totvs') {
            $('.totvs-field').closest('tr').show();
            $('.custom-field').closest('tr').hide();
        } else {
            $('.totvs-field').closest('tr').hide();
            $('.custom-field').closest('tr').show();
        }
    }).trigger('change');
});
```

**Tempo:** 2 horas

---

### Mudança 3: Adaptar Mapeamento de Dados (SE NECESSÁRIO)

**IMPORTANTE:** Só fazer isso DEPOIS de ter a documentação TOTVS!

**Arquivos afetados:**
- `includes/modules/class-order-sync.php`
- `includes/modules/class-customer-sync.php`
- `includes/modules/class-catalog-sync.php`

**Estratégia:**

1. **Criar métodos de mapeamento condicionais:**

```php
/**
 * Prepare order data for API
 * Adapts format based on API type
 */
private function prepare_order_data_for_api(array $order_data): array {
    $api_type = get_option('absloja_protheus_api_type', 'custom');
    
    if ($api_type === 'totvs') {
        return $this->map_to_totvs_format($order_data);
    }
    
    // Formato customizado existente
    return $order_data;
}

/**
 * Map to TOTVS E-Commerce API format
 * TODO: Implementar após obter documentação TOTVS
 */
private function map_to_totvs_format(array $order_data): array {
    // Implementar baseado na documentação TOTVS
    return array(
        // Campos conforme documentação TOTVS
    );
}
```

2. **Adaptar endpoints condicionalmente:**

```php
/**
 * Get API endpoint based on API type
 */
private function get_order_endpoint(): string {
    $api_type = get_option('absloja_protheus_api_type', 'custom');
    
    if ($api_type === 'totvs') {
        return 'ecommerce/v1/orders'; // Endpoint TOTVS
    }
    
    return 'api/v1/orders'; // Endpoint customizado
}
```

**Tempo:** 4-6 horas (APÓS ter documentação)

---

### Mudança 4: Adaptar Tratamento de Respostas (SE NECESSÁRIO)

**Motivo:** APIs TOTVS podem retornar estrutura diferente

```php
/**
 * Extract order ID from API response
 */
private function extract_order_id_from_response(array $response): ?string {
    $api_type = get_option('absloja_protheus_api_type', 'custom');
    
    if ($api_type === 'totvs') {
        // Estrutura TOTVS (verificar documentação)
        return $response['data']['orderId'] ?? 
               $response['data']['id'] ?? 
               null;
    }
    
    // Estrutura customizada existente
    return $response['order_number'] ?? null;
}
```

**Tempo:** 2 horas (APÓS ter documentação)

---

## 📋 CHECKLIST REALISTA

### Fase 1: Preparação (ANTES de começar)

- [ ] **CRÍTICO:** Obter documentação oficial TOTVS
  - [ ] Acessar portal do desenvolvedor TOTVS
  - [ ] Baixar especificação OpenAPI/Swagger
  - [ ] Ou solicitar ao suporte TOTVS
- [ ] Fazer backup do plugin
- [ ] Criar branch Git: `feature/totvs-api-support`

### Fase 2: Mudanças Básicas (SEM documentação)

**Pode fazer agora:**

- [ ] Adicionar campo "Tipo de API" nas configurações (2h)
- [ ] Adicionar campos TOTVS (Tenant ID, API Key) (1h)
- [ ] Adicionar JavaScript para mostrar/ocultar campos (1h)
- [ ] Adicionar suporte para autenticação TOTVS no Auth_Manager (1h)
- [ ] Adicionar método `get_order_endpoint()` condicional (30min)
- [ ] Adicionar método `get_customer_endpoint()` condicional (30min)
- [ ] Adicionar método `get_product_endpoint()` condicional (30min)

**Subtotal:** ~6 horas

### Fase 3: Mapeamento de Dados (COM documentação)

**Só fazer DEPOIS de ter documentação:**

- [ ] Implementar `map_to_totvs_format()` para pedidos (2h)
- [ ] Implementar `map_to_totvs_format()` para clientes (1h)
- [ ] Implementar `map_totvs_to_woo()` para produtos (2h)
- [ ] Implementar `extract_order_id_from_response()` (1h)
- [ ] Implementar `extract_customer_id_from_response()` (30min)

**Subtotal:** ~6.5 horas

### Fase 4: Testes

- [ ] Testar com APIs customizadas (não quebrar funcionalidade existente) (2h)
- [ ] Testar com APIs TOTVS (se tiver acesso) (3h)
- [ ] Atualizar testes unitários (2h)

**Subtotal:** ~7 horas

### Fase 5: Documentação

- [ ] Atualizar README.md (30min)
- [ ] Documentar novos campos de configuração (30min)
- [ ] Criar guia de migração para APIs TOTVS (1h)

**Subtotal:** ~2 horas

---

## ⏱️ ESTIMATIVA REALISTA

| Fase | Tempo | Pode Fazer Agora? |
|------|-------|-------------------|
| Preparação | 1h | ✅ Sim |
| Mudanças Básicas | 6h | ✅ Sim |
| Mapeamento de Dados | 6.5h | ❌ Precisa documentação |
| Testes | 7h | ⚠️ Parcial |
| Documentação | 2h | ✅ Sim |
| **TOTAL** | **22.5h (~3 dias)** | |

---

## 🎯 ESTRATÉGIA RECOMENDADA

### Abordagem em 2 Fases:

#### FASE 1: Preparar Infraestrutura (AGORA - 1 dia)

**O que fazer:**
1. Adicionar campos de configuração
2. Adicionar suporte para autenticação TOTVS
3. Adicionar métodos condicionais de endpoint
4. Criar estrutura para mapeamento (métodos vazios)

**Resultado:**
- Plugin pronto para receber mapeamentos TOTVS
- Não quebra funcionalidade existente
- Fácil de testar

#### FASE 2: Implementar Mapeamentos (DEPOIS - 1-2 dias)

**Quando:** Após obter documentação TOTVS

**O que fazer:**
1. Implementar mapeamentos de dados
2. Implementar parsing de respostas
3. Testar com APIs TOTVS
4. Ajustar conforme necessário

---

## 💡 EXEMPLO DE CÓDIGO "PREPARADO"

Veja como deixar o código preparado SEM ter a documentação:

```php
<?php
/**
 * Order Sync - Prepared for TOTVS APIs
 */

class Order_Sync {
    
    /**
     * Sync order to Protheus/TOTVS
     */
    public function sync_order(int $order_id): array {
        // ... código existente ...
        
        // Preparar dados
        $order_data = $this->prepare_order_data($order);
        
        // Adaptar formato se necessário
        $api_data = $this->adapt_order_data_for_api($order_data);
        
        // Obter endpoint correto
        $endpoint = $this->get_order_endpoint();
        
        // Enviar
        $response = $this->client->post($endpoint, $api_data);
        
        // Processar resposta
        if ($response['success']) {
            $order_id = $this->extract_order_id($response);
            // ... resto do código ...
        }
    }
    
    /**
     * Adapt order data based on API type
     */
    private function adapt_order_data_for_api(array $order_data): array {
        $api_type = get_option('absloja_protheus_api_type', 'custom');
        
        if ($api_type === 'totvs') {
            return $this->map_to_totvs_format($order_data);
        }
        
        return $order_data; // Formato existente
    }
    
    /**
     * Get order endpoint based on API type
     */
    private function get_order_endpoint(): string {
        $api_type = get_option('absloja_protheus_api_type', 'custom');
        
        if ($api_type === 'totvs') {
            return 'ecommerce/v1/orders';
        }
        
        return 'api/v1/orders';
    }
    
    /**
     * Map to TOTVS format
     * TODO: Implement after getting TOTVS documentation
     */
    private function map_to_totvs_format(array $order_data): array {
        // Por enquanto, retorna formato existente
        // Implementar quando tiver documentação TOTVS
        
        error_log('TOTVS mapping not implemented yet');
        
        return $order_data;
    }
    
    /**
     * Extract order ID from response
     */
    private function extract_order_id(array $response): ?string {
        $api_type = get_option('absloja_protheus_api_type', 'custom');
        
        if ($api_type === 'totvs') {
            // TODO: Ajustar após ver estrutura real da resposta TOTVS
            return $response['data']['orderId'] ?? 
                   $response['data']['id'] ?? 
                   $response['order_number'] ?? 
                   null;
        }
        
        return $response['order_number'] ?? null;
    }
}
```

---

## ✅ CONCLUSÃO

### O que REALMENTE precisa fazer:

1. **AGORA (1 dia):**
   - Adicionar campos de configuração
   - Adicionar suporte para autenticação TOTVS
   - Preparar estrutura condicional

2. **DEPOIS (1-2 dias):**
   - Obter documentação TOTVS
   - Implementar mapeamentos
   - Testar e ajustar

### Total: 2-3 dias de trabalho

### Não precisa:
- ❌ Mudar URL padrão no código
- ❌ Reescrever classes existentes
- ❌ Mudar arquitetura do plugin
- ❌ Quebrar funcionalidade existente

---

**PRÓXIMO PASSO:** Começar Fase 1 (preparar infraestrutura) enquanto aguarda documentação TOTVS.

**FIM DO DOCUMENTO CORRIGIDO**
