# Correção de Compatibilidade com WooCommerce 10.5

## Problema

O WooCommerce 10.5.2 estava marcando o plugin como incompatível porque:
1. O header "WC tested up to" estava desatualizado (8.0)
2. Faltava a declaração de compatibilidade HPOS (High-Performance Order Storage)
3. Faltava o header "Requires Plugins" para dependência explícita

## O que é HPOS?

HPOS (High-Performance Order Storage) é o novo sistema de armazenamento de pedidos do WooCommerce que:
- Usa tabelas customizadas ao invés de posts do WordPress
- Melhora significativamente a performance
- É obrigatório declarar compatibilidade desde WooCommerce 8.0+

## Correções Aplicadas

### 1. Atualizado header "WC tested up to"
```php
WC tested up to: 10.5
```

### 2. Adicionado header "Requires Plugins"
```php
Requires Plugins: woocommerce
```

### 3. Declarada compatibilidade HPOS
```php
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 
            'custom_order_tables', 
            __FILE__, 
            true 
        );
    }
} );
```

## Por que o plugin é compatível com HPOS?

O plugin usa as APIs corretas do WooCommerce:
- ✅ `wc_get_order()` - API oficial para obter pedidos
- ✅ `$order->get_meta()` - API oficial para metadados
- ✅ `$order->update_meta_data()` - API oficial para atualizar metadados
- ✅ `wc_get_orders()` - API oficial para buscar pedidos
- ✅ Não acessa diretamente tabelas `wp_posts` ou `wp_postmeta`

## Como Verificar

Após desativar e reativar o plugin:

1. Vá em **Plugins** no WordPress Admin
2. O aviso de incompatibilidade deve desaparecer
3. O plugin deve aparecer na lista normal de plugins ativos
4. Vá em **WooCommerce → Status → Ferramentas**
5. Procure por "High-Performance Order Storage"
6. O plugin deve estar listado como compatível

## Versões Suportadas

- ✅ WordPress 6.0+
- ✅ WooCommerce 7.0 - 10.5+
- ✅ PHP 7.4+
- ✅ HPOS (Custom Order Tables)

## Arquivo Modificado

- `wp-content/plugins/absloja-protheus-connector/absloja-protheus-connector.php`

## Próximos Passos

1. **Desative o plugin** em WordPress Admin → Plugins
2. **Reative o plugin**
3. O aviso de incompatibilidade deve desaparecer
4. Acesse **WooCommerce → Protheus Connector** para configurar
