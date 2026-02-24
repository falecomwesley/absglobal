# Configuração de Auto-Deploy com GitHub Webhooks

## Passo a Passo

### 1. No Servidor (SSH: root@217.216.92.134)

```bash
# Conecte ao servidor
ssh root@217.216.92.134

# Navegue até o diretório do projeto
cd /caminho/do/seu/projeto

# Clone o repositório se ainda não tiver
git clone https://github.com/seu-usuario/seu-repo.git

# Configure as credenciais do Git
git config --global user.name "Seu Nome"
git config --global user.email "seu@email.com"

# Instale Node.js se necessário
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt-get install -y nodejs

# Copie os arquivos webhook-listener.js e setup-webhook.sh para o servidor
# Edite o webhook-listener.js e configure:
nano webhook-listener.js
# - SECRET: crie um token forte (ex: openssl rand -hex 32)
# - REPO_PATH: caminho completo do repositório

# Execute o script de setup
chmod +x setup-webhook.sh
./setup-webhook.sh

# Configure o firewall
ufw allow 3000
```

### 2. No GitHub

1. Vá até o seu repositório no GitHub
2. Clique em **Settings** > **Webhooks** > **Add webhook**
3. Configure:
   - **Payload URL**: `http://217.216.92.134:3000/webhook`
   - **Content type**: `application/json`
   - **Secret**: o mesmo SECRET que você definiu no webhook-listener.js
   - **Which events**: Selecione "Just the push event"
   - **Active**: ✅ marcado
4. Clique em **Add webhook**

### 3. Teste

```bash
# No seu computador local
git add .
git commit -m "Teste de webhook"
git push origin main

# No servidor, verifique os logs
pm2 logs github-webhook
```

## Opção Alternativa: GitHub Actions

Se preferir usar GitHub Actions (mais simples), crie este arquivo:

`.github/workflows/deploy.yml`

```yaml
name: Deploy to Server

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Deploy via SSH
        uses: appleboy/ssh-action@master
        with:
          host: 217.216.92.134
          username: root
          password: ${{ secrets.SERVER_PASSWORD }}
          script: |
            cd /caminho/do/seu/projeto
            git pull origin main
            # Adicione comandos extras se necessário (npm install, restart, etc)
```

Depois adicione o SECRET no GitHub:
- Settings > Secrets and variables > Actions > New repository secret
- Nome: `SERVER_PASSWORD`
- Valor: `L5904H0L4z3c77prZegK85`

## Comandos Úteis

```bash
# Ver logs do webhook
pm2 logs github-webhook

# Reiniciar webhook
pm2 restart github-webhook

# Parar webhook
pm2 stop github-webhook

# Status
pm2 status
```

## Segurança

⚠️ **Importante:**
- Use HTTPS em produção (configure nginx como proxy reverso)
- Mantenha o SECRET seguro
- Configure autenticação SSH com chaves em vez de senha
- Considere usar um usuário não-root para o deploy
