// training-script.js
import { ref, nextTick, watch } from 'vue'
import axios from 'axios'

// ========================
// 🔗 BACKEND URLS
// ========================

// LOCAL (class folder browser)
const LOCAL_URL = 'http://localhost:8001'

// REMOTE — your active Colab/ngrok backend
const REMOTE_URL = 'https://overenvious-jenine-realizable.ngrok-free.dev'

// ========================
// ✔ Reactive state
// ========================
const logs = ref(['Waiting for dataset upload...'])
const uploading = ref(false)
const uploadProgress = ref(0)
const logContainer = ref(null)

const config = ref({
  epochs: 20,
  modelName: 'asin_classifier',
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
// 📁 LOAD CLASSES FROM LOCAL BACKEND
// ========================
async function fetchClassFolders() {
  try {
    const res = await axios.get(`${LOCAL_URL}/api/class-folders`)
    datasetClasses.value = res.data.classes || []
  } catch (err) {
    console.error('[ERROR] Failed to load class folders:', err)
  }
}

fetchClassFolders()

// ========================
// 📤 UPLOAD SINGLE IMAGE TO REMOTE FASTAPI
// ========================
async function uploadImageToTrainingServer(file, className) {
  const formData = new FormData()
  formData.append('file', file)
  formData.append('class_name', className)

  try {
    uploading.value = true
    uploadProgress.value = 0

    const res = await axios.post(`${REMOTE_URL}/upload`, formData, {
      headers: {
        "Content-Type": "multipart/form-data",
        "ngrok-skip-browser-warning": "69420"
      },
      onUploadProgress(e) {
        if (e.total) {
          uploadProgress.value = Math.round((e.loaded * 100) / e.total)
        }
      }
    })

    logs.value.push(`📁 Uploaded → ${res.data.stored_at}`)
  } catch (err) {
    console.error(err)
    logs.value.push(`❌ Upload failed: ${err.message}`)
  } finally {
    uploading.value = false
  }
}

// ========================
// 🚀 START TRAINING (LIVE STREAM)
// ========================
function startTraining() {
  logs.value.push('🚀 Training started...')
  trainingActive.value = true
  status.value.started = true
  status.value.finished = false
  status.value.canceled = false

  // Build streaming URL
  const url = `${REMOTE_URL}/train`

  // SSE stream
  eventSource = new EventSource(url, {
    withCredentials: false
  })

  eventSource.onmessage = (event) => {
    const line = event.data.trim()
    if (!line) return

    logs.value.push(line)

    if (line.includes('completed') || line.includes('[DONE]')) {
      logs.value.push('🎉 Training finished!')
      eventSource.close()
      trainingActive.value = false
      status.value.finished = true
    }
  }

  eventSource.onerror = (err) => {
    console.error('[STREAM ERROR]', err)
    logs.value.push('❌ Stream error or closed')
    eventSource.close()
    trainingActive.value = false
  }
}

// ========================
// 🧪 PREDICT
// ========================
async function predictImage(file) {
  const formData = new FormData()
  formData.append('file', file)

  try {
    const res = await axios.post(`${REMOTE_URL}/predict`, formData, {
      headers: { "Content-Type": "multipart/form-data" }
    })

    return res.data  // {class: "...", confidence: 0.xx}
  } catch (err) {
    console.error(err)
    logs.value.push('❌ Prediction failed.')
    return null
  }
}

// ========================
// 🛑 CANCEL TRAINING
// ========================
function cancelTraining() {
  if (eventSource) eventSource.close()
  logs.value.push('⛔ Training canceled')
  trainingActive.value = false
  status.value.canceled = true
}

// ========================
// 🌀 AUTO SCROLL LOGS
// ========================
watch(logs, async () => {
  await nextTick()
  if (!autoScroll.value || !logContainer.value) return
  logContainer.value.scrollTop = logContainer.value.scrollHeight
})

// ========================
// EXPORT COMPOSABLE
// ========================
export default function useTraining() {
  return {
    logs,
    uploading,
    uploadProgress,
    logContainer,
    config,
    status,
    trainingActive,
    autoScroll,
    datasetClasses,

    fetchClassFolders,
    uploadImageToTrainingServer,
    startTraining,
    cancelTraining,
    predictImage,
  }
}
