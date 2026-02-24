#!/bin/bash

echo "🔧 Configurando webhook no servidor..."

# Cores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}1. Instalando dependências...${NC}"
# Se usar Node.js
command -v node >/dev/null 2>&1 || { echo "Node.js não encontrado. Instale primeiro."; exit 1; }

echo -e "${YELLOW}2. Configurando PM2 para manter o webhook rodando...${NC}"
npm install -g pm2

echo -e "${YELLOW}3. Iniciando o webhook listener...${NC}"
pm2 start webhook-listener.js --name github-webhook
pm2 save
pm2 startup

echo -e "${GREEN}✅ Webhook configurado!${NC}"
echo ""
echo "Próximos passos:"
echo "1. Edite webhook-listener.js e configure:"
echo "   - SECRET: um token secreto forte"
echo "   - REPO_PATH: caminho do seu repositório no servidor"
echo ""
echo "2. Configure o firewall para permitir a porta 3000:"
echo "   sudo ufw allow 3000"
echo ""
echo "3. No GitHub, vá em Settings > Webhooks > Add webhook:"
echo "   - Payload URL: http://217.216.92.134:3000/webhook"
echo "   - Content type: application/json"
echo "   - Secret: o mesmo SECRET do webhook-listener.js"
echo "   - Events: Just the push event"
