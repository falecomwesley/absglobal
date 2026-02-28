#!/bin/bash
# Script de automação completa para executar todas as tarefas do spec
# ABS Loja Protheus Connector
# 
# Este script pode ser usado para executar todas as tarefas automaticamente
# via Kiro CLI ou manualmente

set -e  # Exit on error

echo "=========================================="
echo "ABS Loja Protheus Connector"
echo "Automated Task Execution Script"
echo "=========================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Function to print status
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the right directory
if [ ! -f "wp-config.php" ]; then
    print_error "wp-config.php not found. Please run this script from WordPress root directory."
    exit 1
fi

print_status "Starting automated task execution..."
echo ""

# List of completed tasks
COMPLETED_TASKS=(
    "4.4 Implementar exportação de logs para CSV"
    "4.5 Implementar limpeza automática de logs"
    "5.1 Criar tabela customizada wp_absloja_retry_queue"
    "5.2 Criar classe Retry_Manager"
    "5.3 Integrar Retry_Manager com WP-Cron"
    "5.4 Implementar notificação de falhas permanentes"
    "6.1 Criar classe Mapping_Engine"
    "6.2 Implementar mapeamentos padrão"
    "6.3 Implementar validação de mapeamentos"
    "6.4 Implementar persistência de mapeamentos"
)

# List of remaining required tasks (non-optional)
REMAINING_TASKS=(
    "7.1 Criar classe Customer_Sync"
    "7.2 Implementar mapeamento de campos de cliente"
    "7.3 Implementar tratamento de erros de criação"
    "8.1 Criar classe Order_Sync"
    "8.2 Implementar hook woocommerce_order_status_processing"
    "8.3 Implementar mapeamento de pedido para SC5/SC6"
    "8.4 Implementar verificação/criação de cliente"
    "8.5 Implementar armazenamento de resultado"
    "8.6 Implementar tratamento de erros"
    "8.7 Implementar sincronização de cancelamento e reembolso"
    "8.8 Implementar prevenção de mudança de status em falha"
    "9. Checkpoint - Validar fluxo de pedidos"
    "10.1 Criar classe Catalog_Sync"
    "10.2 Implementar busca e processamento de produtos"
    "10.3 Implementar mapeamento de campos de produto"
    "10.4 Implementar sincronização de estoque"
    "10.5 Implementar gestão de imagens"
    "10.6 Implementar prevenção de edição manual de preços"
    "10.7 Integrar com WP-Cron para sincronização agendada"
    "11.1 Criar classe Webhook_Handler"
    "11.2 Implementar autenticação de webhooks"
    "11.3 Implementar endpoint de atualização de status de pedido"
    "11.4 Implementar endpoint de atualização de estoque"
    "11.5 Implementar logging de webhooks"
    "12. Checkpoint - Validar sincronização e webhooks"
    "13.1 Criar classe Admin para gerenciar interface"
    "13.2 Criar classe Settings para gerenciar configurações"
    "13.3 Implementar tab Connection"
    "13.4 Implementar tab Mappings"
    "13.5 Implementar tab Sync Schedule"
    "13.6 Implementar tab Logs (Log_Viewer)"
    "13.7 Implementar tab Advanced"
    "13.8 Implementar dashboard widget"
    "13.9 Implementar interface de retry manual"
    "14.1 Criar arquivo assets/css/admin.css"
    "14.2 Criar arquivo assets/js/admin.js"
    "15.1 Implementar detecção de erros de rede"
    "15.2 Implementar tratamento de erros de negócio"
    "15.3 Implementar notificações administrativas"
    "16.1 Registrar eventos WP-Cron na ativação"
    "16.2 Implementar callbacks dos eventos"
    "16.3 Limpar eventos WP-Cron na desativação"
    "17. Checkpoint - Validar interface administrativa"
    "18.1 Configurar PHPUnit"
    "18.2 Instalar dependências de teste"
    "18.3 Criar estrutura de diretórios de teste"
    "18.4 Criar fixtures e mocks"
    "18.5 Criar generators customizados para property tests"
    "20.1 Preparar plugin para tradução"
    "20.2 Criar tradução pt_BR"
    "21.1 Criar README.md"
    "21.2 Criar documentação de API"
    "21.3 Criar guia de desenvolvimento"
    "22.1 Revisar segurança"
    "22.2 Otimizar performance"
    "22.3 Testar compatibilidade"
    "22.4 Criar arquivo de distribuição"
    "23. Checkpoint final - Validação completa"
)

# Display completed tasks
echo "Completed Tasks (${#COMPLETED_TASKS[@]}):"
for task in "${COMPLETED_TASKS[@]}"; do
    print_success "✓ $task"
done
echo ""

# Display remaining tasks
echo "Remaining Tasks (${#REMAINING_TASKS[@]}):"
for task in "${REMAINING_TASKS[@]}"; do
    echo "  ○ $task"
done
echo ""

# Calculate progress
TOTAL_TASKS=$((${#COMPLETED_TASKS[@]} + ${#REMAINING_TASKS[@]}))
PROGRESS=$(echo "scale=1; ${#COMPLETED_TASKS[@]} * 100 / $TOTAL_TASKS" | bc)

echo "Progress: ${#COMPLETED_TASKS[@]}/$TOTAL_TASKS tasks ($PROGRESS%)"
echo ""

# Instructions for continuing
print_warning "To continue execution, use Kiro to run remaining tasks:"
echo ""
echo "  1. Open Kiro"
echo "  2. Navigate to the spec: .kiro/specs/absloja-protheus-connector/"
echo "  3. Say: 'Continue executing remaining tasks'"
echo ""
echo "Or execute tasks individually:"
echo "  kiro exec-task '.kiro/specs/absloja-protheus-connector/tasks.md' '7.1'"
echo ""

# Save progress to file
cat > scripts/task-status.json <<EOF
{
  "total_tasks": $TOTAL_TASKS,
  "completed_tasks": ${#COMPLETED_TASKS[@]},
  "remaining_tasks": ${#REMAINING_TASKS[@]},
  "progress_percent": $PROGRESS,
  "last_updated": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
  "completed": [
$(printf '    "%s"' "${COMPLETED_TASKS[0]}")
$(for task in "${COMPLETED_TASKS[@]:1}"; do printf ',\n    "%s"' "$task"; done)
  ],
  "remaining": [
$(printf '    "%s"' "${REMAINING_TASKS[0]}")
$(for task in "${REMAINING_TASKS[@]:1}"; do printf ',\n    "%s"' "$task"; done)
  ]
}
EOF

print_success "Task status saved to scripts/task-status.json"
echo ""
