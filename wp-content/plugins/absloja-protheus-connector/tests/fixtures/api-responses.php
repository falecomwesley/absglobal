<?php
/**
 * API response test fixtures
 * 
 * @package ABSLoja\ProtheusConnector\Tests
 */

return [
    'customer_exists_success' => [
        'status' => 200,
        'body' => json_encode([
            'success' => true,
            'data' => [
                'A1_COD' => '000001',
                'A1_LOJA' => '01',
                'A1_NOME' => 'João Silva',
                'A1_CGC' => '12345678900',
            ],
        ]),
    ],
    
    'customer_not_found' => [
        'status' => 404,
        'body' => json_encode([
            'success' => false,
            'message' => 'Cliente não encontrado',
        ]),
    ],
    
    'customer_create_success' => [
        'status' => 201,
        'body' => json_encode([
            'success' => true,
            'data' => [
                'A1_COD' => '000001',
                'A1_LOJA' => '01',
            ],
            'message' => 'Cliente criado com sucesso',
        ]),
    ],
    
    'customer_create_error' => [
        'status' => 400,
        'body' => json_encode([
            'success' => false,
            'message' => 'CPF inválido',
            'errors' => ['A1_CGC' => 'CPF já cadastrado'],
        ]),
    ],
    
    'order_create_success' => [
        'status' => 201,
        'body' => json_encode([
            'success' => true,
            'data' => [
                'C5_NUM' => '000123',
                'C5_FILIAL' => '01',
            ],
            'message' => 'Pedido criado com sucesso',
        ]),
    ],
    
    'order_create_tes_error' => [
        'status' => 400,
        'body' => json_encode([
            'success' => false,
            'message' => 'TES não encontrado',
            'errors' => ['C6_TES' => 'TES 501 não cadastrado'],
        ]),
    ],
    
    'order_create_stock_error' => [
        'status' => 400,
        'body' => json_encode([
            'success' => false,
            'message' => 'Estoque insuficiente',
            'errors' => ['C6_QTDVEN' => 'Produto PROD001 sem estoque'],
        ]),
    ],
    
    'products_list_success' => [
        'status' => 200,
        'body' => json_encode([
            'success' => true,
            'data' => [
                [
                    'B1_COD' => 'PROD001',
                    'B1_DESC' => 'Produto 1',
                    'B1_PRV1' => 25.00,
                    'B1_PESO' => 0.5,
                    'B1_MSBLQL' => '2',
                    'B1_GRUPO' => '01',
                ],
                [
                    'B1_COD' => 'PROD002',
                    'B1_DESC' => 'Produto 2',
                    'B1_PRV1' => 50.00,
                    'B1_PESO' => 1.0,
                    'B1_MSBLQL' => '2',
                    'B1_GRUPO' => '01',
                ],
            ],
            'pagination' => [
                'page' => 1,
                'per_page' => 50,
                'total' => 2,
            ],
        ]),
    ],
    
    'stock_list_success' => [
        'status' => 200,
        'body' => json_encode([
            'success' => true,
            'data' => [
                [
                    'B2_COD' => 'PROD001',
                    'B2_LOCAL' => '01',
                    'B2_QATU' => 100,
                ],
                [
                    'B2_COD' => 'PROD002',
                    'B2_LOCAL' => '01',
                    'B2_QATU' => 0,
                ],
            ],
        ]),
    ],
    
    'auth_success' => [
        'status' => 200,
        'body' => json_encode([
            'success' => true,
            'message' => 'Autenticação bem-sucedida',
        ]),
    ],
    
    'auth_failure' => [
        'status' => 401,
        'body' => json_encode([
            'success' => false,
            'message' => 'Credenciais inválidas',
        ]),
    ],
    
    'server_error' => [
        'status' => 500,
        'body' => json_encode([
            'success' => false,
            'message' => 'Erro interno do servidor',
        ]),
    ],
    
    'timeout_error' => [
        'status' => 0,
        'body' => '',
        'error' => 'Connection timeout',
    ],
    
    'oauth_token_success' => [
        'status' => 200,
        'body' => json_encode([
            'access_token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]),
    ],
];
