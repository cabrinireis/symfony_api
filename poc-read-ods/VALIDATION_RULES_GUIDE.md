# Sistema de Validações Customizáveis - Frontend

## 📋 Visão Geral

Foram criados dois novos componentes Vue 3 que permitem configurar validações por coluna no frontend de forma dinâmica e intuitiva.

## 🎨 Componente: `ValidationRulesConfigurator.vue`

Novo arquivo em: `src/components/ValidationRulesConfigurator.vue`

### Funcionalidades

1. **Seleção de Coluna**
   - Dropdown com nomes das colunas do ODS
   - Impede duplicação: coluna já configurada não aparece no dropdown

2. **Tipos de Validação Disponíveis**
   - ✅ **Data**: Validar formato de data
   - ✅ **Número**: Validar inteiros e decimais
   - ✅ **Texto**: Validar comprimento e obrigatoriedade
   - ✅ **Email**: Validar formato de email
   - ✅ **Escolha (Enum)**: Validar valores de uma lista

3. **Regras Dinâmicas por Tipo**

   **Data:**
   - Formato (DD/MM/YYYY, YYYY-MM-DD, etc.)
   - Obrigatoriedade

   **Número:**
   - Valor mínimo
   - Valor máximo
   - Permitir zero
   - Permitir casas decimais

   **Texto:**
   - Comprimento mínimo
   - Comprimento máximo
   - Obrigatoriedade

   **Email:**
   - Campo obrigatório

   **Escolha:**
   - Valores permitidos (separados por vírgula)
   - Obrigatoriedade

### Estrutura de Dados Enviada

As validações são armazenadas e enviadas ao backend neste formato:

```javascript
[
  {
    column: "Date de dernière écriture",
    type: "date",
    rules: {
      format: "DD/MM/YYYY",
      required: true
    }
  },
  {
    column: "PIF",
    type: "number",
    rules: {
      minValue: 0,
      maxValue: null,
      allowZero: false,
      allowDecimal: false
    }
  },
  {
    column: "Status",
    type: "choice",
    rules: {
      allowedValues: ["Ativo", "Inativo", "Pendente"],
      required: true
    }
  }
]
```

## 🔧 Modificações em `HelloWorld.vue`

### 1. Nova Seção de Configuração
Após o upload do arquivo e exibição dos dados, aparece o componente `ValidationRulesConfigurator`:

```vue
<v-row v-if="headers.length > 0" class="mt-8">
  <v-col cols="12">
    <ValidationRulesConfigurator
      ref="validationConfigurator"
      :headers="headers"
      @update:validations="onValidationsUpdate"
    />
  </v-col>
</v-row>
```

### 2. Novo Botão: "Validar com Regras Configuradas"
Aparece junto ao botão de download:

```vue
<v-btn
  color="info"
  class="ml-4"
  @click="validateWithRules"
  :disabled="!items.length || validationRules.length === 0"
  prepend-icon="mdi-check-circle"
>
  Validar com Regras Configuradas
</v-btn>
```

### 3. Novo método `validateWithRules()`
Envia as validações configuradas para o backend:

```javascript
async function validateWithRules() {
  const tempFormData = new FormData()
  tempFormData.append('odsFile', selectedFile.value)
  tempFormData.append('sheetName', 'XX) Onglet technique - TOTAL')
  tempFormData.append('validations', JSON.stringify(validationRules.value))

  const response = await fetch('http://localhost:8080/api/ods/validate', {
    method: 'POST',
    body: tempFormData,
  })
  // ... processar resposta
}
```

### 4. Novo evento `onValidationsUpdate()`
Recebe as validações do componente filho:

```javascript
function onValidationsUpdate(rules) {
  validationRules.value = rules
  console.log('Validações atualizadas:', rules)
}
```

## 🎯 Fluxo de Uso

1. **Usuário faz upload do arquivo ODS**
   ```
   Arquivo → Processar → Dados exibidos na tabela
   ```

2. **Componente de validação aparece**
   ```
   Seleciona coluna → Escolhe tipo → Define regras → Adiciona
   ```

3. **Usuário configura múltiplas validações**
   ```
   Coluna 1 + Regras → Coluna 2 + Regras → ... → Lista de validações
   ```

4. **Clica em "Validar com Regras Configuradas"**
   ```
   Envia arquivo + validações → Backend processa → Retorna erros específicos
   ```

5. **Erros são exibidos na tela**
   ```
   Lista com linha, coluna e mensagem de erro específica
   ```

## 📦 Props e Emits

### Props
- `headers` (Array): Lista de colunas do arquivo ODS

### Emits
- `@update:validations`: Emitido quando regras são adicionadas/removidas

### Métodos Exportados (via `ref`)
- `getValidations()`: Retorna array com todas as validações configuradas
- `resetValidations()`: Limpa todas as validações e reemite evento

## 🔄 Próximos Passos (Backend)

O backend deve:
1. Receber o arquivo ODS + array de validações
2. Aplicar cada validação na coluna correspondente
3. Retornar array de erros detalhados: `{ row, column, message }`
4. Exemplo de endpoint: `POST /api/ods/validate`

## 📝 Exemplo de Resposta do Backend

```json
{
  "success": false,
  "validationErrors": [
    {
      "row": 5,
      "column": "Date de dernière écriture",
      "value": "32/13/2024",
      "message": "Data inválida. Formato esperado: DD/MM/YYYY"
    },
    {
      "row": 10,
      "column": "PIF",
      "value": "-5",
      "message": "Número deve ser maior que 0"
    }
  ]
}
```
