// training-script.js
import { ref } from 'vue'
import axios from 'axios'

export default function useTraining() {
  // 🧠 Reactive states
  const logs = ref(['Waiting for dataset upload...'])
  const uploading = ref(false)
  const uploadProgress = ref(0)

  // 🧩 Configuration state
  const config = ref({
    modelType: 'YOLOv8-cls',  // fixed for now
    epochs: 30,
    split: 80,
    modelName: 'asin_model',
    autoReplace: true,
    useGPU: true,
  })
  const showAdvancedConfig = ref(false)

  const SITE_URL = window.location.origin.includes('localhost')
    ? 'http://localhost:8001'
    : 'https://test.techniquyality.com'

  // 📁 Handle upload
  async function handleDatasetFiles(files) {
    const file = files[0]
    if (!file) return

    logs.value.push(`[UPLOAD] Preparing ${file.name}...`)
    const formData = new FormData()
    formData.append('dataset', file)
    formData.append('split', config.value.split) // ✅ dynamic split value

    try {
      uploading.value = true
      uploadProgress.value = 0

      const res = await axios.post(`${SITE_URL}/api/upload-dataset`, formData, {
        onUploadProgress: (e) => {
          if (e.total) uploadProgress.value = Math.round((e.loaded * 100) / e.total)
        },
      })

      logs.value.push(`[SUCCESS] ${res.data.message || 'Dataset uploaded successfully.'}`)
    } catch (err) {
      logs.value.push(`[ERROR] Upload failed: ${err.message}`)
      console.error(err)
    } finally {
      uploading.value = false
    }
  }

  return {
    logs,
    uploading,
    uploadProgress,
    handleDatasetFiles,
    config,
    showAdvancedConfig
  }
}
