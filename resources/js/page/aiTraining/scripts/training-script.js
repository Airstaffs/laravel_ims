import { ref, nextTick, watch } from 'vue'
import axios from 'axios'

const API_BASE = '/api/training'

// ========================
// State
// ========================
const logs = ref(['Waiting for dataset upload...'])
const uploading = ref(false)
const uploadProgress = ref(0)
const logContainer = ref(null)

const showAdvancedConfig = ref(false)

const config = ref({
  modelType: 'YOLOv8-cls',   // fixed display only
  epochs: 20,
  split: 80,
  modelName: 'asin_classifier',
  autoReplace: true,
  useGPU: true,
})

const status = ref({
  started: false,
  finished: false,
  canceled: false
})

const trainingActive = ref(false)
const autoScroll = ref(true)
const datasetClasses = ref([])

let eventSource = null

// ========================
// Load dataset classes
// ========================
async function fetchClassFolders() {
  try {
    const res = await axios.get(`${API_BASE}/class-folders`)
    datasetClasses.value = res.data?.classes || []
  } catch (err) {
    console.error('[ERROR] Failed to load class folders:', err)
  }
}

// ========================
// Upload dataset ZIP
// ========================
async function uploadDataset(file, split = 80) {
  const formData = new FormData()
  formData.append('dataset', file)
  formData.append('split', split)

  try {
    uploading.value = true
    uploadProgress.value = 0

    const res = await axios.post(
      `${API_BASE}/upload-dataset`,
      formData,
      {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress(e) {
          if (e.total) {
            uploadProgress.value = Math.round((e.loaded * 100) / e.total)
          }
        }
      }
    )

    logs.value.push('📁 Dataset uploaded')
    return res.data
  } catch (err) {
    logs.value.push('❌ Upload failed')
    console.error(err)
  } finally {
    uploading.value = false
  }
}

// ========================
// Start training (SSE)
// ========================
function startTraining() {
  logs.value.push('🚀 Training started')
  trainingActive.value = true

  axios.post(`${API_BASE}/start-training`, {
      model_type: config.value.modelType,
      epochs: config.value.epochs,
      split: config.value.split,
      model_name: config.value.modelName,
      auto_replace: config.value.autoReplace,
      use_gpu: config.value.useGPU,
    })


  eventSource = new EventSource(`${API_BASE}/training-stream`)

  eventSource.onmessage = (e) => {
    logs.value.push(e.data)

    if (e.data.includes('[DONE]')) {
      trainingActive.value = false
      status.value.finished = true
      eventSource.close()
    }
  }

  eventSource.onerror = () => {
    logs.value.push('❌ Training stream closed')
    trainingActive.value = false
    eventSource.close()
  }
}

// ========================
// Auto-scroll logs
// ========================
watch(logs, async () => {
  await nextTick()
  if (autoScroll.value && logContainer.value) {
    logContainer.value.scrollTop = logContainer.value.scrollHeight
  }
})

// ========================
// Handle dataset files from UploadDataset.vue
// ========================
async function handleDatasetFiles(files) {
  if (!files || !files.length) return

  const file = files[0]

  logs.value.push(`📦 Uploading ${file.name}...`)
  await uploadDataset(file)

  // Refresh class folders after upload
  await fetchClassFolders()
}

export default function useTraining() {
  return {
    logs,
    uploading,
    uploadProgress,
    logContainer,
    config,
    showAdvancedConfig,
    status,
    trainingActive,
    autoScroll,
    datasetClasses,

    fetchClassFolders,
    uploadDataset,
    handleDatasetFiles,
    startTraining,
  }
}
