// training-script.js
import { ref, reactive, nextTick, watch } from 'vue'
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

const status = reactive({
  started: false,
  finished: false,
  canceled: false,
})

const updatingModel = ref(false)

const trainingActive = ref(false)
const autoScroll = ref(true)
const datasetClasses = ref([])

// ✅ training results images (shown in TrainingProgress.vue)
const resultImages = ref([])

let eventSource = null
let reconnectTimer = null
let reconnectAttempts = 0
const MAX_RECONNECT = 30 // 30 tries * 3s = 90s window

function normalizeImageName(img) {
  if (!img) return ''
  return img.split('/').pop()
}

function classImageUrl(className, fileName) {
  const cleanFile = normalizeImageName(fileName)
  return `${API_BASE}/class-image/${encodeURIComponent(className)}/${encodeURIComponent(cleanFile)}`
}

// ========================
// Training Results (images)
// ========================
async function fetchTrainingImages(modelNameOverride = null) {
  const model = modelNameOverride || config.value.modelName
  if (!model) return

  // ✅ avoid showing old images while fetching
  resultImages.value = []

  try {
    const res = await axios.get(`${API_BASE}/training-images/${encodeURIComponent(model)}`)

    const imgs = Array.isArray(res.data?.images) ? res.data.images : []
    resultImages.value = imgs.map(
      (img) => `${API_BASE}/training-image/${encodeURIComponent(model)}/${encodeURIComponent(img)}`
    )
  } catch (err) {
    console.error('[Training Images]', err)
    resultImages.value = []
  }
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

async function markFinished() {
  trainingActive.value = false
  status.started = false
  status.finished = true
  status.canceled = false

  pushLog('✅ Training finished')

  // ✅ load result images after finished
  await fetchTrainingImages(config.value.modelName)

  reconnectAttempts = 0
  closeStream()
}

function markStopped(canceled = false) {
  trainingActive.value = false
  status.started = false
  status.finished = false
  status.canceled = canceled

  // ✅ if cancel/restart, clear results so old results don't show
  if (canceled) resultImages.value = []

  closeStream()
}

// Detect end of training from logs
function isTrainingDoneLine(line) {
  const text = (line || '').toLowerCase()
  return (
    text.includes('training finished') ||
    text.includes('epochs completed') ||
    text.includes('results saved to') ||
    text.includes('[done]')
  )
}

// ========================
// SSE Connect (with retry)
// ========================
function connectStream(modelName) {
  if (!modelName) return

  closeStream()
  reconnectAttempts = 0

  const url = `${API_BASE}/training-stream?model_name=${encodeURIComponent(modelName)}`
  pushLog('📡 Training stream connected')

  const open = () => {
    eventSource = new EventSource(url)

    eventSource.onmessage = (e) => {
      const line = (e?.data ?? '').toString()

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
      // finished already
      if (status.finished || status.canceled) {
        closeStream()
        return
      }

      // retry if training still active
      if (trainingActive.value) {
        pushLog('⚠️ Stream disconnected, retrying...')
        eventSource?.close()
        scheduleReconnect(modelName)
        return
      }

      closeStream()
    }
  }

  const scheduleReconnect = () => {
    if (reconnectAttempts >= MAX_RECONNECT) {
      pushLog('❌ Stream unstable too long. Training may still be running. Click "Start" again to reattach.')
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
        if (e.total) uploadProgress.value = Math.round((e.loaded * 100) / e.total)
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
  if (!modelName) {
    pushLog('❌ Model name missing')
    return
  }

  try {
    // reset outputs for a clean run
    resultImages.value = []
    reconnectAttempts = 0

    trainingActive.value = true
    status.started = true
    status.finished = false
    status.canceled = false

    pushLog('🚀 Training started')

    await axios.post(`${API_BASE}/start-training`, {
      epochs: config.value.epochs,
      model_name: modelName,
      auto_replace: config.value.autoReplace,
      use_gpu: config.value.useGPU,
      imgsz: 224,
      batch: 8,
    })

    connectStream(modelName)
  } catch (err) {
    console.error(err)
    pushLog('❌ Failed to start training')
    markStopped(false)
  }
}

// ========================
// Cancel training
// ========================
async function cancelTraining() {
  if (!trainingActive.value) return

  pushLog('❌ Training canceled by user')

  // ✅ use API_BASE so local/live match
  axios.post(`${API_BASE}/cancel-training`, {
    model_name: config.value.modelName,
  }).catch(() => {})

  markStopped(true)
}

// ========================
// Update / Retrain
// ========================
async function updateModel() {
  if (updatingModel?.value) return

  pushLog('🧠 Uploading and deploying model...')

  try {
    const formData = new FormData()

    // LOCAL training_server best.pt path
    const res = await axios.get(
      '/api/training/get-latest-model',
      { responseType: 'blob' }
    )

    formData.append('file', res.data, 'best.pt')

    const deployRes = await axios.post(
      `${API_BASE}/update-model`,
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } }
    )

    if (deployRes.data?.status !== 'success') {
      throw new Error(deployRes.data?.message || 'Deployment failed')
    }

    pushLog('✅ Model uploaded and deployed')

  } catch (err) {
    console.error(err)
    pushLog(`❌ Update model failed: ${err.message}`)
  }
}

async function retrainModel() {
  logs.value = []
  reconnectAttempts = 0

  status.started = false
  status.finished = false
  status.canceled = false

  resultImages.value = []

  await nextTick()
  pushLog('🔁 Retraining started')
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
// Singleton composable
// ========================
let _store = null

export default function useTraining() {
  if (_store) return _store

  _store = {
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
    resultImages,
    updatingModel,

    fetchClassFolders,
    uploadDataset,
    handleDatasetFiles,

    startTraining,
    cancelTraining,
    updateModel,
    retrainModel,
    classImageUrl,

    // optional export in case component wants manual reload
    fetchTrainingImages,
  }

  return _store
}
