# Como Converter a Documentação para Word

## Arquivos Criados

Foram criados 2 documentos em formato Markdown (.md):

1. **DOCUMENTACAO-INTEGRACAO-PROTHEUS.md** (Completo - 50+ páginas)
2. **RESUMO-EXECUTIVO-INTEGRACAO.md** (Resumido - 5 páginas)

---

## Método 1: Usando Pandoc (Recomendado)

### Instalação do Pandoc

**macOS:**
```bash
brew install pandoc
```

**Windows:**
Baixe em: https://pandoc.org/installing.html

**Linux:**
```bash
sudo apt-get install pandoc
```

### Conversão para Word

```bash
# Navegar até a pasta
cd wp-content/plugins/absloja-protheus-connector/docs/

# Converter documento completo
pandoc DOCUMENTACAO-INTEGRACAO-PROTHEUS.md -o DOCUMENTACAO-INTEGRACAO-PROTHEUS.docx

# Converter resumo executivo
pandoc RESUMO-EXECUTIVO-INTEGRACAO.md -o RESUMO-EXECUTIVO-INTEGRACAO.docx
```

### Conversão com Formatação Avançada

```bash
# Com índice automático
pandoc DOCUMENTACAO-INTEGRACAO-PROTHEUS.md \
  -o DOCUMENTACAO-INTEGRACAO-PROTHEUS.docx \
  --toc \
  --toc-depth=3 \
  --highlight-style=tango

# Com template customizado
pandoc DOCUMENTACAO-INTEGRACAO-PROTHEUS.md \
  -o DOCUMENTACAO-INTEGRACAO-PROTHEUS.docx \
  --reference-doc=template.docx \
  --toc
```

---

## Método 2: Usando Ferramentas Online

### Opção A: Dillinger.io
1. Acesse: https://dillinger.io
2. Cole o conteúdo do arquivo .md
3. Clique em "Export as" → "Styled HTML"
4. Abra o HTML no Word
5. Salve como .docx

### Opção B: StackEdit
1. Acesse: https://stackedit.io
2. Cole o conteúdo
3. Menu → Export to Disk → Word

### Opção C: Markdown to Word
1. Acesse: https://word2md.com
2. Cole o conteúdo Markdown
3. Clique em "Convert"
4. Baixe o arquivo .docx

---

## Método 3: Usando VS Code

### Extensão Markdown PDF

1. Instale a extensão "Markdown PDF"
2. Abra o arquivo .md no VS Code
3. Pressione `Ctrl+Shift+P` (ou `Cmd+Shift+P` no Mac)
4. Digite "Markdown PDF: Export (docx)"
5. Selecione a pasta de destino

---

## Método 4: Copiar e Colar no Word

### Passo a Passo

1. Abra o arquivo .md em um editor de texto
2. Copie todo o conteúdo
3. Abra o Microsoft Word
4. Cole o conteúdo
5. O Word irá formatar automaticamente:
   - Títulos (# → Título 1, ## → Título 2, etc.)
   - Listas
   - Tabelas
   - Blocos de código

### Ajustes Manuais no Word

Após colar, você pode:
- Aplicar estilos de título
- Formatar tabelas
- Adicionar cores aos blocos de código
- Inserir quebras de página
- Adicionar cabeçalho e rodapé
- Gerar índice automático

---

## Formatação Recomendada no Word

### Estilos de Título

- `#` → **Título 1** (Fonte: 24pt, Negrito)
- `##` → **Título 2** (Fonte: 18pt, Negrito)
- `###` → **Título 3** (Fonte: 14pt, Negrito)
- `####` → **Título 4** (Fonte: 12pt, Negrito)

### Blocos de Código

- Fonte: Consolas ou Courier New
- Tamanho: 10pt
- Fundo: Cinza claro (#F5F5F5)
- Borda: 1pt sólida

### Tabelas

- Estilo: Grid Table 4 - Accent 1
- Cabeçalho: Negrito, fundo azul
- Linhas alternadas: Sim

### Cores Sugeridas

- **Títulos principais:** Azul escuro (#003366)
- **Destaques:** Verde (#28A745)
- **Avisos:** Laranja (#FFA500)
- **Erros:** Vermelho (#DC3545)
- **Código:** Cinza (#6C757D)

---

## Adicionando Elementos no Word

### Índice Automático

1. Posicione o cursor no início do documento
2. Vá em **Referências → Índice**
3. Escolha um estilo
4. Clique em **OK**

### Cabeçalho e Rodapé

**Cabeçalho:**
```
ABS Global - Integração Protheus
Desenvolvido por: Fale Agência Digital
```

**Rodapé:**
```
Página X de Y                    Confidencial
```

### Capa

Adicione uma capa profissional:
1. **Inserir → Capa**
2. Escolha um modelo
3. Preencha:
   - Título: "Documentação de Integração Protheus"
   - Subtítulo: "WooCommerce + TOTVS Protheus ERP"
   - Autor: "Fale Agência Digital"
   - Data: "Fevereiro 2024"

---

## Checklist Final

Antes de enviar o documento:

- [ ] Índice gerado e atualizado
- [ ] Cabeçalho e rodapé configurados
- [ ] Numeração de páginas
- [ ] Estilos de título aplicados
- [ ] Tabelas formatadas
- [ ] Blocos de código destacados
- [ ] Imagens/diagramas inseridos (se houver)
- [ ] Quebras de página adequadas
- [ ] Revisão ortográfica
- [ ] Salvo em formato .docx

---

## Exemplo de Comando Completo

```bash
# Conversão completa com todas as opções
pandoc DOCUMENTACAO-INTEGRACAO-PROTHEUS.md \
  -o "ABS Global - Documentacao Integracao Protheus.docx" \
  --toc \
  --toc-depth=3 \
  --number-sections \
  --highlight-style=tango \
  --metadata title="Documentação de Integração Protheus" \
  --metadata author="Fale Agência Digital" \
  --metadata date="Fevereiro 2024"
```

---

## Dicas Profissionais

### Para Apresentação ao Cliente

1. **Use o documento completo** para a equipe técnica
2. **Use o resumo executivo** para gestores/diretores
3. **Adicione logo da empresa** no cabeçalho
4. **Use cores da marca** nos títulos
5. **Inclua informações de contato** no rodapé

### Para Impressão

- Margens: 2,5cm (todas)
- Orientação: Retrato
- Tamanho: A4
- Fonte: Arial ou Calibri
- Tamanho base: 11pt

### Para PDF

Após converter para Word, salve como PDF:
1. **Arquivo → Salvar Como**
2. Escolha **PDF**
3. Opções:
   - ✅ Otimizar para: Padrão
   - ✅ Incluir: Marcadores
   - ✅ Propriedades do documento

---

## Suporte

Se tiver dificuldades na conversão:

📧 **Email:** dev@faleagencia.digital  
🌐 **Website:** https://faleagencia.digital

---

© 2024 Fale Agência Digital
