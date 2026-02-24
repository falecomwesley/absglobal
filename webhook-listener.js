const http = require('http');
const crypto = require('crypto');
const { exec } = require('child_process');

// Configurações
const PORT = 3000;
const SECRET = 'seu_secret_aqui'; // Defina um secret forte
const REPO_PATH = '/caminho/para/seu/repositorio'; // Ajuste o caminho

// Função para verificar a assinatura do GitHub
function verifySignature(payload, signature) {
  const hmac = crypto.createHmac('sha256', SECRET);
  const digest = 'sha256=' + hmac.update(payload).digest('hex');
  return crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(digest));
}

// Servidor HTTP
const server = http.createServer((req, res) => {
  if (req.method === 'POST' && req.url === '/webhook') {
    let body = '';

    req.on('data', chunk => {
      body += chunk.toString();
    });

    req.on('end', () => {
      const signature = req.headers['x-hub-signature-256'];
      
      // Verifica a assinatura
      if (!signature || !verifySignature(body, signature)) {
        console.log('❌ Assinatura inválida');
        res.writeHead(401);
        res.end('Unauthorized');
        return;
      }

      const event = req.headers['x-github-event'];
      
      if (event === 'push') {
        console.log('✅ Push recebido! Atualizando repositório...');
        
        // Executa git pull
        exec(`cd ${REPO_PATH} && git pull origin main`, (error, stdout, stderr) => {
          if (error) {
            console.error('❌ Erro ao executar git pull:', error);
            res.writeHead(500);
            res.end('Error');
            return;
          }
          
          console.log('📦 Repositório atualizado:', stdout);
          res.writeHead(200);
          res.end('OK');
        });
      } else {
        res.writeHead(200);
        res.end('OK');
      }
    });
  } else {
    res.writeHead(404);
    res.end('Not Found');
  }
});

server.listen(PORT, () => {
  console.log(`🚀 Webhook listener rodando na porta ${PORT}`);
});
