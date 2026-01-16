// training-script.js
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
  modelType: 'YOLOv8-cls',
  epochs: 20,
  split: 80,
  modelName: 'asin_classifier',
  autoReplace: true,
  useGPU: true,
})

const status = ref({
  started: false,
  finished: false,
  canceled: false,
})

const trainingActive = ref(false)
const autoScroll = ref(true)
const datasetClasses = ref([])

let eventSource = null
let reconnectTimer = null
let reconnectAttempts = 0
const MAX_RECONNECT = 30 // 30 tries * 3s = 90s window

function normalizeImageName(img) {
  if (!img) return ''
  return img.split('/').pop() // removes "/image_xxx.jpg"
}

function classImageUrl(className, fileName) {
  const cleanFile = normalizeImageName(fileName)

  return `${API_BASE}/class-image/${encodeURIComponent(className)}/${encodeURIComponent(cleanFile)}`
}

// ========================
// Helpers
// ========================
function pushLog(line) {
  if (!line) return
  logs.value.push(line)
}

function closeStream() {
  if (reconnectTimer) {
    clearTimeout(reconnectTimer)
    reconnectTimer = null
  }
  if (eventSource) {
    eventSource.close()
    eventSource = null
  }
}

function markFinished() {
  trainingActive.value = false
  status.value.started = false
  status.value.finished = true
  status.value.canceled = false
  pushLog('✅ Training finished')
  closeStream()
}

function markStopped(canceled = false) {
  trainingActive.value = false
  status.value.started = false
  status.value.finished = false
  status.value.canceled = canceled
  closeStream()
}

// Detect end of training from logs
function isTrainingDoneLine(line) {
  // ultralytics often prints these near the end
  return (
    line.includes('Results saved to') ||
    line.includes('epochs completed') ||
    line.includes('Validation') && line.includes('weights') ||
    line.includes('[DONE]')
  )
}

// ========================
// SSE Connect (with retry)
// ========================
function connectStream(modelName) {
  // guard
  if (!modelName) return

  closeStream()
  reconnectAttempts = 0

  const url = `${API_BASE}/training-stream?model_name=${encodeURIComponent(modelName)}`
  pushLog('📡 Training stream connected')

  const open = () => {
    eventSource = new EventSource(url)

    eventSource.onmessage = (e) => {
      const line = (e?.data ?? '').toString()

      // Filter any accidental HTML
      if (line.trim().startsWith('<!DOCTYPE') || line.trim().startsWith('<html')) {
        pushLog('⚠️ Stream proxy returned HTML; retrying...')
        eventSource?.close()
        scheduleReconnect(modelName)
        return
      }

      pushLog(line)

      if (isTrainingDoneLine(line)) {
        markFinished()
      }
    }

    eventSource.onerror = () => {
      // Do NOT treat as fatal. Reconnect.
      if (!trainingActive.value) {
        closeStream()
        return
      }

      pushLog('⚠️ Stream disconnected, retrying...')
      eventSource?.close()
      scheduleReconnect(modelName)
    }
  }

  const scheduleReconnect = (mn) => {
    if (reconnectAttempts >= MAX_RECONNECT) {
      pushLog('❌ Stream unstable too long. Training may still be running. Click "Start" again to reattach.')
      // keep trainingActive true? better to keep true so UI shows cancel
      // but stream is dead; user can refresh or reattach
      closeStream()
      return
    }

    reconnectAttempts += 1
    reconnectTimer = setTimeout(() => {
      if (trainingActive.value) open()
    }, 3000)
  }

  open()
}

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

    const res = await axios.post(`${API_BASE}/upload-dataset`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress(e) {
        if (e.total) {
          uploadProgress.value = Math.round((e.loaded * 100) / e.total)
        }
      },
    })

    pushLog('📁 Dataset uploaded')
    return res.data
  } catch (err) {
    pushLog('❌ Upload failed')
    console.error(err)
  } finally {
    uploading.value = false
  }
}

// ========================
// Start training
// ========================
async function startTraining() {
  if (trainingActive.value) return

  if (!datasetClasses.value.length) {
    pushLog('❌ No dataset found. Upload dataset first.')
    return
  }

  const modelName = config.value.modelName

  try {
    trainingActive.value = true
    status.value.started = true
    status.value.finished = false
    status.value.canceled = false

    pushLog('🚀 Training started')

    // Start training backend (Laravel -> FastAPI)
    await axios.post(`${API_BASE}/start-training`, {
      epochs: config.value.epochs,
      model_name: modelName,
      auto_replace: config.value.autoReplace,
      use_gpu: config.value.useGPU,
      // optional
      imgsz: 224,
      batch: 8,
    })

    // Attach SSE stream AFTER successful start
    connectStream(modelName)
  } catch (err) {
    console.error(err)
    pushLog('❌ Failed to start training')
    markStopped(false)
  }
}

// ========================
// Cancel training (optional endpoint)
// ========================
async function cancelTraining() {
  if (!trainingActive.value) return

  pushLog('❌ Training canceled by user')

  try {
    // If you don’t have this endpoint yet, it will just fail silently
    await axios.post(`${API_BASE}/cancel-training`, {
      model_name: config.value.modelName,
    })
  } catch (_) {}

  markStopped(true)
}

// ========================
// Update / Retrain
// ========================
async function updateModel() {
  pushLog('🧠 Updating model...')

  await axios.post(`${API_BASE}/update-model`, {
    model_name: config.value.modelName,
  })

  pushLog('✅ Model updated')
}

function retrainModel() {
  pushLog('🔁 Retraining started')
  status.value.finished = false
  startTraining()
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
// Handle dataset files
// ========================
async function handleDatasetFiles(files) {
  if (!files || !files.length) return
  const file = files[0]
  pushLog(`📦 Uploading ${file.name}...`)

  await uploadDataset(file, config.value.split)
  await fetchClassFolders()
}

// ========================
// Export composable
// ========================
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
    cancelTraining,
    updateModel,
    retrainModel,
    classImageUrl,
  }
}
