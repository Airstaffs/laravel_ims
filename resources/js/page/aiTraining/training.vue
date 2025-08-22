<template>
  <div class="p-6 space-y-8 bg-gray-900 text-white rounded-lg shadow-lg">
    <h1 class="text-2xl font-bold">🧪 Model Training</h1>

    <!-- Upload Section -->
    <div class="flex flex-col md:flex-row gap-4">
      <!-- 1. Dataset Upload -->
      <UploadDataset
        label="📁 Upload Dataset (Images or Labels)"
        accept=".jpg,.jpeg,.png,.txt"
        :multiple="true"
        @files-selected="handleDatasetFiles"
        class="md:w-1/2"
      />
      <!-- 2. Pretrained Model Upload -->
      <UploadDataset
        label="🧠 Upload Pretrained Model (.pt) (Optional)"
        accept=".pt"
        :multiple="false"
        @files-selected="handleModelFile"
        class="md:w-1/2"
      />
    </div>

    <!-- Configuration Section -->
    <div class="flex flex-col md:flex-row gap-6">
      <!-- 3. Model Config -->
      <div class="md:w-1/2 space-y-4">
        <h2 class="text-xl font-semibold">⚙️ Model Configuration</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <FormField label="Model Type (Fixed):">
            <input v-model="config.modelType" readonly class="input-disabled" />
          </FormField>
          <FormField label="Epochs:">
            <input v-model="config.epochs" type="number" class="input" />
          </FormField>
          <FormField label="Batch Size:">
            <input v-model="config.batchSize" type="number" class="input" />
          </FormField>
          <FormField label="Image Size:">
            <input v-model="config.imgSize" type="number" class="input" />
          </FormField>
        </div>
      </div>

      <!-- 4. Advanced Config -->
      <div class="md:w-1/2 space-y-4">
        <div class="flex justify-between items-center">
          <h2 class="text-xl font-semibold">🛠️ Advanced Config</h2>
          <label class="flex items-center space-x-2 text-sm text-gray-300">
            <span>Show</span>
            <input type="checkbox" v-model="showAdvancedConfig" class="form-checkbox text-blue-500" />
          </label>
        </div>
        <div v-if="showAdvancedConfig" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <FormField label="Optimizer:">
            <input v-model="config.optimizer" placeholder="e.g., SGD" class="input" />
          </FormField>
          <FormField label="Momentum:">
            <input v-model="config.momentum" type="number" step="0.1" class="input" />
          </FormField>
          <FormField label="Warmup Epochs:">
            <input v-model="config.warmupEpochs" type="number" class="input" />
          </FormField>
        </div>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex space-x-4">
      <button @click="startTraining" class="btn-primary">🚀 Start Training</button>
      <button @click="cancelTraining" class="btn-danger">🛑 Cancel</button>
    </div>

    <hr class="my-6 border-gray-700" />

    <!-- Training & Logs -->
    <div class="flex flex-col md:flex-row gap-4">
      <!-- Chart -->
      <div class="md:w-1/3">
        <Recharts />
      </div>

      <!-- Logs + Info -->
      <div class="md:w-2/3 space-y-4">
        <div>
          <h2 class="text-xl font-semibold">📜 Live Logs</h2>
          <div class="bg-black p-3 h-40 overflow-y-auto rounded text-green-400 text-sm font-mono whitespace-pre">
            <div v-for="(log, index) in logs" :key="index">{{ log }}</div>
          </div>
        </div>
        <div>
            <!-- Progress & Status -->
            <div v-if="status.running || status.finished" class="space-y-2">
            <div class="w-full bg-gray-700 rounded-full h-4">
                <div class="bg-blue-500 h-4 rounded-full transition-all" :style="{ width: `${status.epoch / config.epochs * 100}%` }"></div>
            </div>
            <div v-if="status.running" class="text-yellow-400 text-sm">
                <p>⏳ Epoch: {{ status.epoch }} / {{ config.epochs }}</p>
                <p>🎯 Accuracy: {{ status.accuracy }}%</p>
                <p>📉 Loss: {{ status.loss }}</p>
            </div>
            <div v-else-if="status.finished" class="text-green-400 text-sm">
                <p>✅ Training complete!</p>
                <p>🎯 Final Accuracy: {{ status.accuracy }}%</p>
                <p>📉 Final Loss: {{ status.loss }}</p>
            </div>
            </div>
            <!-- Download & Retrain -->
            <div v-if="status.finished" class="mt-4 gap-2 flex">
            <a :href="trainedModelUrl" class="text-blue-400 p-2 rounded bg-[#2563eb] text-white hover:bg-[#1d4ed8]">⬇️ Download Trained Model (.pt)</a>
            <button @click="simulateTrainingLog" class="btn-secondary mt-0">🔁 Retrain</button>
            </div>
        </div>
      </div>
    </div>
  </div>
</template>

<!-- ✅ Composition API Setup -->
<script setup>
import { ref } from 'vue'
import UploadDataset from './components/UploadDataset.vue'
import Recharts from './components/Recharts.vue'

const datasetFiles = ref([])
const modelFile = ref(null)
const logs = ref(['Waiting for training to start...'])
const showAdvancedConfig = ref(false)
const trainingDone = ref(false)
const trainedModelUrl = ref('#')

const config = ref({
  modelType: 'yolov8n',
  epochs: 50,
  batchSize: 16,
  imgSize: 640,
  optimizer: 'SGD',
  momentum: 0.9,
  warmupEpochs: 3,
})

const status = ref({
  running: false,
  finished: false,
  epoch: 0,
  accuracy: 0,
  loss: 0
})

function handleDatasetFiles(files) {
  datasetFiles.value = files
}

function handleModelFile(files) {
  modelFile.value = files[0]
}

function startTraining() {
  logs.value.push('[INFO] Training started...')
  status.value.running = true
  status.value.finished = false

  let progress = 0
  const interval = setInterval(() => {
    if (progress >= config.value.epochs) {
      logs.value.push('[INFO] Training completed.')
      status.value.running = false
      status.value.finished = true
      trainedModelUrl.value = '/models/trained_model.pt'
      clearInterval(interval)
      return
    }
    progress++
    status.value.epoch = progress
    status.value.accuracy = (Math.random() * 100).toFixed(2)
    status.value.loss = (Math.random() * 1.5).toFixed(3)
    logs.value.push(`[Epoch ${progress}] Accuracy: ${status.value.accuracy}% | Loss: ${status.value.loss}`)
  }, 500)
}

function cancelTraining() {
  logs.value.push('[INFO] Training cancelled by user.')
  status.value.running = false
}

function simulateTrainingLog() {
  logs.value = ['[INFO] Retaining with same configuration...']
  status.value.epoch = 0
  status.value.finished = false
  startTraining()
}
</script>

<!-- ✅ Scoped styles for inputs, buttons -->
<style scoped>
.input {
  @apply p-2 rounded bg-gray-800 text-white border border-gray-600;
}
.input-disabled {
  @apply input opacity-60 cursor-not-allowed;
}
.btn-primary {
  @apply px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded;
}
.btn-danger {
  @apply px-4 py-2 bg-red-600 hover:bg-red-700 rounded;
}
.btn-secondary {
  @apply px-4 py-2 bg-yellow-600 hover:bg-yellow-500 text-white rounded;
}
</style>

<!-- ✅ Basic slot-based wrapper -->
<script>
export default {
  components: {
    FormField: {
      props: ['label'],
      template: `
        <div class="flex flex-col">
          <label class="text-sm text-gray-400 mb-1">{{ label }}</label>
          <slot></slot>
        </div>
      `
    }
  }
}
</script>
