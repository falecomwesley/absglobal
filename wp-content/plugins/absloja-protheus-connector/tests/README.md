# ABS Loja Protheus Connector - Testing Guide

## Overview

This directory contains all tests for the ABS Loja Protheus Connector plugin. The test suite is organized into three main categories: unit tests, property-based tests, and integration tests.

## Test Structure

```
tests/
├── bootstrap.php              # PHPUnit bootstrap file
├── unit/                      # Unit tests
│   ├── api/                   # API client tests
│   ├── database/              # Database schema tests
│   └── modules/               # Module-specific tests
├── property/                  # Property-based tests
├── integration/               # Integration tests
└── fixtures/                  # Test data and mocks
```

## Prerequisites

### Required Software
- PHP 7.4 or higher
- Composer
- WordPress 6.0+
- WooCommerce 7.0+
- PHPUnit 9.5+

### Installation

1. Install dependencies:
```bash
cd wp-content/plugins/absloja-protheus-connector
composer install
```

2. Set up WordPress test environment (optional for integration tests):
```bash
# Install WordPress test suite
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

## Running Tests

### Run All Tests
```bash
composer test
# or
vendor/bin/phpunit
```

### Run Specific Test Suites

**Unit Tests Only:**
```bash
vendor/bin/phpunit --testsuite unit
```

**Property-Based Tests Only:**
```bash
vendor/bin/phpunit --testsuite property
```

**Integration Tests Only:**
```bash
vendor/bin/phpunit --testsuite integration
```

### Run Specific Test File
```bash
vendor/bin/phpunit tests/unit/modules/AuthManagerTest.php
```

### Run with Coverage Report
```bash
vendor/bin/phpunit --coverage-html coverage/
```

Then open `coverage/index.html` in your browser.

## Test Types

### 1. Unit Tests (`tests/unit/`)

Unit tests verify individual components in isolation using mocks and stubs.

**Example:**
```php
<?php
namespace ABSLoja\ProtheusConnector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ABSLoja\ProtheusConnector\Modules\Auth_Manager;

class AuthManagerTest extends TestCase {
    public function test_basic_auth_headers() {
        $config = [
            'auth_type' => 'basic',
            'username' => 'testuser',
            'password' => 'testpass'
        ];
        
        $auth = new Auth_Manager($config);
        $headers = $auth->get_auth_headers();
        
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertStringStartsWith('Basic ', $headers['Authorization']);
    }
}
```

### 2. Property-Based Tests (`tests/property/`)

Property-based tests verify that certain properties hold true across many randomly generated inputs.

**Example:**
```php
<?php
namespace ABSLoja\ProtheusConnector\Tests\Property;

use PHPUnit\Framework\TestCase;
use ABSLoja\ProtheusConnector\Modules\Customer_Sync;

class CustomerSyncPropertyTest extends TestCase {
    /**
     * @test Feature: absloja-protheus-connector, Property 11: CPF/CNPJ Extraction and Cleaning
     * Validates: Requirements 2.4
     */
    public function property_cpf_cnpj_cleaning() {
        // Test that CPF/CNPJ cleaning always removes formatting
        $test_cases = [
            '123.456.789-00' => '12345678900',
            '12.345.678/0001-00' => '12345678000100',
            '123-456-789.00' => '12345678900',
        ];
        
        foreach ($test_cases as $input => $expected) {
            $result = Customer_Sync::clean_document($input);
            $this->assertEquals($expected, $result);
            $this->assertMatchesRegularExpression('/^\d+$/', $result);
        }
    }
}
```

### 3. Integration Tests (`tests/integration/`)

Integration tests verify that multiple components work together correctly.

**Example:**
```php
<?php
namespace ABSLoja\ProtheusConnector\Tests\Integration;

use PHPUnit\Framework\TestCase;

class OrderFlowIntegrationTest extends TestCase {
    public function test_complete_order_sync_flow() {
        // Create WooCommerce order
        $order = wc_create_order();
        $order->set_status('processing');
        
        // Verify customer is created/verified
        // Verify order is sent to Protheus
        // Verify metadata is stored
        // Verify logs are created
    }
}
```

## Test Configuration

### PHPUnit Configuration (`phpunit.xml`)

The `phpunit.xml` file configures:
- Test suites (unit, property, integration)
- Bootstrap file
- Code coverage settings
- PHP constants

### Bootstrap File (`bootstrap.php`)

The bootstrap file:
- Loads Composer autoloader
- Loads WordPress test functions (if available)
- Sets up test environment
- Defines test constants

## Writing Tests

### Best Practices

1. **One assertion per test** (when possible)
2. **Use descriptive test names** that explain what is being tested
3. **Follow AAA pattern**: Arrange, Act, Assert
4. **Mock external dependencies** (API calls, database)
5. **Test edge cases** and error conditions
6. **Keep tests independent** - no test should depend on another

### Naming Conventions

**Unit Tests:**
- File: `{ClassName}Test.php`
- Method: `test_{method_name}_{scenario}()`

**Property Tests:**
- File: `{Feature}PropertyTest.php`
- Method: `property_{property_name}()`
- Use annotation: `@test Feature: absloja-protheus-connector, Property N: [Title]`

**Integration Tests:**
- File: `{Feature}IntegrationTest.php`
- Method: `test_{feature}_{scenario}()`

## Mocking

### WordPress Functions

Use Brain\Monkey for mocking WordPress functions:

```php
use Brain\Monkey\Functions;

Functions\when('get_option')->justReturn('test_value');
Functions\expect('update_option')->once()->with('key', 'value');
```

### WooCommerce Objects

Use Mockery for mocking WooCommerce objects:

```php
use Mockery;

$order = Mockery::mock('WC_Order');
$order->shouldReceive('get_id')->andReturn(123);
$order->shouldReceive('get_status')->andReturn('processing');
```

## Fixtures

Test fixtures are located in `tests/fixtures/` and provide:
- Sample order data
- Sample customer data
- Sample product data
- Mock API responses

**Example usage:**
```php
$order_data = require __DIR__ . '/../fixtures/orders.php';
$mock_response = require __DIR__ . '/../fixtures/api-responses.php';
```

## Continuous Integration

### GitHub Actions (Example)

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.0'
        
    - name: Install dependencies
      run: composer install
      
    - name: Run tests
      run: vendor/bin/phpunit
```

## Troubleshooting

### Common Issues

**Issue: Class not found**
- Solution: Run `composer dump-autoload`

**Issue: WordPress functions not available**
- Solution: Install WordPress test suite or use Brain\Monkey for mocking

**Issue: Database errors**
- Solution: Ensure test database is configured correctly

**Issue: Tests are slow**
- Solution: Use `@group` annotations to organize tests and run specific groups

## Coverage Goals

- **Overall Coverage**: 80%+
- **Critical Modules**: 90%+
  - Auth_Manager
  - Order_Sync
  - Customer_Sync
  - Catalog_Sync
  - Webhook_Handler

## Additional Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Plugin Testing](https://make.wordpress.org/cli/handbook/plugin-unit-tests/)
- [Mockery Documentation](http://docs.mockery.io/)
- [Brain\Monkey Documentation](https://brain-wp.github.io/BrainMonkey/)

## Support

For questions or issues with tests, please:
1. Check this documentation
2. Review existing test examples
3. Consult the main plugin documentation
4. Contact the development team
