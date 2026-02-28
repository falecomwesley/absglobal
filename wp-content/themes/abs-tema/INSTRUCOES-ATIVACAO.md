# Instruções de Ativação - ABS Tema

## Como Ativar o Tema

### Passo 1: Verificar Requisitos

Antes de ativar, certifique-se de que:
- ✅ O tema **Hello Elementor** está instalado (tema pai)
- ✅ WordPress 6.0 ou superior
- ✅ PHP 7.4 ou superior

### Passo 2: Ativar o Tema

1. Acesse **WordPress Admin**
2. Vá em **Aparência → Temas**
3. Localize o tema **ABS Tema**
4. Clique em **Ativar**

### Passo 3: Verificar Ativação

Após ativar, você deve ver:
- Nome do tema ativo: **ABS Tema**
- Descrição: Tema personalizado baseado no Hello Elementor
- Autor: **Fale Agência Digital**

## O que Mudou?

### Informações do Tema

| Antes (Hello Elementor) | Depois (ABS Tema) |
|------------------------|-------------------|
| Theme Name: Hello Elementor | Theme Name: **ABS Tema** |
| Author: Elementor Team | Author: **Fale Agência Digital** |
| Author URI: elementor.com | Author URI: **https://faleagencia.digital** |
| Theme URI: elementor.com/hello-theme | Theme URI: **https://faleagencia.digital** |

### Créditos no Rodapé

O rodapé agora mostra:
```
© 2024 [Nome do Site]. Desenvolvido por Fale Agência Digital
```

### Créditos no Admin

No rodapé do painel administrativo:
```
Tema desenvolvido por Fale Agência Digital
```

## Características do ABS Tema

### Mantém Todas as Funcionalidades do Hello Elementor

- ✅ Compatibilidade total com Elementor
- ✅ Leveza e performance
- ✅ Responsividade
- ✅ Todas as atualizações do tema pai

### Adiciona Personalizações

- ✅ Identidade visual da ABS Global
- ✅ Créditos da Fale Agência Digital
- ✅ Variáveis CSS customizadas
- ✅ Estrutura pronta para expansão

## Customizações Disponíveis

### 1. Logo Customizado

Vá em **Aparência → Personalizar → Identidade do Site → Logo**

### 2. Cores

Vá em **Aparência → Personalizar → Cores**

Ou edite as variáveis CSS em `style.css`:
```css
:root {
	--abs-primary-color: #0073aa;
	--abs-secondary-color: #005177;
	--abs-accent-color: #00a0d2;
}
```

### 3. Cabeçalho

Vá em **Aparência → Personalizar → Imagem do Cabeçalho**

### 4. Background

Vá em **Aparência → Personalizar → Cores de Fundo**

## Estrutura do Tema Filho

```
abs-tema/
├── style.css              # Estilos e informações do tema
├── functions.php          # Funções PHP customizadas
├── screenshot.png         # Preview do tema
├── README.md             # Documentação completa
├── INSTRUCOES-ATIVACAO.md # Este arquivo
└── languages/            # Traduções
    ├── pt_BR.po          # Tradução em português
    └── pt_BR.mo          # Tradução compilada
```

## Vantagens do Tema Filho

### Por que usar um Child Theme?

1. **Atualizações Seguras**
   - O tema pai (Hello Elementor) pode ser atualizado sem perder customizações

2. **Organização**
   - Todas as customizações ficam em um lugar separado

3. **Manutenção**
   - Fácil de identificar o que foi customizado

4. **Performance**
   - Herda toda a otimização do tema pai

## Compatibilidade

### Plugins Testados

- ✅ Elementor (recomendado)
- ✅ Elementor Pro
- ✅ WooCommerce
- ✅ ABS Loja Protheus Connector
- ✅ Contact Form 7
- ✅ Yoast SEO

### Versões do WordPress

- ✅ WordPress 6.0+
- ✅ WordPress 6.9+ (testado)

## Solução de Problemas

### Tema não aparece na lista

1. Verifique se a pasta está em `wp-content/themes/abs-tema`
2. Verifique se o arquivo `style.css` existe
3. Verifique se o tema pai (Hello Elementor) está instalado

### Erro ao ativar

1. Certifique-se de que o Hello Elementor está instalado
2. Verifique os requisitos de PHP (7.4+)
3. Verifique os logs de erro do WordPress

### Estilos não aparecem

1. Limpe o cache do navegador
2. Limpe o cache do WordPress (se usar plugin de cache)
3. Verifique se o arquivo `style.css` está correto

## Próximos Passos

Após ativar o tema:

1. **Configure o Logo**
   - Aparência → Personalizar → Identidade do Site

2. **Configure as Cores**
   - Aparência → Personalizar → Cores

3. **Instale o Elementor**
   - Se ainda não tiver instalado

4. **Crie suas Páginas**
   - Use o Elementor para criar layouts personalizados

5. **Configure o WooCommerce**
   - Se for usar loja virtual

## Suporte

Para suporte técnico ou desenvolvimento adicional:

- **Website:** https://faleagencia.digital
- **Email:** contato@faleagencia.digital

## Desenvolvido por

**Fale Agência Digital**
- Website: https://faleagencia.digital
- Especializada em desenvolvimento WordPress
- Soluções digitais personalizadas

---

© 2024 Fale Agência Digital. Todos os direitos reservados.
