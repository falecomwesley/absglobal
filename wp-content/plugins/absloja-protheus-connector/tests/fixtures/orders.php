<?php
/**
 * Order test fixtures
 * 
 * @package ABSLoja\ProtheusConnector\Tests
 */

return [
    'simple_order' => [
        'id' => 123,
        'status' => 'processing',
        'total' => 150.00,
        'shipping_total' => 10.00,
        'discount_total' => 5.00,
        'date_created' => '2024-01-15 10:30:00',
        'payment_method' => 'credit_card',
        'billing' => [
            'first_name' => 'João',
            'last_name' => 'Silva',
            'email' => 'joao.silva@example.com',
            'phone' => '(11) 98765-4321',
            'address_1' => 'Rua das Flores, 123',
            'address_2' => 'Apto 45',
            'city' => 'São Paulo',
            'state' => 'SP',
            'postcode' => '01234-567',
            'country' => 'BR',
            'cpf' => '123.456.789-00',
            'neighborhood' => 'Centro',
        ],
        'items' => [
            [
                'product_id' => 10,
                'sku' => 'PROD001',
                'name' => 'Produto Teste 1',
                'quantity' => 2,
                'subtotal' => 50.00,
                'total' => 50.00,
            ],
            [
                'product_id' => 11,
                'sku' => 'PROD002',
                'name' => 'Produto Teste 2',
                'quantity' => 1,
                'subtotal' => 95.00,
                'total' => 90.00, // Com desconto
            ],
        ],
    ],
    
    'cnpj_order' => [
        'id' => 124,
        'status' => 'processing',
        'total' => 500.00,
        'shipping_total' => 20.00,
        'discount_total' => 0.00,
        'date_created' => '2024-01-16 14:20:00',
        'payment_method' => 'bacs',
        'billing' => [
            'first_name' => 'Empresa',
            'last_name' => 'Teste LTDA',
            'email' => 'contato@empresa.com.br',
            'phone' => '(11) 3456-7890',
            'address_1' => 'Av. Paulista, 1000',
            'address_2' => 'Sala 501',
            'city' => 'São Paulo',
            'state' => 'SP',
            'postcode' => '01310-100',
            'country' => 'BR',
            'cnpj' => '12.345.678/0001-00',
            'neighborhood' => 'Bela Vista',
        ],
        'items' => [
            [
                'product_id' => 12,
                'sku' => 'PROD003',
                'name' => 'Produto Empresarial',
                'quantity' => 10,
                'subtotal' => 500.00,
                'total' => 500.00,
            ],
        ],
    ],
    
    'interstate_order' => [
        'id' => 125,
        'status' => 'processing',
        'total' => 200.00,
        'shipping_total' => 15.00,
        'discount_total' => 0.00,
        'date_created' => '2024-01-17 09:15:00',
        'payment_method' => 'pix',
        'billing' => [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria.santos@example.com',
            'phone' => '(21) 99876-5432',
            'address_1' => 'Rua do Comércio, 456',
            'address_2' => '',
            'city' => 'Rio de Janeiro',
            'state' => 'RJ',
            'postcode' => '20000-000',
            'country' => 'BR',
            'cpf' => '987.654.321-00',
            'neighborhood' => 'Centro',
        ],
        'items' => [
            [
                'product_id' => 13,
                'sku' => 'PROD004',
                'name' => 'Produto Interestadual',
                'quantity' => 1,
                'subtotal' => 185.00,
                'total' => 185.00,
            ],
        ],
    ],
];
