<template>
  <div class="validation-configurator">
    <!-- Section: Ajouter nouvelle règle de validation -->
    <v-card class="mb-6">
      <v-card-title>Configurer Validations par Colonne</v-card-title>
      <v-card-text>
        <v-row class="align-center">
          <!-- Sélection de Colonne -->
          <v-col cols="12" sm="4">
            <v-select
              v-model="newValidation.column"
              :items="availableColumns"
              label="Sélectionnez la Colonne"
              variant="outlined"
              hide-details
              item-title="title"
              item-value="title"
            />
          </v-col>

          <!-- Type de Validation -->
          <v-col cols="12" sm="4">
            <v-select
              v-model="newValidation.type"
              :items="validationTypes"
              label="Type de Validation"
              variant="outlined"
              hide-details
              @update:modelValue="resetRules"
            />
          </v-col>

          <!-- Bouton Ajouter -->
          <v-col cols="12" sm="4">
            <v-btn
              color="primary"
              @click="addValidationRule"
              :disabled="!newValidation.column || !newValidation.type"
              prepend-icon="mdi-plus"
              block
            >
              Ajouter Règle
            </v-btn>
          </v-col>
        </v-row>

        <!-- Règles Dynamiques basées sur le type -->
        <v-row v-if="newValidation.type" class="mt-4">
          <v-col cols="12">
            <v-card variant="outlined" class="pa-4">
              <h4 class="mb-4">Configurer Règles pour {{ newValidation.type }}</h4>

              <!-- Règles pour DATE -->
              <div v-if="newValidation.type === 'date'">
                <v-select
                  v-model="newValidation.rules.format"
                  :items="dateFormats"
                  label="Format de Date"
                  variant="outlined"
                  class="mb-3"
                  hide-details
                />
                <v-checkbox
                  v-model="newValidation.rules.required"
                  label="Champ obligatoire"
                  hide-details
                />
              </div>

              <!-- Règles pour NOMBRE -->
              <div v-else-if="newValidation.type === 'number'">
                <v-text-field
                  v-model.number="newValidation.rules.minValue"
                  label="Valeur minimum"
                  type="number"
                  variant="outlined"
                  class="mb-3"
                  hide-details
                />
                <v-text-field
                  v-model.number="newValidation.rules.maxValue"
                  label="Valeur maximum"
                  type="number"
                  variant="outlined"
                  class="mb-3"
                  hide-details
                />
                <v-checkbox
                  v-model="newValidation.rules.allowZero"
                  label="Autoriser Zéro"
                  hide-details
                  class="mb-3"
                />
                <v-checkbox
                  v-model="newValidation.rules.allowDecimal"
                  label="Autoriser Décimales"
                  hide-details
                />
              </div>

              <!-- Règles pour TEXTE -->
              <div v-else-if="newValidation.type === 'text'">
                <v-text-field
                  v-model.number="newValidation.rules.minLength"
                  label="Longueur minimum"
                  type="number"
                  variant="outlined"
                  class="mb-3"
                  hide-details
                />
                <v-text-field
                  v-model.number="newValidation.rules.maxLength"
                  label="Longueur maximum"
                  type="number"
                  variant="outlined"
                  class="mb-3"
                  hide-details
                />
                <v-checkbox
                  v-model="newValidation.rules.required"
                  label="Champ obligatoire"
                  hide-details
                />
              </div>

              <!-- Règles pour EMAIL -->
              <div v-else-if="newValidation.type === 'email'">
                <v-checkbox
                  v-model="newValidation.rules.required"
                  label="Champ obligatoire"
                  hide-details
                />
              </div>

              <!-- Règles pour CHOIX (Enum) -->
              <div v-else-if="newValidation.type === 'choice'">
                <v-text-field
                  v-model="enumValuesInput"
                  label="Valeurs autorisées (séparées par des virgules)"
                  variant="outlined"
                  class="mb-3"
                  hide-details
                  hint="Ex: Actif, Inactif, En attente"
                />
                <v-checkbox
                  v-model="newValidation.rules.required"
                  label="Champ obligatoire"
                  hide-details
                />
              </div>
            </v-card>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Section: Validations Configurées -->
    <v-card v-if="validationRules.length > 0" class="mb-6">
      <v-card-title>Validations Configurées</v-card-title>
      <v-card-text>
        <div class="validation-list">
          <v-card
            v-for="(validation, index) in validationRules"
            :key="index"
            class="mb-3 pa-4"
            variant="outlined"
          >
            <div class="d-flex justify-space-between align-center">
              <div>
                <div class="font-weight-bold">{{ validation.column }}</div>
                <div class="text-subtitle-2 text-medium-emphasis">
                  Type: {{ validation.type }}
                </div>
                <div class="text-caption">
                  {{ formatRulesDisplay(validation) }}
                </div>
              </div>
              <v-btn
                icon
                color="error"
                size="small"
                @click="removeValidationRule(index)"
                variant="text"
              >
                <v-icon>mdi-delete</v-icon>
              </v-btn>
            </div>
          </v-card>
        </div>
      </v-card-text>
    </v-card>

    <!-- Avertissement si aucune validation configurée -->
    <v-alert
      v-else
      type="info"
      variant="tonal"
      class="mb-6"
      icon="mdi-information"
    >
      Aucune règle de validation configurée encore. Ajoutez des règles pour valider vos colonnes.
    </v-alert>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

