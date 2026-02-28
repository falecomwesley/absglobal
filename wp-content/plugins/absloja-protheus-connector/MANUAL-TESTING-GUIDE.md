# Guia de Testes Manuais - ABS Loja Protheus Connector

## Pré-requisitos

Antes de iniciar os testes, certifique-se de que:
- ✅ WordPress 6.0+ está instalado e funcionando
- ✅ WooCommerce 7.0+ está ativo
- ✅ PHP 7.4+ está configurado
- ✅ Você tem acesso ao admin do WordPress
- ✅ Você tem credenciais de API do Protheus (para testes reais)

## Teste 0: Script de Verificação de Ativação (RECOMENDADO)

### Objetivo
Verificar todos os requisitos antes de ativar o plugin.

### Passos
1. Acesse no navegador: `http://localhost:8888/absglobal/wp-content/plugins/absloja-protheus-connector/test-activation.php`
2. Revise todos os testes executados
3. Verifique se há erros (em vermelho)

### Resultado Esperado
- ✅ Todos os testes devem passar (verde)
- ⚠️ Avisos (laranja) são aceitáveis para itens que serão criados na ativação
- ❌ Erros (vermelho) devem ser corrigidos antes de ativar

### O que o script verifica
- Versão do WordPress e PHP
- WooCommerce ativo
- Arquivos do plugin
- Autoloader PSR-4
- Tabelas do banco de dados
- Opções do plugin
- Agendamentos cron
- Status de ativação
- Instanciação do plugin

---

## Teste 1: Ativação do Plugin

### Objetivo
Verificar se o plugin ativa corretamente e cria as estruturas necessárias.

### Passos
1. Acesse WordPress Admin → Plugins
2. Localize "ABS Loja Protheus Connector"
3. Clique em "Ativar"

### Resultado Esperado
- ✅ Plugin ativa sem erros
- ✅ Aparece menu "Protheus Connector" sob WooCommerce
- ✅ Tabelas do banco de dados são criadas:
  - `wp_absloja_logs`
  - `wp_absloja_retry_queue`

### Verificação Manual
Execute no MySQL/phpMyAdmin:
```sql
SHOW TABLES LIKE 'wp_absloja_%';
```

### Se houver erro de ativação
1. Execute o script de teste (Teste 0) para diagnosticar
2. Verifique os logs de erro do PHP
3. Desative e reative o plugin
4. Se o erro persistir, verifique o arquivo `wp-content/plugins/absloja-protheus-connector/debug-plugin.php`

---

## Teste 2: Interface Administrativa

### Objetivo
Verificar se todas as abas da interface administrativa estão acessíveis.

### Passos
1. Acesse WooCommerce → Protheus Connector
2. Verifique cada aba:
   - Connection (Conexão)
   - Mappings (Mapeamentos)
   - Schedule (Agendamento)
   - Logs
   - Advanced (Avançado)

### Resultado Esperado
- ✅ Todas as abas carregam sem erros
- ✅ Formulários são exibidos corretamente
- ✅ CSS está aplicado (visual limpo e organizado)
- ✅ JavaScript está funcionando (tabs navegáveis)

---

## Teste 3: Configuração de Conexão (Sem API Real)

### Objetivo
Testar a interface de configuração sem conectar ao Protheus real.

### Passos
1. Vá para a aba "Connection"
2. Preencha os campos:
   - **API URL**: `https://exemplo.com/api`
   - **Auth Type**: Basic Authentication
   - **Username**: `teste`
   - **Password**: `senha123`
3. Clique em "Salvar Configurações"

### Resultado Esperado
- ✅ Mensagem de sucesso aparece
- ✅ Dados são salvos (recarregue a página para confirmar)
- ✅ Senha aparece como "••••••" (criptografada)

---

## Teste 4: Test Connection (Esperado Falhar)

### Objetivo
Verificar se o botão "Test Connection" funciona e trata erros corretamente.

### Passos
1. Na aba "Connection", clique em "Test Connection"
2. Aguarde a resposta

### Resultado Esperado
- ✅ Botão mostra "Testando..." durante o processo
- ✅ Mensagem de erro aparece (API não existe)
- ✅ Erro é logado no sistema

### Verificação de Logs
1. Vá para a aba "Logs"
2. Verifique se há um log do tipo "API Request" com status "error"

---

## Teste 5: Configuração de Mapeamentos

