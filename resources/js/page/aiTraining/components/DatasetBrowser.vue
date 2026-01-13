<template>
  <div class="flex flex-col md:flex-row gap-6">
    <!-- 🗂️ Dataset Class Browser -->
    <div class="flex-1">
      <h2 class="text-xl font-semibold text-white mb-4">📁 Dataset Classes</h2>

      <div v-if="datasetClasses.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
          v-for="(cls, index) in datasetClasses"
          :key="index"
          class="bg-gray-800 border border-gray-700 rounded-lg p-4 hover:bg-gray-700 transition cursor-pointer"
          @click="openClassModal(cls)"
        >
          <p class="font-semibold text-gray-200 mb-2 truncate">{{ cls.className }}</p>

          <div class="flex flex-wrap gap-2">
            <img
              v-for="(img, i) in cls.images.slice(0, 4)"
              :key="i"
              :src="img"
              class="w-20 h-20 object-cover rounded border border-gray-600"
            />
          </div>
        </div>
      </div>

      <div v-else class="text-gray-400 italic text-sm mt-4">
        Loading dataset classes...
      </div>
    </div>

    <!-- 🪟 Modal for viewing images inside a class -->
    <div
      v-if="showClassModal"
      class="fixed inset-0 bg-black bg-opacity-80 flex justify-center items-center z-50"
      @click.self="showClassModal = false"
    >
      <!-- Prev -->
      <button
        @click.stop="prevClassImage"
        class="absolute left-8 text-white text-4xl font-bold hover:text-blue-400"
      >
        ‹
      </button>

      <img
        :src="selectedClassImages[classCurrentIndex]"
        class="max-h-[85vh] rounded border border-gray-600 shadow-lg transition"
      />

      <!-- Next -->
      <button
        @click.stop="nextClassImage"
        class="absolute right-8 text-white text-4xl font-bold hover:text-blue-400"
      >
        ›
      </button>

      <p class="absolute bottom-6 text-gray-300 text-sm">
        {{ classCurrentIndex + 1 }} / {{ selectedClassImages.length }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import useTraining from '../scripts/training-script.js'

const { datasetClasses, fetchClassFolders } = useTraining()
// const SITE_URL = window.location.origin

// 🪟 Modal state
const showClassModal = ref(false)
const selectedClassImages = ref([])
const classCurrentIndex = ref(0)

// 🔥 THIS IS THE MISSING PIECE
onMounted(() => {
  fetchClassFolders()
})

function openClassModal(cls) {
  selectedClassImages.value = cls.images
  classCurrentIndex.value = 0
  showClassModal.value = true
}

function nextClassImage() {
  classCurrentIndex.value =
    (classCurrentIndex.value + 1) % selectedClassImages.value.length
}

function prevClassImage() {
  classCurrentIndex.value =
    (classCurrentIndex.value - 1 + selectedClassImages.value.length) %
    selectedClassImages.value.length
}
</script>

<style scoped>
/* Optional smooth modal fade-in */
.fixed {
  animation: fadeIn 0.2s ease-in-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}
</style>
