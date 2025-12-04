// training-script.js
import { ref, nextTick, watch, onMounted } from 'vue'
import axios from 'axios'

// 🧠 Shared reactive state (singleton across all components)
const logs = ref(['Waiting for dataset upload...'])
const uploading = ref(false)
const uploadProgress = ref(0)
const logContainer = ref(null)

const config = ref({
  modelType: 'YOLOv8-cls',
  epochs: 30,
  split: 80,
  modelName: 'asin_model',
  autoReplace: true,
  useGPU: true,
})

const showAdvancedConfig = ref(false)

const status = ref({
  started: false,
  finished: false,
  canceled: false
})

const trainingActive = ref(false)
const autoScroll = ref(true)
const datasetClasses = ref([])
const resultImages = ref([])
const confusionMatrixUrl = ref(null)

let eventSource = null

const SITE_URL = window.location.origin.includes('localhost')
  ? 'http://localhost:8001'
  : 'https://test.techniquyality.com'

// 🗂️ Dataset classes
async function fetchClassFolders() {
  try {
    const res = await axios.get(`${SITE_URL}/api/class-folders`)
    datasetClasses.value = res.data.classes || []
  } catch (err) {
    console.error('[ERROR] Failed to fetch class folders:', err)
  }
}

fetchClassFolders()

// 📁 Handle dataset upload
async function handleDatasetFiles(files) {
  const file = files[0]
  if (!file) return

  logs.value.push(`[UPLOAD] Preparing ${file.name}...`)
  await fetchClassFolders()
  const formData = new FormData()
  formData.append('dataset', file)
  formData.append('split', config.value.split)

  try {
    uploading.value = true
    uploadProgress.value = 0

    const res = await axios.post(`${SITE_URL}/api/upload-dataset`, formData, {
      onUploadProgress: (e) => {
        if (e.total) uploadProgress.value = Math.round((e.loaded * 100) / e.total)
      },
    })

    logs.value.push(`[SUCCESS] ${res.data.message || 'Dataset uploaded successfully.'}`)
    await fetchClassFolders()
  } catch (err) {
    logs.value.push(`[ERROR] Upload failed: ${err.message}`)
    console.error(err)
  } finally {
    uploading.value = false
  }
}

// 🚀 Start training
function startTraining() {
  logs.value.push(`[TRAIN] Starting training...`)
  trainingActive.value = true
  autoScroll.value = true
  status.value.started = true
  status.value.finished = false
  status.value.canceled = false

  window.dispatchEvent(new CustomEvent('trainingMetricsReset'))

  const params = new URLSearchParams({
    epochs: config.value.epochs,
    model_name: config.value.modelName,
    auto_replace: config.value.autoReplace,
    use_gpu: config.value.useGPU,
  })

  eventSource = new EventSource(`${SITE_URL}/api/training-stream?${params.toString()}`)

  eventSource.onmessage = async (e) => {
    const line = e.data.trim()
    if (!line) return

    if (line.startsWith('[LIVE_METRICS]')) {
      try {
        const jsonPart = line.replace('[LIVE_METRICS]', '').trim()
        const data = JSON.parse(jsonPart)
        const { epoch, train_loss, val_loss } = data
        window.dispatchEvent(new CustomEvent('trainingMetricsLive', {
          detail: { epoch, trainLoss: train_loss, valLoss: val_loss },
        }))
      } catch (err) {
        console.error('Error parsing live metrics:', err)
      }
      return
    }

    if (line === '[DONE]') {
      logs.value.push('✅ Training completed!')
      eventSource.close()
      trainingActive.value = false
      status.value.finished = true      // ✅ triggers TestModel.vue
      status.value.started = false
      fetchTrainingImages()
      const metrics = await fetchTrainingMetrics()
      if (metrics) {
        window.dispatchEvent(
          new CustomEvent('trainingMetricsLoaded', { detail: metrics })
        )
      }
      return
    }

    if (logs.value.at(-1) !== line) {
      logs.value.push(line)
    }
  }

  eventSource.onerror = (err) => {
    logs.value.push(`[ERROR] Stream closed or failed.`)
    console.error(err)
    eventSource.close()
    trainingActive.value = false
  }
}

function cancelTraining() {
  if (eventSource) {
    eventSource.close()
    logs.value.push('[CANCELLED] Training manually stopped.')
  }
  trainingActive.value = false
  status.value.finished = false
  status.value.started = false
  status.value.canceled = true
}

async function updateModel() {
  try {
    logs.value.push('🧠 Updating model on server...')
    const res = await axios.post(`${SITE_URL}/api/update-model`)
    const { message, version } = res.data || {}
    const msg = version
      ? `Model updated successfully to version v${version}`
      : message || 'Model updated successfully.'
    logs.value.push(`✅ ${msg}`)
    alert(msg)
  } catch (error) {
    console.error('Model update failed:', error)
    logs.value.push('❌ Model update failed: ' + error.message)
    alert('Model update failed: ' + error.message)
  }
}

// 🧩 Fetch results and metrics
async function fetchTrainingImages() {
  try {
    const res = await axios.get(
      `${SITE_URL}/api/training-images/${encodeURIComponent(config.value.modelName)}`
    )

    const files = res.data.images || []
    resultImages.value = files.map(file =>
      `${SITE_URL}/api/training-image/${encodeURIComponent(config.value.modelName)}/${encodeURIComponent(file)}`
    )

    const matrixFile = files.find(f => f.toLowerCase().includes('confusion')) || files[0]
    confusionMatrixUrl.value = matrixFile
      ? `${SITE_URL}/api/training-image/${encodeURIComponent(config.value.modelName)}/${encodeURIComponent(matrixFile)}`
      : null
  } catch (err) {
    console.error('Error fetching training images:', err)
  }
}

async function fetchTrainingMetrics() {
  try {
    const res = await axios.get(`${SITE_URL}/api/training-metrics/${config.value.modelName}`)
    return res.data
  } catch (err) {
    console.error('Error fetching training metrics:', err)
    return null
  }
}

// 🌀 Auto-scroll while training
watch(logs, async () => {
  await nextTick()
  const el = logContainer.value
  if (el && trainingActive.value && autoScroll.value) {
    requestAnimationFrame(() => {
      el.scrollTop = el.scrollHeight
    })
  }
}, { deep: true })

// ✅ Singleton export (shared state across app)
export default function useTraining() {
  return {
    logs,
    logContainer,
    uploading,
    uploadProgress,
    handleDatasetFiles,
    config,
    showAdvancedConfig,
    startTraining,
    cancelTraining,
    updateModel,
    trainingActive,
    status,
    autoScroll,
    confusionMatrixUrl,
    resultImages,
    datasetClasses,
    fetchClassFolders,
  }
}