### Objetivo
Testar a interface de configuração de mapeamentos.

### Passos
1. Vá para a aba "Mappings"
2. Configure mapeamentos de exemplo:

**Payment Methods:**
- `bacs` → `001` (Boleto)
- `credit-card` → `002` (Cartão)

**Categories:**
- `Eletrônicos` → `001`
- `Roupas` → `002`

**TES por Estado:**
- `SP` → `501`
- `RJ` → `502`
- `Outros` → `500`

3. Clique em "Salvar Mapeamentos"

### Resultado Esperado
- ✅ Mapeamentos são salvos
- ✅ Ao recarregar, mapeamentos aparecem preenchidos
- ✅ Botão "Reset to Defaults" funciona

---

## Teste 6: Configuração de Agendamento

### Objetivo
Configurar a frequência de sincronização.

### Passos
1. Vá para a aba "Schedule"
2. Configure:
   - **Catalog Sync**: 1 hour
   - **Stock Sync**: 30 minutes
3. Clique em "Salvar Configurações"

### Resultado Esperado
- ✅ Configurações são salvas
- ✅ Eventos WP-Cron são agendados

### Verificação de Cron
Execute no terminal (se tiver WP-CLI):
```bash
wp cron event list --path=.
```

Ou verifique no banco de dados:
```sql
SELECT * FROM wp_options WHERE option_name = 'cron';
```

---

## Teste 7: Visualizador de Logs

### Objetivo
Verificar se o visualizador de logs funciona corretamente.

### Passos
1. Vá para a aba "Logs"
2. Teste os filtros:
   - Filtrar por tipo
   - Filtrar por status
   - Filtrar por data
3. Clique em "Ver Detalhes" em um log
4. Clique em "Exportar CSV"

### Resultado Esperado
- ✅ Logs são exibidos em tabela
- ✅ Filtros funcionam
- ✅ Paginação funciona
- ✅ Modal de detalhes abre
- ✅ CSV é baixado

---

## Teste 8: Configurações Avançadas

### Objetivo
Testar configurações avançadas e geração de tokens.

### Passos
1. Vá para a aba "Advanced"
2. Configure:
   - **Batch Size**: 50
   - **Max Retries**: 5
   - **Log Retention**: 30 days
3. Clique em "Generate Token" para webhook token
4. Clique em "Generate Secret" para webhook secret
5. Salve as configurações

### Resultado Esperado
- ✅ Tokens são gerados automaticamente
- ✅ Configurações são salvas
- ✅ Endpoints de webhook são exibidos

---

## Teste 9: Dashboard Widget

### Objetivo
Verificar se o widget do dashboard funciona.

### Passos
1. Vá para WordPress Admin → Dashboard
2. Localize o widget "Protheus Connector Status"

### Resultado Esperado
- ✅ Widget é exibido
- ✅ Mostra estatísticas (mesmo que zeradas)
- ✅ Link para configurações funciona

---

## Teste 10: Webhooks (Teste com cURL)

### Objetivo
Testar os endpoints de webhook sem Protheus real.

### Passos

**10.1 - Webhook de Status de Pedido:**
```bash
curl -X POST http://seu-site.local/wp-json/absloja-protheus/v1/webhook/order-status \
  -H "Content-Type: application/json" \
  -H "X-Protheus-Token: SEU_TOKEN_AQUI" \
  -d '{
    "woo_order_id": 999,
    "status": "approved"
  }'
```

**10.2 - Webhook de Estoque:**
```bash
curl -X POST http://seu-site.local/wp-json/absloja-protheus/v1/webhook/stock \
  -H "Content-Type: application/json" \
  -H "X-Protheus-Token: SEU_TOKEN_AQUI" \
  -d '{
    "sku": "PROD001",
    "quantity": 50
  }'
```

### Resultado Esperado
- ✅ Webhook sem token retorna 401 Unauthorized
- ✅ Webhook com token inválido retorna 401
- ✅ Webhook com pedido inexistente retorna 404
- ✅ Todos os webhooks são logados

---

## Teste 11: Criação de Pedido WooCommerce

### Objetivo
Testar o fluxo completo de sincronização de pedido (sem API real).

### Passos
1. Crie um produto no WooCommerce
2. Crie um pedido manualmente:
   - Adicione o produto
   - Preencha dados do cliente (com CPF/CNPJ)
   - Escolha método de pagamento
