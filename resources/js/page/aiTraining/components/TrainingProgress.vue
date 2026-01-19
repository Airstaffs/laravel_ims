<template>
  <div class="flex flex-col md:flex-row gap-4 mt-6">
    <!-- 📊 Training Progress -->
    <div v-if="status.finished" class="md:w-1/2 space-y-2">
      <h2 class="text-xl font-semibold text-white">📊 Training Progress</h2>
      <div class="p-4 bg-gray-800 rounded-lg border border-gray-700 shadow-md training-status">
        <Line
          :key="chartKey"
          :data="chartData"
          :options="options"
        />
      </div>
    </div>

    <!-- 🧮 Training Results -->
    <div class="md:w-1/2 training-results-section">
      <div v-if="status.finished && resultImages.length" class="space-y-2">
        <h2 class="text-xl font-semibold text-white">🧮 Training Results</h2>

        <!-- Thumbnail -->
        <div
          class="cursor-pointer bg-gray-800 border border-gray-700 rounded-lg p-6 text-center hover:bg-gray-700 transition training-status"
          @click="openImage(resultImages[0])"
        >
          <img
            :src="resultImages[0]"
            class="mx-auto w-32 h-32 object-contain rounded border border-gray-600 mb-2"
          />
          <p class="text-lg font-semibold text-white">🖼️ View Training Results</p>
          <p class="text-sm text-gray-400">
            Click to open {{ resultImages.length }} images
          </p>
        </div>

        <!-- 🪟 Modal -->
        <div
          v-if="showImageModal"
          class="fixed inset-0 bg-black bg-opacity-80 flex items-center justify-center z-50"
          @click.self="showImageModal = false"
        >
          <button
            class="absolute left-8 text-white text-4xl"
            @click.stop="prevImage"
          >
            ‹
          </button>

          <img
            :src="selectedImage"
            class="max-w-4xl max-h-[90vh] rounded border border-gray-600"
          />

          <button
            class="absolute right-8 text-white text-4xl"
            @click.stop="nextImage"
          >
            ›
          </button>

          <p class="absolute bottom-6 text-sm text-gray-300">
            {{ currentIndex + 1 }} / {{ resultImages.length }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import axios from 'axios'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  LineElement,
  PointElement,
  CategoryScale,
  LinearScale,
  Title,
  Tooltip,
  Legend
} from 'chart.js'
import useTraining from '../scripts/training-script.js'

ChartJS.register(
  LineElement,
  PointElement,
  CategoryScale,
  LinearScale,
  Title,
  Tooltip,
  Legend
)

const API_BASE = '/api/training'
const training = useTraining()
const status = training.status

// ✅ SAFE resultImages
const resultImages = computed(() => training.resultImages?.value || [])

// 🔑 FORCE chart re-render
const chartKey = ref(0)

// 📈 Chart data (empty initially)
const chartData = ref({
  labels: [],
  datasets: []
})

const options = {
  responsive: true,
  maintainAspectRatio: false,
  scales: {
    x: { ticks: { color: '#9ca3af' }, grid: { color: '#374151' } },
    y: { ticks: { color: '#9ca3af' }, grid: { color: '#374151' } }
  },
  plugins: {
    legend: { labels: { color: '#f3f4f6' } },
    tooltip: { mode: 'index', intersect: false }
  }
}

// 📊 Load metrics from results.csv
async function loadMetrics() {
  try {
    const model = training.config.value.modelName
    if (!model) return

    const res = await axios.get(`${API_BASE}/training-metrics/${model}`)

    // ✅ REPLACE entire object (IMPORTANT)
    chartData.value = {
      labels: res.data.epochs.map(e => `Epoch ${e}`),
      datasets: [
        {
          label: 'Training Loss',
          borderColor: '#f87171',
          backgroundColor: 'rgba(248,113,113,0.2)',
          tension: 0.3,
          data: res.data.train_loss
        },
        {
          label: 'Validation Loss',
          borderColor: '#60a5fa',
          backgroundColor: 'rgba(96,165,250,0.2)',
          tension: 0.3,
          data: res.data.val_loss
        }
      ]
    }

    // 🔁 FORCE redraw
    chartKey.value++
  } catch (err) {
    console.error('[TrainingProgress] Metrics load failed', err)
  }
}

// 🔁 Load when training finishes
watch(
  () => status.finished,
  finished => {
    if (finished) loadMetrics()
  },
  { immediate: true }
)

// 🖼️ Modal logic (unchanged)
const showImageModal = ref(false)
const selectedImage = ref(null)
const currentIndex = ref(0)

function openImage(img) {
  selectedImage.value = img
  currentIndex.value = resultImages.value.indexOf(img)
  showImageModal.value = true
}

function nextImage() {
  currentIndex.value =
    (currentIndex.value + 1) % resultImages.value.length
  selectedImage.value = resultImages.value[currentIndex.value]
}

function prevImage() {
  currentIndex.value =
    (currentIndex.value - 1 + resultImages.value.length) %
    resultImages.value.length
  selectedImage.value = resultImages.value[currentIndex.value]
}
</script>
  
<style scoped>
.training-status {
  height: 300px;
}
.training-results-section {
  position: relative;
}
</style>