// Props
const props = defineProps({
  headers: {
    type: Array,
    default: () => [],
  },
})

// Emits
const emit = defineEmits(['update:validations'])

// Data
const validationRules = ref([])
const enumValuesInput = ref('')

const newValidation = ref({
  column: null,
  type: null,
  rules: {},
})

const validationTypes = [
  { title: 'Date', value: 'date' },
  { title: 'Nombre', value: 'number' },
  { title: 'Texte', value: 'text' },
  { title: 'Email', value: 'email' },
  { title: 'Choix (Enum)', value: 'choice' },
]

const dateFormats = [
  { title: 'DD/MM/YY', value: 'DD/MM/YY' },
  { title: 'DD/MM/YYYY', value: 'DD/MM/YYYY' },
  { title: 'YYYY-MM-DD', value: 'YYYY-MM-DD' },
  { title: 'DD-MM-YYYY', value: 'DD-MM-YYYY' },
  { title: 'MM/DD/YYYY', value: 'MM/DD/YYYY' },
]

// Computed
const availableColumns = computed(() => {
  return props.headers.filter(
    (header) =>
      !validationRules.value.some((rule) => rule.column === header.title)
  )
})

// Methods
function resetRules() {
  newValidation.value.rules = {}
  enumValuesInput.value = ''
}

function addValidationRule() {
  if (!newValidation.value.column || !newValidation.value.type) {
    return
  }

  // Processar valores enum se aplicável
  if (newValidation.value.type === 'choice' && enumValuesInput.value) {
    newValidation.value.rules.allowedValues = enumValuesInput.value
      .split(',')
      .map((val) => val.trim())
  }

  const rule = {
    column: newValidation.value.column,
    type: newValidation.value.type,
    rules: { ...newValidation.value.rules },
  }

  validationRules.value.push(rule)
  emit('update:validations', validationRules.value)

  // Reset form
  newValidation.value = {
    column: null,
    type: null,
    rules: {},
  }
  enumValuesInput.value = ''
}

function removeValidationRule(index) {
  validationRules.value.splice(index, 1)
  emit('update:validations', validationRules.value)
}

function formatRulesDisplay(validation) {
  const rules = validation.rules
  const parts = []

  switch (validation.type) {
    case 'date':
      if (rules.format) parts.push(`Format: ${rules.format}`)
      if (rules.required) parts.push('Obligatoire')
      break
    case 'number':
      if (rules.minValue !== undefined)
        parts.push(`Minimum: ${rules.minValue}`)
      if (rules.maxValue !== undefined)
        parts.push(`Maximum: ${rules.maxValue}`)
      if (!rules.allowZero) parts.push('Zéro non autorisé')
      if (rules.allowDecimal) parts.push('Décimales autorisées')
      break
    case 'text':
      if (rules.minLength !== undefined)
        parts.push(`Min. caractères: ${rules.minLength}`)
      if (rules.maxLength !== undefined)
        parts.push(`Max. caractères: ${rules.maxLength}`)
      if (rules.required) parts.push('Obligatoire')
      break
    case 'email':
      if (rules.required) parts.push('Obligatoire')
      break
    case 'choice':
      if (rules.allowedValues)
        parts.push(`Valeurs: ${rules.allowedValues.join(', ')}`)
      break
  }

  return parts.join(' | ')
}

// Exportar função para obter validações
defineExpose({
  getValidations: () => validationRules.value,
  resetValidations: () => {
    validationRules.value = []
    emit('update:validations', [])
  },
})
</script>

<style scoped>
.validation-configurator {
  width: 100%;
}

.validation-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
</style>