3. Mude o status do pedido para "Processing"

### Resultado Esperado
- ✅ Plugin tenta sincronizar o pedido
- ✅ Erro é logado (API não existe)
- ✅ Retry é agendado
- ✅ Admin note é adicionado ao pedido

### Verificação
1. Vá para a aba "Logs" e verifique o log de erro
2. Vá para a aba "Advanced" → "Retry Queue" e veja o pedido na fila

---

## Teste 12: Sincronização Manual

### Objetivo
Testar os botões de sincronização manual.

### Passos
1. Vá para a aba "Schedule"
2. Clique em "Sync Catalog Now"
3. Aguarde a resposta
4. Clique em "Sync Stock Now"
5. Aguarde a resposta

### Resultado Esperado
- ✅ Botões mostram "Sincronizando..." durante o processo
- ✅ Mensagem de erro aparece (API não existe)
- ✅ Operações são logadas

---

## Teste 13: Retry Manual

### Objetivo
Testar o retry manual de operações falhadas.

### Passos
1. Vá para a aba "Advanced"
2. Na seção "Retry Queue", localize uma operação pendente
3. Clique em "Retry Now"

### Resultado Esperado
- ✅ Operação é reprocessada
- ✅ Erro ocorre novamente (API não existe)
- ✅ Contador de tentativas aumenta

---

## Teste 14: Desativação do Plugin

### Objetivo
Verificar se o plugin desativa corretamente.

### Passos
1. Vá para Plugins
2. Desative "ABS Loja Protheus Connector"

### Resultado Esperado
- ✅ Plugin desativa sem erros
- ✅ Eventos WP-Cron são removidos
- ✅ Dados permanecem no banco (tabelas e options)

### Verificação
```sql
-- Tabelas devem ainda existir
SHOW TABLES LIKE 'wp_absloja_%';

-- Options devem ainda existir
SELECT * FROM wp_options WHERE option_name LIKE 'absloja_protheus_%';
```

---

## Teste 15: Reativação do Plugin

### Objetivo
Verificar se o plugin pode ser reativado sem problemas.

### Passos
1. Reative o plugin
2. Verifique se as configurações foram preservadas

### Resultado Esperado
- ✅ Plugin reativa sem erros
- ✅ Configurações anteriores estão intactas
- ✅ Eventos WP-Cron são reagendados

---

## Testes com API Real do Protheus

Se você tiver acesso a uma API Protheus real, execute os seguintes testes adicionais:

### Teste 16: Conexão Real
1. Configure credenciais reais na aba "Connection"
2. Clique em "Test Connection"
3. Verifique se retorna sucesso

### Teste 17: Sincronização de Catálogo Real
1. Clique em "Sync Catalog Now"
2. Aguarde a sincronização
3. Verifique se produtos foram criados/atualizados no WooCommerce

### Teste 18: Sincronização de Estoque Real
1. Clique em "Sync Stock Now"
2. Verifique se quantidades foram atualizadas

### Teste 19: Pedido Real
1. Crie um pedido no WooCommerce
2. Mude para "Processing"
3. Verifique se foi sincronizado no Protheus
4. Verifique se metadata foi armazenado

### Teste 20: Webhook Real
1. Configure webhook no Protheus
2. Envie atualização de status
3. Verifique se pedido foi atualizado no WooCommerce

---

## Checklist Final

- [ ] Plugin ativa sem erros
- [ ] Todas as abas da interface funcionam
- [ ] Configurações são salvas corretamente
- [ ] Logs são criados e visualizados
- [ ] Webhooks respondem corretamente
- [ ] Retry queue funciona
- [ ] Dashboard widget aparece
- [ ] Plugin desativa sem erros
- [ ] Reativação preserva configurações
- [ ] Tradução pt_BR funciona

---

## Problemas Conhecidos

Se encontrar problemas, verifique:

1. **Plugin não ativa**: Verifique se WooCommerce está ativo
2. **Erro 500**: Verifique logs do PHP (`error_log`)
3. **CSS não carrega**: Limpe cache do navegador
4. **Webhooks não funcionam**: Verifique permalinks (Settings → Permalinks → Save)
5. **Cron não executa**: Configure cron real do servidor

---

## Suporte

Para problemas ou dúvidas:
1. Verifique os logs na aba "Logs"
2. Consulte a documentação em `docs/`
3. Entre em contato com o suporte técnico

