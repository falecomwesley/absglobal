<?php
/**
 * Customer test fixtures
 * 
 * @package ABSLoja\ProtheusConnector\Tests
 */

return [
    'cpf_customer' => [
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
    
    'cnpj_customer' => [
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
    
    'protheus_sa1_cpf' => [
        'A1_FILIAL' => '01',
        'A1_COD' => '000001',
        'A1_LOJA' => '01',
        'A1_NOME' => 'João Silva',
        'A1_NREDUZ' => 'João Silva',
        'A1_CGC' => '12345678900',
        'A1_TIPO' => 'F',
        'A1_END' => 'Rua das Flores, 123',
        'A1_BAIRRO' => 'Centro',
        'A1_MUN' => 'São Paulo',
        'A1_EST' => 'SP',
        'A1_CEP' => '01234567',
        'A1_DDD' => '11',
        'A1_TEL' => '987654321',
        'A1_EMAIL' => 'joao.silva@example.com',
    ],
    
    'protheus_sa1_cnpj' => [
        'A1_FILIAL' => '01',
        'A1_COD' => '000002',
        'A1_LOJA' => '01',
        'A1_NOME' => 'Empresa Teste LTDA',
        'A1_NREDUZ' => 'Empresa Teste',
        'A1_CGC' => '12345678000100',
        'A1_TIPO' => 'J',
        'A1_END' => 'Av. Paulista, 1000',
        'A1_BAIRRO' => 'Bela Vista',
        'A1_MUN' => 'São Paulo',
        'A1_EST' => 'SP',
        'A1_CEP' => '01310100',
        'A1_DDD' => '11',
        'A1_TEL' => '34567890',
        'A1_EMAIL' => 'contato@empresa.com.br',
    ],
];
