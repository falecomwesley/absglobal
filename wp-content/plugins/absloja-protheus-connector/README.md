# ABS Loja Protheus Connector

Plugin WordPress para integração WooCommerce com TOTVS Protheus ERP.

**Versão:** 1.0.0  
**Desenvolvido por:** Fale Agência Digital  
**Cliente:** ABS Global

---

## Descrição

O ABS Loja Protheus Connector é um plugin WordPress que integra automaticamente sua loja WooCommerce com o sistema ERP TOTVS Protheus através de REST API, automatizando a sincronização de pedidos, clientes, produtos e estoque.

## Funcionalidades

- ✅ Sincronização automática de pedidos para o Protheus
- ✅ Sincronização de clientes (criação/atualização)
- ✅ Sincronização de catálogo de produtos
- ✅ Sincronização de estoque em tempo real
- ✅ Recebimento de webhooks do Protheus
- ✅ Sistema de retry automático para falhas
- ✅ Logs completos de todas as operações
- ✅ Interface administrativa intuitiva
- ✅ Totalmente traduzido para português brasileiro

## Requisitos

- WordPress 6.0 ou superior
- WooCommerce 7.0 ou superior
- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- HTTPS (obrigatório para produção)

## Instalação

1. Faça upload da pasta `absloja-protheus-connector` para `/wp-content/plugins/`
2. Ative o plugin através do menu 'Plugins' no WordPress
3. Acesse **WooCommerce → Protheus Connector** para configurar

## Configuração Rápida

1. **Conexão**: Configure URL da API e credenciais
2. **Contrato TOTVS**: Use `TOTVS E-commerce v1` (padrão recomendado)
3. **Overrides (opcional)**: Ajuste endpoints/params no tab **Advanced** apenas se sua instância tiver rotas diferentes
2. **Mapeamentos**: Configure mapeamentos de campos
3. **Agendamento**: Defina frequência de sincronização
4. **Teste**: Use o botão "Testar Conexão"

## Contrato de API Padrão

O plugin está alinhado ao contrato TOTVS E-commerce com estes endpoints padrão:

- `api/ecommerce/v1/retailSalesOrders`
- `api/ecommerce/v1/orderChangeStatus`
- `api/ecommerce/v1/retailItem`
- `api/ecommerce/v1/retailItem/{sku}`
- `api/ecommerce/v1/stock-product`

Os endpoints podem ser sobrescritos no admin quando necessário.

## Documentação

### Documentação Principal
📄 **[docs/README.md](docs/README.md)** - Documentação completa consolidada

### Documentação para Equipe Protheus
📄 **[docs/DOCUMENTACAO-INTEGRACAO-PROTHEUS.md](docs/DOCUMENTACAO-INTEGRACAO-PROTHEUS.md)** - Especificações técnicas completas  
📄 **[docs/RESUMO-EXECUTIVO-INTEGRACAO.md](docs/RESUMO-EXECUTIVO-INTEGRACAO.md)** - Resumo executivo  
📄 **[docs/COMO-CONVERTER-PARA-WORD.md](docs/COMO-CONVERTER-PARA-WORD.md)** - Guia de conversão para Word

### Documentação Técnica
📄 **[docs/API-DOCUMENTATION.md](docs/API-DOCUMENTATION.md)** - Documentação da API  
📄 **[docs/DEVELOPMENT-GUIDE.md](docs/DEVELOPMENT-GUIDE.md)** - Guia de desenvolvimento  
📄 **[docs/WEBHOOK-ENDPOINTS.md](docs/WEBHOOK-ENDPOINTS.md)** - Endpoints de webhook  
📄 **[docs/SECURITY-REVIEW.md](docs/SECURITY-REVIEW.md)** - Revisão de segurança  
📄 **[docs/PERFORMANCE-OPTIMIZATION.md](docs/PERFORMANCE-OPTIMIZATION.md)** - Otimização de performance

### Guias de Usuário
📄 **[MANUAL-TESTING-GUIDE.md](MANUAL-TESTING-GUIDE.md)** - Guia de testes manuais  
📄 **[TRADUCAO-COMPLETA.md](TRADUCAO-COMPLETA.md)** - Documentação de tradução

## Estrutura do Plugin

```
absloja-protheus-connector/
├── includes/              # Classes principais
│   ├── admin/            # Interface administrativa
│   ├── api/              # Cliente HTTP
│   ├── modules/          # Módulos de sincronização
│   └── database/         # Schema do banco
├── assets/               # CSS e JavaScript
├── languages/            # Arquivos de tradução
├── tests/                # Testes
└── docs/                 # Documentação
```

## Suporte

### Fale Agência Digital

- 🌐 Website: https://faleagencia.digital
- 📧 Email: contato@faleagencia.digital
- 📧 Suporte Técnico: dev@faleagencia.digital
- ⏰ Horário: Segunda a Sexta, 9h às 18h

## Changelog

### 1.0.0 (Fevereiro 2024)
- Lançamento inicial
- Sincronização completa de pedidos, clientes, produtos e estoque
- Sistema de webhooks
- Sistema de retry automático
- Interface administrativa completa
- Tradução pt_BR completa
- Compatibilidade com WooCommerce 10.5+
- Suporte a HPOS (High-Performance Order Storage)

## Licença

GPL v3 ou posterior

## Créditos

Desenvolvido por **Fale Agência Digital** para **ABS Global**.

---

© 2024 Fale Agência Digital. Todos os direitos reservados.
