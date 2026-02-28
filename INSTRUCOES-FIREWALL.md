# Configuração do Firewall para GitHub Actions

## Passo 1: Conectar ao servidor via SSH

```bash
ssh root@217.21.92.134
```

## Passo 2: Verificar qual firewall está ativo

```bash
# Verificar se é UFW
sudo ufw status

# Ou verificar se é iptables
sudo iptables -L -n

# Ou verificar se é firewalld
sudo firewall-cmd --state
```

## Passo 3: Liberar IPs do GitHub Actions

O GitHub Actions usa uma lista de IPs que pode mudar. A melhor abordagem é:

### Opção A: Liberar todos os IPs do GitHub (Recomendado)

```bash
# Baixar a lista de IPs do GitHub
curl https://api.github.com/meta | jq -r '.actions[]' > github-actions-ips.txt

# Ver os IPs
cat github-actions-ips.txt
```

### Opção B: Usar UFW (se estiver usando UFW)

```bash
# Para cada IP da lista, execute:
sudo ufw allow from <IP> to any port 22 proto tcp

# Exemplo:
sudo ufw allow from 4.175.114.51/32 to any port 22 proto tcp
sudo ufw allow from 13.64.0.0/16 to any port 22 proto tcp
# ... repetir para todos os IPs
```

### Opção C: Usar iptables (se estiver usando iptables)

```bash
# Para cada IP da lista, execute:
sudo iptables -A INPUT -p tcp -s <IP> --dport 22 -j ACCEPT

# Exemplo:
sudo iptables -A INPUT -p tcp -s 4.175.114.51/32 --dport 22 -j ACCEPT

# Salvar as regras
sudo iptables-save > /etc/iptables/rules.v4
```

### Opção D: Usar firewalld (se estiver usando firewalld)

```bash
# Para cada IP da lista, execute:
sudo firewall-cmd --permanent --add-rich-rule='rule family="ipv4" source address="<IP>" port protocol="tcp" port="22" accept'

# Recarregar o firewall
sudo firewall-cmd --reload
```

## Passo 4: Script automatizado para liberar IPs do GitHub

Crie um script para facilitar:

```bash
#!/bin/bash

# Baixar IPs do GitHub Actions
IPS=$(curl -s https://api.github.com/meta | jq -r '.actions[]')

# Liberar cada IP no UFW
for IP in $IPS; do
    echo "Liberando IP: $IP"
    sudo ufw allow from $IP to any port 22 proto tcp
done

echo "✅ Todos os IPs do GitHub Actions foram liberados!"
```

Salve como `liberar-github-ips.sh` e execute:

```bash
chmod +x liberar-github-ips.sh
sudo ./liberar-github-ips.sh
```

## Passo 5: Verificar se funcionou

Após liberar os IPs, faça um novo push e verifique se o GitHub Actions consegue conectar.

## Alternativa: Usar chave SSH em vez de senha

Para maior segurança, é recomendado usar chave SSH:

1. No GitHub, vá em Settings > Secrets and variables > Actions
2. Adicione uma nova secret chamada `SSH_PRIVATE_KEY` com sua chave privada
3. Atualize o workflow para usar a chave em vez da senha

## Observação Importante

Os IPs do GitHub Actions podem mudar. Considere:
- Criar um script cron que atualiza os IPs periodicamente
- Ou usar a solução de webhook que é mais estável
