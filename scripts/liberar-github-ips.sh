#!/bin/bash

echo "🔧 Configurando firewall para GitHub Actions..."
echo ""

# Verificar se jq está instalado
if ! command -v jq &> /dev/null; then
    echo "📦 Instalando jq..."
    apt-get update && apt-get install -y jq
fi

# Baixar IPs do GitHub Actions
echo "📥 Baixando lista de IPs do GitHub Actions..."
IPS=$(curl -s https://api.github.com/meta | jq -r '.actions[]')

if [ -z "$IPS" ]; then
    echo "❌ Erro ao baixar IPs do GitHub"
    exit 1
fi

echo "📋 IPs encontrados:"
echo "$IPS"
echo ""

# Detectar qual firewall está ativo
if command -v ufw &> /dev/null && ufw status | grep -q "Status: active"; then
    echo "🔥 Usando UFW..."
    for IP in $IPS; do
        echo "  ➜ Liberando $IP"
        ufw allow from $IP to any port 22 proto tcp comment "GitHub Actions"
    done
    echo "✅ Regras UFW adicionadas!"
    
elif command -v firewall-cmd &> /dev/null && firewall-cmd --state &> /dev/null; then
    echo "🔥 Usando firewalld..."
    for IP in $IPS; do
        echo "  ➜ Liberando $IP"
        firewall-cmd --permanent --add-rich-rule="rule family=\"ipv4\" source address=\"$IP\" port protocol=\"tcp\" port=\"22\" accept"
    done
    firewall-cmd --reload
    echo "✅ Regras firewalld adicionadas!"
    
elif command -v iptables &> /dev/null; then
    echo "🔥 Usando iptables..."
    for IP in $IPS; do
        echo "  ➜ Liberando $IP"
        iptables -A INPUT -p tcp -s $IP --dport 22 -j ACCEPT -m comment --comment "GitHub Actions"
    done
    # Salvar regras
    if [ -f /etc/debian_version ]; then
        iptables-save > /etc/iptables/rules.v4
    elif [ -f /etc/redhat-release ]; then
        service iptables save
    fi
    echo "✅ Regras iptables adicionadas!"
    
else
    echo "❌ Nenhum firewall detectado ou não está ativo"
    exit 1
fi

echo ""
echo "🎉 Configuração concluída!"
echo "📝 Agora faça um push no GitHub e verifique se o deploy funciona."
