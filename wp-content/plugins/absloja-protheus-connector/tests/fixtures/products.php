<?php
/**
 * Product test fixtures
 * 
 * @package ABSLoja\ProtheusConnector\Tests
 */

return [
    'woocommerce_product' => [
        'id' => 10,
        'sku' => 'PROD001',
        'name' => 'Produto Teste 1',
        'regular_price' => 25.00,
        'description' => 'Descrição do produto teste',
        'short_description' => 'Produto para testes',
        'weight' => 0.5,
        'status' => 'publish',
        'manage_stock' => true,
        'stock_quantity' => 100,
    ],
    
    'protheus_sb1' => [
        'B1_FILIAL' => '01',
        'B1_COD' => 'PROD001',
        'B1_DESC' => 'Produto Teste 1',
        'B1_TIPO' => 'PA',
        'B1_UM' => 'UN',
        'B1_LOCPAD' => '01',
        'B1_GRUPO' => '01',
        'B1_PRV1' => 25.00,
        'B1_PESO' => 0.5,
        'B1_MSBLQL' => '2', // Não bloqueado
        'B1_DESCMAR' => 'Produto para testes',
    ],
    
    'protheus_sb1_blocked' => [
        'B1_FILIAL' => '01',
        'B1_COD' => 'PROD002',
        'B1_DESC' => 'Produto Bloqueado',
        'B1_TIPO' => 'PA',
        'B1_UM' => 'UN',
        'B1_LOCPAD' => '01',
        'B1_GRUPO' => '02',
        'B1_PRV1' => 50.00,
        'B1_PESO' => 1.0,
        'B1_MSBLQL' => '1', // Bloqueado
        'B1_DESCMAR' => 'Produto bloqueado para venda',
    ],
    
    'protheus_sb2_stock' => [
        'B2_FILIAL' => '01',
        'B2_COD' => 'PROD001',
        'B2_LOCAL' => '01',
        'B2_QATU' => 100,
        'B2_RESERVA' => 0,
        'B2_QEMP' => 0,
    ],
    
    'protheus_sb2_zero_stock' => [
        'B2_FILIAL' => '01',
        'B2_COD' => 'PROD002',
        'B2_LOCAL' => '01',
        'B2_QATU' => 0,
        'B2_RESERVA' => 0,
        'B2_QEMP' => 0,
    ],
    
    'product_batch' => [
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
        [
            'B1_COD' => 'PROD003',
            'B1_DESC' => 'Produto 3',
            'B1_PRV1' => 75.00,
            'B1_PESO' => 1.5,
            'B1_MSBLQL' => '1',
            'B1_GRUPO' => '02',
        ],
    ],
];
