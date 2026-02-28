# Correção do Erro de Ativação do Plugin

## Problema Identificado

O plugin estava gerando um erro fatal durante a ativação:
```
Fatal error: Uncaught TypeError: ABSLoja\ProtheusConnector\API\Protheus_Client::__construct(): 
Argument #2 ($api_url) must be of type string, ABSLoja\ProtheusConnector\Modules\Logger given
```

## Causas Raiz

1. **Interface administrativa não inicializada**: O método `define_admin_hooks()` estava vazio, não registrando o menu e funcionalidades do plugin.

2. **Parâmetros incorretos no construtor**: O `Protheus_Client` espera 3 parâmetros:
   - `Auth_Manager $auth_manager`
   - `string $api_url`
   - `int $timeout` (opcional)
   
   Mas estava sendo chamado com apenas 2 parâmetros: `Auth_Manager` e `Logger`.

## Correções Aplicadas

### 1. Criado método auxiliar `create_protheus_client()`

Adicionado método privado para criar instâncias do Protheus_Client corretamente:

```php
private function create_protheus_client() {
    $auth_config = $this->get_auth_config();
    $auth_manager = new Modules\Auth_Manager( $auth_config );
    $api_url = ! empty( $auth_config['api_url'] ) ? $auth_config['api_url'] : 'http://localhost';
    
    return new API\Protheus_Client( $auth_manager, $api_url );
}
```

### 2. Corrigido método `define_admin_hooks()`

Agora inicializa corretamente a interface administrativa com todos os parâmetros corretos.

### 3. Corrigidos todos os métodos que instanciam `Protheus_Client`

Atualizados os seguintes métodos para usar `create_protheus_client()`:
- ✅ `define_admin_hooks()`
- ✅ `sync_catalog_callback()`
- ✅ `sync_stock_callback()`
- ✅ `handle_order_status_processing()`
- ✅ `handle_order_status_cancelled()`
- ✅ `handle_order_status_refunded()`
- ✅ `prevent_status_change_on_sync_failure()`

## Arquivos Modificados

- `wp-content/plugins/absloja-protheus-connector/includes/class-plugin.php`

## Arquivos Criados

- `wp-content/plugins/absloja-protheus-connector/test-activation.php` - Script de diagnóstico
- `wp-content/plugins/absloja-protheus-connector/ACTIVATION-FIX.md` - Este documento

## Próximos Passos

### 1. Desative e Reative o Plugin

1. Vá em **WordPress Admin → Plugins**
2. Desative "ABS Loja Protheus Connector"
3. Ative novamente

### 2. Execute o Script de Teste (RECOMENDADO)

Antes de ativar, execute o script de diagnóstico:

**URL:** `http://localhost:8888/absglobal/wp-content/plugins/absloja-protheus-connector/test-activation.php`

Este script verifica:
- ✅ Versões do WordPress e PHP
- ✅ WooCommerce instalado
- ✅ Arquivos do plugin
- ✅ Autoloader funcionando
- ✅ Tabelas do banco de dados
- ✅ Opções do plugin
- ✅ Agendamentos cron
- ✅ Instanciação do plugin

### 3. Acesse as Configurações

Após ativação bem-sucedida, acesse:

**WordPress Admin → WooCommerce → Protheus Connector**

Você verá 5 abas:
- **Connection (Conexão)** - Configure credenciais da API
- **Mappings (Mapeamentos)** - Configure mapeamentos de campos
- **Schedule (Agendamento)** - Configure frequência de sincronização
- **Logs** - Visualize logs de operações
- **Advanced (Avançado)** - Configurações avançadas

### 4. Configure a Conexão

Na aba **Connection**:
1. Selecione o tipo de autenticação (Basic, OAuth2, ou Token)
2. Preencha a URL da API do Protheus
3. Preencha as credenciais
4. Clique em "Test Connection" para verificar

### 5. Execute os Testes Manuais

Siga o guia completo em:
`wp-content/plugins/absloja-protheus-connector/MANUAL-TESTING-GUIDE.md`

## Verificação Rápida

Se o plugin foi ativado corretamente, você deve ver:

1. ✅ Menu "Protheus Connector" sob WooCommerce
2. ✅ Widget no Dashboard mostrando status
3. ✅ Sem mensagens de erro no topo da página
4. ✅ Sem avisos de incompatibilidade no WooCommerce

## Solução de Problemas

### Se ainda houver erro de ativação:

1. Execute o script de teste para diagnóstico detalhado
2. Verifique os logs de erro do PHP (`wp-content/debug.log`)
3. Verifique se todas as dependências estão instaladas
4. Certifique-se de que o WooCommerce está ativo

### Se o menu não aparecer:

1. Limpe o cache do navegador
2. Faça logout e login novamente
3. Verifique se você tem permissão `manage_woocommerce`

### Se houver erro 500:

1. Ative o modo debug do WordPress (`WP_DEBUG = true` em `wp-config.php`)
2. Verifique `wp-content/debug.log` para detalhes do erro
3. Execute o script de teste para identificar o problema

## Suporte

Se o problema persistir após estas correções, forneça:
- Mensagem de erro completa
- Resultado do script de teste
- Conteúdo do `wp-content/debug.log`
- Versões do WordPress, WooCommerce e PHP
