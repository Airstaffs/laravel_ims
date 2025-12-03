<template>
  <div class="flex flex-col md:flex-row gap-4 mt-6">
    <!-- 📊 Training Progress -->
    <div v-if="status.finished" class="md:w-1/2 space-y-2">
      <h2 class="text-xl font-semibold text-white">📊 Training Progress</h2>
      <div class="p-4 bg-gray-800 rounded-lg border border-gray-700 shadow-md training-status">
        <Line :data="chartData" :options="options" />
      </div>
    </div>

    <!-- 🧮 Training Results -->
    <div class="md:w-1/2 training-results-section">
      <div v-if="status.finished && resultImages.length" class="space-y-2">
        <h2 class="text-xl font-semibold text-white">🧮 Training Results</h2>

        <!-- Thumbnail Preview -->
        <div
          v-if="resultImages.length"
          class="cursor-pointer bg-gray-800 border border-gray-700 rounded-lg p-6 text-center hover:bg-gray-700 transition training-status"
          @click="openImage(resultImages[0])"
        >
          <img
            :src="resultImages[0]"
            alt="Preview"
            class="mx-auto w-32 h-32 object-contain rounded border border-gray-600 mb-2"
          />
          <p class="text-lg font-semibold text-white">🖼️ View Training Results</p>
          <p class="text-sm text-gray-400">Click to open {{ resultImages.length }} images</p>
        </div>

        <!-- 🪟 Modal Viewer -->
        <div
          v-if="showImageModal"
          class="fixed inset-0 bg-black bg-opacity-80 flex justify-center items-center z-50 select-none"
          @click.self="showImageModal = false"
        >
          <!-- ⬅️ Prev -->
          <button
            @click.stop="prevImage"
            class="absolute left-8 text-white text-4xl font-bold hover:text-blue-400 transition"
          >
            ‹
          </button>

          <!-- 🖼️ Image -->
          <img
            :src="selectedImage"
            class="max-w-4xl max-h-[90vh] rounded border border-gray-600 shadow-xl transition-all"
          />

          <!-- ➡️ Next -->
          <button
            @click.stop="nextImage"
            class="absolute right-8 text-white text-4xl font-bold hover:text-blue-400 transition"
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
import { ref, onMounted } from 'vue'
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

// 🧠 Shared state
const { status, resultImages } = useTraining()

// 📈 Chart.js setup
ChartJS.register(LineElement, PointElement, CategoryScale, LinearScale, Title, Tooltip, Legend)

const chartData = ref({
  labels: [],
  datasets: [
    {
      label: 'Training Loss',
      borderColor: '#f87171', // red-400
      backgroundColor: 'rgba(248,113,113,0.2)',
      tension: 0.3,
      data: []
    },
    {
      label: 'Validation Loss',
      borderColor: '#60a5fa', // blue-400
      backgroundColor: 'rgba(96,165,250,0.2)',
      tension: 0.3,
      data: []
    }
  ]
})

const options = {
  responsive: true,
  maintainAspectRatio: false,
  scales: {
    x: {
      ticks: { color: '#9ca3af' },
      grid: { color: '#374151' }
    },
    y: {
      ticks: { color: '#9ca3af' },
      grid: { color: '#374151' }
    }
  },
  plugins: {
    legend: { labels: { color: '#f3f4f6' } },
    tooltip: { mode: 'index', intersect: false }
  }
}

// 🧩 Update chart live
onMounted(() => {
  window.addEventListener('trainingMetricsLive', (e) => {
    const { epoch, trainLoss, valLoss } = e.detail
    chartData.value.labels.push(`Epoch ${epoch}`)
    chartData.value.datasets[0].data.push(trainLoss)
    chartData.value.datasets[1].data.push(valLoss)
  })

  // Reset metrics when new training starts
  window.addEventListener('trainingMetricsReset', () => {
    chartData.value.labels = []
    chartData.value.datasets[0].data = []
    chartData.value.datasets[1].data = []
  })
})

// 🖼️ Modal gallery logic
const showImageModal = ref(false)
const selectedImage = ref(null)
const currentIndex = ref(0)

function openImage(img) {
  selectedImage.value = img
  currentIndex.value = resultImages.value.indexOf(img)
  showImageModal.value = true
}

function nextImage() {
  if (!resultImages.value.length) return
  currentIndex.value = (currentIndex.value + 1) % resultImages.value.length
  selectedImage.value = resultImages.value[currentIndex.value]
}

function prevImage() {
  if (!resultImages.value.length) return
  currentIndex.value =
    (currentIndex.value - 1 + resultImages.value.length) % resultImages.value.length
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

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
