# Tradução do Hello Elementor para ABS Tema

## O que foi feito

O arquivo de tradução do Hello Elementor foi completamente traduzido para português brasileiro e personalizado para o ABS Tema.

## Principais Alterações

### Substituições de "Hello" por "ABS Tema"

Todas as ocorrências de "Hello" foram substituídas por "ABS Tema":

| Original (Inglês) | Tradução Anterior | Nova Tradução |
|-------------------|-------------------|---------------|
| Hello | Hello | **ABS Tema** |
| Hello Theme | Tema Hello | **ABS Tema** |
| Hello Theme Header | Cabeçalho do Tema Hello | **Cabeçalho do ABS Tema** |
| Hello Theme Footer | Rodapé do Tema Hello | **Rodapé do ABS Tema** |
| Hello Elementor | Hello Elementor | **ABS Tema** |
| Thanks for installing the Hello Theme! | Obrigado por instalar o Tema Hello! | **Obrigado por instalar o ABS Tema!** |

### Traduções Completas

Todas as strings foram traduzidas para português:

#### Interface Geral
- Header → **Cabeçalho**
- Footer → **Rodapé**
- Menu → **Menu**
- Settings → **Configurações**
- Home → **Início**

#### Elementos de Design
- Layout → **Layout**
- Typography → **Tipografia**
- Color → **Cor**
- Background → **Fundo**
- Width → **Largura**
- Full Width → **Largura Total**
- Centered → **Centralizado**

#### Ações
- Show → **Mostrar**
- Hide → **Ocultar**
- Edit → **Editar**
- Add New → **Adicionar Novo**
- Save → **Salvar**

#### Mensagens
- "The page can't be found." → **"A página não pode ser encontrada."**
- "Something went wrong." → **"Algo deu errado."**
- "All rights reserved" → **"Todos os direitos reservados"**

## Arquivos Criados/Modificados

1. **hello-elementor-pt_BR.po** - Arquivo de tradução (texto)
2. **hello-elementor-pt_BR.mo** - Arquivo de tradução compilado (binário)
3. **TRADUCAO-ABS-TEMA.md** - Este documento

## Localização dos Arquivos

```
wp-content/languages/loco/themes/
├── hello-elementor-pt_BR.po    # Tradução editável
├── hello-elementor-pt_BR.mo    # Tradução compilada
└── TRADUCAO-ABS-TEMA.md        # Documentação
```

## Como Funciona

### Hierarquia de Traduções

O WordPress carrega traduções nesta ordem:
1. Traduções customizadas em `wp-content/languages/loco/themes/`
2. Traduções do tema em `wp-content/themes/hello-elementor/languages/`
3. Traduções do WordPress em `wp-content/languages/themes/`

Como colocamos a tradução em `loco/themes/`, ela tem prioridade sobre as outras.

### Verificação

Para verificar se as traduções estão ativas:

1. **Acesse o WordPress Admin**
2. **Vá em Aparência → Temas**
3. **Verifique se o tema mostra "ABS Tema" ao invés de "Hello"**
4. **Navegue pelas páginas do site**
5. **Todos os textos devem estar em português**

## Strings Principais Traduzidas

### Cabeçalho e Rodapé
- "Header & Footer" → "Cabeçalho e Rodapé"
- "Site Logo" → "Logo do Site"
- "Main menu" → "Menu principal"
- "Footer menu" → "Menu do rodapé"
- "Mobile menu" → "Menu mobile"

### Configurações
- "Theme Builder" → "Construtor de Tema"
- "Site Name" → "Nome do Site"
- "Add New Page" → "Adicionar Nova Página"
- "Customize your entire website" → "Personalize todo o seu site"

### Navegação
- "Skip to content" → "Pular para o conteúdo"
- "Previous" → "Anterior"
- "Next" → "Próximo"
- "Search results for:" → "Resultados da busca por:"

### Mensagens de Erro
- "The page can't be found." → "A página não pode ser encontrada."
- "It looks like nothing was found at this location." → "Parece que nada foi encontrado neste local."
- "Something went wrong." → "Algo deu errado."

## Descrição do Tema Traduzida

A descrição completa do tema foi traduzida:

**Original:**
> "Hello Elementor is a lightweight and minimalist WordPress theme..."

**Tradução:**
> "ABS Tema é um tema WordPress leve e minimalista construído especificamente para trabalhar perfeitamente com o plugin Elementor..."

## Compatibilidade

- ✅ WordPress 6.0+
- ✅ Hello Elementor 3.x
- ✅ Loco Translate (se instalado)
- ✅ WPML (se instalado)
- ✅ Polylang (se instalado)

## Manutenção

### Adicionar Novas Traduções

Se o tema Hello Elementor for atualizado e adicionar novas strings:

1. Edite o arquivo `hello-elementor-pt_BR.po`
2. Adicione as novas traduções
3. Recompile: `msgfmt hello-elementor-pt_BR.po -o hello-elementor-pt_BR.mo`
4. Limpe o cache do WordPress

### Editar Traduções Existentes

1. Abra `hello-elementor-pt_BR.po` em um editor de texto
2. Encontre a string que deseja modificar
3. Altere o valor em `msgstr ""`
4. Recompile o arquivo .mo
5. Limpe o cache

## Exemplo de Edição

Para mudar uma tradução:

```po
# Antes
msgid "Header"
msgstr "Cabeçalho"

# Depois (se quiser mudar)
msgid "Header"
msgstr "Topo"
```

Depois recompile:
```bash
msgfmt hello-elementor-pt_BR.po -o hello-elementor-pt_BR.mo
```

## Suporte

Para suporte ou modificações adicionais:
- **Website:** https://faleagencia.digital
- **Email:** contato@faleagencia.digital

---

© 2024 Fale Agência Digital. Desenvolvido para ABS Global.
