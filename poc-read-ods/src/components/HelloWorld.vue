<template>
  <div>
    <v-row class="align-center">
      <v-col cols="5">
        <v-form @submit.prevent="processar" class="d-flex">
          <v-file-input
            v-model="selectedFile"
            accept=".ods,.xlsx,.xls"
            label="Sélectionnez un fichier"
            prepend-icon="mdi-file"
            show-size
            variant="outlined"
            hide-details
          />

          <v-btn
            class="mt-4 ml-4"
            color="primary"
            :disabled="!selectedFile"
            type="submit"
          >
            Traiter
          </v-btn>
        </v-form>

      </v-col>
        <v-spacer class="my-4" />
      <v-col>
           <v-text-field
          v-model="search"
          class="mt-4"
          label="Rechercher"
          variant="outlined"
          hide-details
        />
      </v-col>
      </v-row>

    <!-- Validation Configurator - Displayed after upload -->
    <v-row v-if="headers.length > 0" class="mt-8">
      <v-col cols="12">
        <ValidationRulesConfigurator
          ref="validationConfigurator"
          :headers="headers"
          @update:validations="onValidationsUpdate"
        />
      </v-col>
    </v-row>
    <v-row>
      <v-col cols="12">
        <div style="overflow-x: auto; max-width: 100%;">
          <v-data-table
            :headers="headers"
            :items="items"
            :loading="loading"
            :search="search"
            density="compact"
          />
        </div>
      </v-col>
    </v-row>
    <v-row class="mt-4">
      <v-col cols="12">
        <v-btn
          color="success"
          :loading="downloadingFile"
          @click="downloadFilteredOds"
          :disabled="!items.length"
          prepend-icon="mdi-download"
        >
          Télécharger Données Filtrées ({{ items.length }} lignes)
        </v-btn>
        <v-btn
          color="info"
          class="ml-4"
          @click="validateWithRules"
          :disabled="!items.length || validationRules.length === 0"
          prepend-icon="mdi-check-circle"
        >
          Valider avec Règles Configurées
        </v-btn>
      </v-col>
    </v-row>
    <v-alert
    v-if="erros.length"
      :value="alert"
      color="pink"
      dark
      icon="mdi-alert-circle"
      transition="scale-transition"
      class="mt-6"
    >
        <ul style="height: 200px; overflow-y: auto;">
          <li v-for="(erro, index) in erros" :key="index">{{ erro }}</li>
        </ul>
    </v-alert>
  </div>
</template>

<script setup>
  import { ref } from 'vue'
  import ValidationRulesConfigurator from './ValidationRulesConfigurator.vue'

  const selectedFile = ref(null)
  const headers = ref([])
  const items = ref([])
  const erros = ref([])
  const loading = ref(false)
  const downloadingFile = ref(false)
  const search = ref('')
  const validationRules = ref([])
  const validationConfigurator = ref(null)

  async function processar () {
    if (!selectedFile.value) return

    try {
      loading.value = true
      const tempFormData = new FormData()
      tempFormData.append('odsFile', selectedFile.value)
      tempFormData.append('sheetName', 'XX) Onglet technique - TOTAL')
      tempFormData.append('page', '1');
      tempFormData.append('limit', '30');
      const response = await fetch('http://localhost:8080/api/ods/upload', {
        method: 'POST',
        body: tempFormData,
      })
      const dataResponse = await response.json()
      headers.value = dataResponse.headers
      items.value = dataResponse.data
      mapInvalidValuesByRow(dataResponse.errors);
    } catch (error) {
    } finally {
      loading.value = false
    }
  }

   function mapInvalidValuesByRow(data) {

  data.forEach(row => {
    row.columns.forEach(col => {
      if (
        col.value.includes("#N/")
      ) {
        erros.value.push(
          `Ligne ${row.rowNumber} ___ colonne "${col.column}" = "${col.value}" - invalide`
        );
      }
    });
  });

  return
}

function onValidationsUpdate(rules) {
  validationRules.value = rules
  console.log('Validations mises à jour:', rules)
}

async function validateWithRules() {
  if (!selectedFile.value || validationRules.value.length === 0) return

  try {
    loading.value = true
    const tempFormData = new FormData()
    tempFormData.append('odsFile', selectedFile.value)
    tempFormData.append('sheetName', 'XX) Onglet technique - TOTAL')
    tempFormData.append('validations', JSON.stringify(validationRules.value))

    console.log('📤 Envoi des validations:', validationRules.value)

    const response = await fetch('http://localhost:8080/api/ods/filter', {
      method: 'POST',
      body: tempFormData,
    })

    const dataResponse = await response.json()

    console.log('📥 Réponse du backend:', dataResponse)

    if (dataResponse.success) {
      // Update the table with valid data only
      items.value = dataResponse.data

      // Calculate how many rows were removed
      const removedCount = dataResponse.originalCount - dataResponse.filteredCount

      if (removedCount > 0) {
        alert(`Filtre appliqué! ${removedCount} lignes invalides ont été supprimées. ${dataResponse.filteredCount} lignes valides restantes.`)
      } else {
        alert('Toutes les lignes sont valides!')
      }

      erros.value = []
    } else {
      console.error('❌ Erreur lors du filtrage:', dataResponse)
      alert('Erreur lors du filtrage des données: ' + (dataResponse.error || 'Inconnu'))
    }
  } catch (error) {
    console.error('❌ Erreur lors de la validation:', error)
    alert('Erreur lors de la validation des données: ' + error.message)
  } finally {
    loading.value = false
  }
}

async function downloadFilteredOds() {
  if (!selectedFile.value || items.value.length === 0) return

  try {
    downloadingFile.value = true
    const tempFormData = new FormData()
    tempFormData.append('odsFile', selectedFile.value)
    tempFormData.append('sheetName', 'XX) Onglet technique - TOTAL')

    // Add validations if they exist
    if (validationRules.value.length > 0) {
      tempFormData.append('validations', JSON.stringify(validationRules.value))
    }

    const response = await fetch('http://localhost:8080/api/ods/download-filtered', {
      method: 'POST',
      body: tempFormData,
    })

    if (!response.ok) {
      const error = await response.json()
      alert('Erreur: ' + (error.error || 'Impossible de télécharger le fichier'))
      return
    }

    // Extract file name from Content-Disposition header
    const contentDisposition = response.headers.get('content-disposition')
    let filename = 'donnees_filtrees.ods'
    if (contentDisposition) {
      const match = contentDisposition.match(/filename[^;=\n]*=(["\']?)([^"\';]*)\1/i)
      if (match && match[2]) {
        filename = match[2]
      }
    }

    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)

  } catch (error) {
    console.error('Erreur lors du téléchargement:', error)
    alert('Erreur lors du téléchargement du fichier')
  } finally {
    downloadingFile.value = false
  }
}
</script>
