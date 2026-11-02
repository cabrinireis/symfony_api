# ValidationRulesConfigurator - Guia Técnico e Exemplos

## 📐 Arquitetura do Componente

```
HelloWorld.vue (Componente Principal)
├── Upload de Arquivo
├── Exibição de Dados
└── ValidationRulesConfigurator ✨ (Novo)
    ├── Seletor de Coluna
    ├── Seletor de Tipo de Validação
    ├── Painel de Regras Dinâmicas
    └── Lista de Validações Configuradas
```

## 🎨 Interface do Usuário

### 1. Área de Configuração - Validação por Data

```
┌─────────────────────────────────────────────────────────────┐
│ Configurar Validações por Coluna                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  [Selecione a Coluna ▼]  [Tipo de Validação ▼]  [+ Adicionar] │
│                                                             │
│  ┌──────────────────────────────────────────────────┐      │
│  │ Configurar Regras para data                      │      │
│  ├──────────────────────────────────────────────────┤      │
│  │ [Formato de Data ▼] (DD/MM/YYYY selecionado)   │      │
│  │ ☐ Campo obrigatório                            │      │
│  └──────────────────────────────────────────────────┘      │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### 2. Área de Configuração - Validação por Número

```
┌─────────────────────────────────────────────────────────────┐
│ Configurar Validações por Coluna                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  [Selecione a Coluna ▼]  [Tipo de Validação ▼]  [+ Adicionar] │
│                                                             │
│  ┌──────────────────────────────────────────────────┐      │
│  │ Configurar Regras para number                    │      │
│  ├──────────────────────────────────────────────────┤      │
│  │ Valor mínimo              [0             ]      │      │
│  │ Valor máximo              [             ]      │      │
│  │ ☑ Permitir Zero                                │      │
│  │ ☐ Permitir Casas Decimais                      │      │
│  └──────────────────────────────────────────────────┘      │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### 3. Lista de Validações Configuradas

```
┌─────────────────────────────────────────────────────────────┐
│ Validações Configuradas                                     │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ Date de dernière écriture                      [🗑️]  │  │
│  │ Tipo: date                                         │  │
│  │ Formato: DD/MM/YYYY | Obrigatório               │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ PIF                                            [🗑️]  │  │
│  │ Tipo: number                                       │  │
│  │ Mínimo: 0 | Não permite zero | Não permite decimais│  │
│  └──────────────────────────────────────────────────────┘  │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

## 🔄 Estados do Componente

### Estado 1: Antes do Upload
- Componente ValidationRulesConfigurator não é renderizado
- Usuário só vê o input de arquivo e botão "Processar"

### Estado 2: Após Upload
- Dados da tabela aparecem
- **Componente ValidationRulesConfigurator aparece**
- Dropdown de colunas mostra todas as colunas disponíveis

### Estado 3: Adicionando Validação
- Usuário seleciona coluna: "Date de dernière écriture"
- Seleciona tipo: "Data"
- Painel dinâmico mostra campos específicos para data
- Configurar formato e regras
- Clica "Adicionar Regra"

### Estado 4: Validação Adicionada
- Validação aparece na lista
- Coluna é removida do dropdown (não pode duplicar)
- Botão "Validar com Regras Configuradas" fica ativo

## 💻 Exemplos de Código

### Exemplo 1: Adicionar Validação de Data

```javascript
// Estado do componente filho
newValidation.value = {
  column: "Date de dernière écriture",
  type: "date",
  rules: {
    format: "DD/MM/YYYY",
    required: true
  }
}

// Após clicar "Adicionar Regra"
validationRules.value = [
  {
    column: "Date de dernière écriture",
    type: "date",
    rules: {
      format: "DD/MM/YYYY",
      required: true
    }
  }
]
```

### Exemplo 2: Adicionar Validação de Número com Restrições

```javascript
newValidation.value = {
  column: "PIF",
  type: "number",
  rules: {
    minValue: 0,
    maxValue: undefined,
    allowZero: false,
    allowDecimal: false
  }
}
```

### Exemplo 3: Adicionar Validação de Escolha (Enum)

```javascript
// Usuário digita: "Ativo, Inativo, Pendente"
enumValuesInput.value = "Ativo, Inativo, Pendente"

