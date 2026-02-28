# ABS Loja Protheus Connector - Development Guide

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Code Structure](#code-structure)
3. [Development Setup](#development-setup)
4. [Coding Standards](#coding-standards)
5. [Adding New Features](#adding-new-features)
6. [Extending Functionality](#extending-functionality)
7. [Testing](#testing)
8. [Debugging](#debugging)
9. [Contributing](#contributing)

## Architecture Overview

The plugin follows a modular architecture with clear separation of concerns:

```
┌─────────────────────────────────────────────────────────┐
│                    WordPress Core                        │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│                  Plugin Main Class                       │
│              (Singleton Pattern)                         │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│                    Loader (Hooks)                        │
└─────────────────────────────────────────────────────────┘
                          ↓
┌──────────────┬──────────────┬──────────────┬────────────┐
│   Modules    │     API      │    Admin     │  Database  │
│              │              │              │            │
│ Auth_Manager │ Protheus_    │ Admin        │ Schema     │
│ Order_Sync   │ Client       │ Settings     │            │
│ Customer_    │ REST_        │ Log_Viewer   │            │
│ Sync         │ Controller   │              │            │
│ Catalog_Sync │              │              │            │
│ Webhook_     │              │              │            │
│ Handler      │              │              │            │
│ Logger       │              │              │            │
│ Retry_       │              │              │            │
│ Manager      │              │              │            │
│ Mapping_     │              │              │            │
│ Engine       │              │              │            │
└──────────────┴──────────────┴──────────────┴────────────┘
```

### Design Patterns

1. **Singleton**: Plugin main class ensures single instance
2. **Dependency Injection**: Modules receive dependencies via constructor
3. **Observer**: WordPress hooks and filters for event-driven architecture
4. **Strategy**: Mapping Engine for configurable field mappings
5. **Factory**: Object creation for orders, products, customers

## Code Structure

### Namespace Organization

All classes use PSR-4 autoloading with the base namespace:

```php
ABSLoja\ProtheusConnector
```

**Namespace Structure:**
```
ABSLoja\ProtheusConnector\
├── Admin\              # Administrative interface
├── API\                # HTTP client and REST endpoints
├── Database\           # Database schema
└── Modules\            # Core functionality modules
```

### File Organization

```
includes/
├── class-plugin.php              # Main plugin class
├── class-activator.php           # Activation logic
├── class-deactivator.php         # Deactivation logic
├── class-loader.php              # Hook manager
├── modules/
│   ├── class-auth-manager.php    # Authentication
│   ├── class-order-sync.php      # Order synchronization
│   ├── class-customer-sync.php   # Customer synchronization
│   ├── class-catalog-sync.php    # Catalog synchronization
│   ├── class-webhook-handler.php # Webhook processing
│   ├── class-logger.php          # Logging system
│   ├── class-retry-manager.php   # Retry management
│   ├── class-mapping-engine.php  # Field mappings
│   └── class-error-handler.php   # Error handling
├── api/
│   ├── class-protheus-client.php # HTTP client
│   └── class-rest-controller.php # REST API endpoints
├── admin/
│   ├── class-admin.php           # Admin interface
│   ├── class-settings.php        # Settings management
│   ├── class-log-viewer.php      # Log viewer
│   └── views/                    # View templates
└── database/
    └── class-schema.php          # Database schema
```

## Development Setup

### Prerequisites

- PHP 7.4+
- Composer
- Node.js and npm (for assets)
- WordPress 6.0+
- WooCommerce 7.0+
- MySQL 5.7+

### Installation

1. Clone the repository:
```bash
git clone https://github.com/absglobal/absloja-protheus-connector.git
cd absloja-protheus-connector
```

2. Install PHP dependencies:
```bash
composer install
```

3. Install JavaScript dependencies (if applicable):
```bash
npm install
```

4. Create a symlink to your WordPress plugins directory:
```bash
ln -s $(pwd) /path/to/wordpress/wp-content/plugins/absloja-protheus-connector
```

5. Activate the plugin in WordPress admin

### Development Tools

**Recommended IDE:** Visual Studio Code with extensions:
- PHP Intelephense
- WordPress Snippets
- PHP Debug (Xdebug)

**Recommended Browser Extensions:**
- Query Monitor (WordPress debugging)
- WooCommerce Debug Bar

## Coding Standards

### PHP Standards

Follow WordPress Coding Standards (WPCS):

```bash
# Install PHPCS
composer require --dev squizlabs/php_codesniffer

# Install WordPress standards
composer require --dev wp-coding-standards/wpcs

# Configure PHPCS
vendor/bin/phpcs --config-set installed_paths vendor/wp-coding-standards/wpcs

# Check code
vendor/bin/phpcs --standard=WordPress includes/
```

### Code Style

**Class Names:** PascalCase with underscores
```php
class Auth_Manager {}
```

**Method Names:** snake_case
```php
public function get_auth_headers() {}
```

**Variable Names:** snake_case
```php
$auth_manager = new Auth_Manager();
```

**Constants:** UPPERCASE with underscores
```php
define( 'ABSLOJA_PROTHEUS_VERSION', '1.0.0' );
```

### Documentation

All classes and methods must have PHPDoc comments:

```php
/**
 * Authenticate with Protheus API
 *
 * @param array $credentials Authentication credentials.
 * @return bool True if authentication successful.
 * @throws \Exception If authentication fails.
 */
public function authenticate( $credentials ) {
    // Implementation
}
```

## Adding New Features

### Adding a New Module

1. Create the module class in `includes/modules/`:

```php
<?php
namespace ABSLoja\ProtheusConnector\Modules;

class My_New_Module {
    /**
     * Constructor
     *
     * @param Logger $logger Logger instance.
     */
    public function __construct( $logger ) {
        $this->logger = $logger;
    }
    
    /**
     * Initialize module
     */
    public function init() {
        // Register hooks
    }
}
```

2. Register the module in `class-plugin.php`:

```php
private function load_modules() {
    // ... existing modules
    
    $this->my_new_module = new Modules\My_New_Module( $this->logger );
    $this->my_new_module->init();
}
```

### Adding a New Mapping Type

1. Add the mapping type to `Mapping_Engine`:

```php
public function get_my_mapping() {
    $mapping = get_option( 'absloja_protheus_my_mapping', $this->get_default_my_mapping() );
    return $mapping;
}

private function get_default_my_mapping() {
    return array(
        'key1' => 'value1',
        'key2' => 'value2',
    );
}
```

2. Add settings registration in `Settings`:

```php
register_setting( 
    'absloja_protheus_mappings', 
    'absloja_protheus_my_mapping', 
    array( $this, 'sanitize_array' ) 
);
```

3. Add UI in `views/tab-mappings.php`

### Adding a New Webhook Endpoint

1. Add the endpoint in `Webhook_Handler`:

```php
public function register_routes() {
    // ... existing routes
    
    register_rest_route(
        'absloja-protheus/v1',
        '/webhook/my-endpoint',
        array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_my_endpoint' ),
            'permission_callback' => array( $this, 'authenticate_webhook' ),
        )
    );
}

public function handle_my_endpoint( $request ) {
    $data = $request->get_json_params();
    
    // Process webhook
    
    return new \WP_REST_Response(
        array( 'success' => true ),
        200
    );
}
```

2. Document the endpoint in `API-DOCUMENTATION.md`

## Extending Functionality

### Using WordPress Hooks

The plugin provides several hooks for customization:

**Filters:**

```php
// Modify order data before sending to Protheus
add_filter( 'absloja_protheus_order_data', function( $data, $order ) {
    $data['custom_field'] = 'custom_value';
    return $data;
}, 10, 2 );

// Modify product data from Protheus
add_filter( 'absloja_protheus_product_data', function( $data, $protheus_data ) {
    $data['custom_meta'] = $protheus_data['custom_field'];
    return $data;
}, 10, 2 );

// Modify customer data before sending to Protheus
add_filter( 'absloja_protheus_customer_data', function( $data, $order ) {
    $data['custom_field'] = 'custom_value';
    return $data;
}, 10, 2 );
```

**Actions:**

```php
// After order sync
add_action( 'absloja_protheus_order_synced', function( $order_id, $protheus_order_id ) {
    // Custom logic after order sync
}, 10, 2 );

// After product sync
add_action( 'absloja_protheus_product_synced', function( $product_id, $sku ) {
    // Custom logic after product sync
}, 10, 2 );

// After stock update
add_action( 'absloja_protheus_stock_updated', function( $product_id, $quantity ) {
    // Custom logic after stock update
}, 10, 2 );

// On sync error
add_action( 'absloja_protheus_sync_error', function( $error, $context ) {
    // Custom error handling
}, 10, 2 );
```

### Creating Custom Mappings

You can programmatically add custom mappings:

```php
add_filter( 'absloja_protheus_payment_mapping', function( $mapping ) {
    $mapping['custom_payment'] = '999';
    return $mapping;
} );

add_filter( 'absloja_protheus_tes_rules', function( $rules ) {
    $rules['RJ'] = '503';
    return $rules;
} );
```

### Overriding Default Behavior

Use WordPress filters to override default behavior:

```php
// Override TES determination
add_filter( 'absloja_protheus_determine_tes', function( $tes, $state, $order ) {
    if ( $order->get_total() > 1000 ) {
        return '504'; // Special TES for high-value orders
    }
    return $tes;
}, 10, 3 );

// Override customer code generation
add_filter( 'absloja_protheus_customer_code', function( $code, $customer_data ) {
    // Custom logic for customer code
    return $code;
}, 10, 2 );
```

## Testing

### Running Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test suite
vendor/bin/phpunit --testsuite unit

# Run specific test file
vendor/bin/phpunit tests/unit/modules/AuthManagerTest.php

# Run with coverage
vendor/bin/phpunit --coverage-html coverage/
```

### Writing Tests

**Unit Test Example:**

```php
<?php
namespace ABSLoja\ProtheusConnector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ABSLoja\ProtheusConnector\Modules\Auth_Manager;

class AuthManagerTest extends TestCase {
    public function test_basic_auth_headers() {
        $config = array(
            'auth_type' => 'basic',
            'username'  => 'testuser',
            'password'  => 'testpass',
        );
        
        $auth    = new Auth_Manager( $config );
        $headers = $auth->get_auth_headers();
        
        $this->assertArrayHasKey( 'Authorization', $headers );
        $this->assertStringStartsWith( 'Basic ', $headers['Authorization'] );
    }
}
```

### Mocking WordPress Functions

Use Brain\Monkey for mocking:

```php
use Brain\Monkey\Functions;

Functions\when( 'get_option' )->justReturn( 'test_value' );
Functions\expect( 'update_option' )->once()->with( 'key', 'value' );
```

## Debugging

### Enable WordPress Debug Mode

In `wp-config.php`:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

### Plugin Logging

The plugin logs all operations. View logs in:

**WooCommerce > Protheus Connector > Logs**

### Xdebug Setup

1. Install Xdebug PHP extension
2. Configure `php.ini`:

```ini
[xdebug]
xdebug.mode=debug
xdebug.start_with_request=yes
xdebug.client_port=9003
```

3. Configure VS Code `launch.json`:

```json
{
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Listen for Xdebug",
            "type": "php",
            "request": "launch",
            "port": 9003,
            "pathMappings": {
                "/path/to/wordpress": "${workspaceFolder}"
            }
        }
    ]
}
```

### Query Monitor

Install Query Monitor plugin for WordPress to debug:
- Database queries
- PHP errors
- Hooks and filters
- HTTP requests
- Performance

## Contributing

### Git Workflow

1. Create a feature branch:
```bash
git checkout -b feature/my-new-feature
```

2. Make changes and commit:
```bash
git add .
git commit -m "Add new feature"
```

3. Push to remote:
```bash
git push origin feature/my-new-feature
```

4. Create a pull request

### Commit Message Format

```
<type>(<scope>): <subject>

<body>

<footer>
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation
- `style`: Code style changes
- `refactor`: Code refactoring
- `test`: Adding tests
- `chore`: Maintenance tasks

**Example:**
```
feat(order-sync): add support for custom order fields

- Add filter for custom order data
- Update documentation
- Add unit tests

Closes #123
```

### Code Review Checklist

- [ ] Code follows WordPress coding standards
- [ ] All functions have PHPDoc comments
- [ ] Unit tests added for new functionality
- [ ] Documentation updated
- [ ] No PHP errors or warnings
- [ ] Tested in WordPress 6.0+
- [ ] Tested with WooCommerce 7.0+
- [ ] Security best practices followed

## Performance Optimization

### Database Queries

- Use `$wpdb->prepare()` for all queries
- Add indexes to custom tables
- Use transients for caching
- Batch process large datasets

### Caching

```php
// Cache mappings
$mapping = wp_cache_get( 'payment_mapping', 'absloja_protheus' );
if ( false === $mapping ) {
    $mapping = $this->get_payment_mapping();
    wp_cache_set( 'payment_mapping', $mapping, 'absloja_protheus', 3600 );
}
```

### Async Processing

Use WP-Cron for background tasks:

```php
// Schedule async task
wp_schedule_single_event( time() + 60, 'absloja_protheus_process_batch', array( $batch_id ) );

// Register callback
add_action( 'absloja_protheus_process_batch', 'process_batch_callback' );
```

## Security Best Practices

1. **Input Validation**: Validate all user inputs
2. **Output Escaping**: Escape all outputs
3. **Nonce Verification**: Use nonces for forms
4. **Capability Checks**: Check user capabilities
5. **SQL Injection**: Use prepared statements
6. **XSS Prevention**: Sanitize and escape data
7. **CSRF Protection**: Use WordPress nonces

## Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WooCommerce Documentation](https://woocommerce.com/documentation/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Protheus REST API Documentation](https://tdn.totvs.com/display/public/PROT/REST)

## Support

For development questions or issues:

**ABS Global**
- Website: https://absglobal.com.br
- Email: dev@absglobal.com.br
- GitHub: https://github.com/absglobal