// Processado para:
newValidation.value = {
  column: "Status",
  type: "choice",
  rules: {
    allowedValues: ["Ativo", "Inativo", "Pendente"],
    required: true
  }
}
```

### Exemplo 4: Integração no HelloWorld.vue

```vue
<template>
  <!-- ... -->
  
  <!-- Renderizado após upload -->
  <ValidationRulesConfigurator
    ref="validationConfigurator"
    :headers="headers"
    @update:validations="onValidationsUpdate"
  />
  
  <!-- Botão novo -->
  <v-btn @click="validateWithRules" :disabled="validationRules.length === 0">
    Validar com Regras
  </v-btn>
</template>

<script setup>
  const validationRules = ref([])
  
  function onValidationsUpdate(rules) {
    validationRules.value = rules
  }
  
  async function validateWithRules() {
    const formData = new FormData()
    formData.append('odsFile', selectedFile.value)
    formData.append('validations', JSON.stringify(validationRules.value))
    
    const response = await fetch('/api/ods/validate', {
      method: 'POST',
      body: formData
    })
  }
</script>
```

## 🎯 Fluxo de Dados Completo

```
┌─────────────────┐
│ Frontend        │
├─────────────────┤
│                 │
│  HelloWorld.vue │
│   │             │
│   └─ Upload ODS │ ──────────────────────────────────┐
│      ↓          │                                   │
│   [Exibe dados] │ ←─────── GET colunas e dados ────┤
│      ↓          │                                   │
│   Validations   │                                   │
│   Configurator  │                                   │
│      ↓          │                                   │
│   [Configura]   │                                   │
│      ↓          │                                   │
│   validateWith  │                                   │
│   Rules()       │──────── POST arquivo + rules ────┤
│      ↓          │                                   │
│   [Exibe       │←─── GET validation results ────┤
│    erros]      │                                   │
│                 │                                   │
├─────────────────┤                   ┌────────────────────┐
│ Backend         │                   │ OdsReaderService   │
├─────────────────┤                   ├────────────────────┤
│                 │                   │                    │
│ POST /api/ods/  │─ validações ─────→ validateWithRules() │
│    validate     │                   │                    │
│                 │←─ erros ─────────  │                    │
│                 │                   │                    │
└─────────────────┘                   └────────────────────┘
```

## 🧪 Teste Manual

### Cenário 1: Validação de Data

1. Upload do arquivo
2. Selecione coluna: "Date de dernière écriture"
3. Tipo: "Data"
4. Formato: "DD/MM/YYYY"
5. Marque "Campo obrigatório"
6. Clique "Adicionar Regra"
7. Clique "Validar com Regras Configuradas"
8. **Esperado**: Erros mostrando datas com formato inválido

### Cenário 2: Validação de Número com Mínimo

1. Upload do arquivo
2. Selecione coluna: "PIF"
3. Tipo: "Número"
4. Valor mínimo: 0
5. Marque "Não permitir Zero" (desmarque allowZero)
6. Clique "Adicionar Regra"
7. Clique "Validar com Regras Configuradas"
8. **Esperado**: Erros mostrando PIF com valor 0 ou negativo

### Cenário 3: Múltiplas Validações

1. Adicione validação para Data
2. Adicione validação para Número
3. Adicione validação para Texto
4. Veja todas na lista
5. Clique "Validar com Regras Configuradas"
6. **Esperado**: Erros agregados de todas as validações

## 🛠️ Métodos Auxiliares

### formatRulesDisplay(validation)

Formata as regras para exibição legível:

```javascript
// Input
{
  column: "PIF",
  type: "number",
  rules: { minValue: 0, allowZero: false }
}

// Output
"Mínimo: 0 | Não permite zero"
```

### resetRules()

Limpa as regras quando o tipo de validação é alterado:

```javascript
newValidation.value.rules = {}
enumValuesInput.value = ''
```

### getValidations()

Exportado via `defineExpose`:

```javascript
const validations = validationConfigurator.value.getValidations()
// Retorna: Array com todas as validações configuradas
```

## ⚠️ Validações e Limitações

1. **Coluna duplicada**: Não permite adicionar mesma coluna 2x
2. **Tipo obrigatório**: Deve selecionar tipo para ver painel de regras
3. **Coluna obrigatória**: Deve selecionar coluna para ativar botão
4. **Enum values**: Separadas por vírgula, trim automático
5. **Números**: Min/Max podem ser deixados vazios
