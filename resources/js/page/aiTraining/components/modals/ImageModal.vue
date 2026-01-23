<template>
  <div class="fixed inset-0 bg-black bg-opacity-60 flex justify-center items-center z-50">
    <div
      class="bg-gray-900 p-6 rounded-lg w-11/12 md:w-3/4 max-h-[90vh] overflow-y-auto shadow-xl border border-gray-700"
    >
      <!-- Header -->
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold">
          🖼️ {{ dataset.name }} — {{ folderType.toUpperCase() }} Images
        </h2>
        <button class="text-gray-400 hover:text-white text-2xl" @click="$emit('close')">
          ×
        </button>
      </div>

      <!-- Actions Row -->
      <div class="flex justify-between items-center mb-4">
        <div class="text-sm text-gray-400">
          Selected: {{ selectedImages.length }} / {{ images.length }}
        </div>
        <div class="flex gap-2">
          <button
            class="bg-red-600 hover:bg-red-700 px-3 py-1 rounded disabled:opacity-50 transition"
            :disabled="!selectedImages.length"
            @click="deleteSelected"
          >
            🗑 Delete Selected
          </button>
          <label class="bg-indigo-600 hover:bg-indigo-700 px-3 py-1 rounded cursor-pointer transition">
            ➕ Add Images
            <input type="file" multiple class="hidden" @change="handleFileSelect" />
          </label>
        </div>
      </div>

      <!-- Image Grid -->
      <div
        class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-3 mb-6 select-none"
        @drop.prevent="handleDrop"
        @dragover.prevent
      >
        <div
          v-for="(img, i) in images"
          :key="i"
          class="relative group cursor-pointer border-2 rounded-lg overflow-hidden"
          :class="{
            'border-indigo-500': selectedImages.includes(i),
            'border-gray-700': !selectedImages.includes(i),
          }"
          @click="toggleSelect(i)"
        >
          <!-- ✅ FIXED IMAGE URL -->
          <img
            :src="API_BASE + img"
            class="object-cover w-full h-24 transition-opacity duration-200"
            :class="{ 'opacity-70': selectedImages.includes(i) }"
          />

          <button
            class="absolute top-1 right-1 bg-red-600 hover:bg-red-700 text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition"
            @click.stop="deleteImage(i)"
          >
            🗑
          </button>
        </div>

        <div v-if="!images.length" class="col-span-full text-center text-gray-500 italic">
          No images found in this folder.
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
  dataset: Object,
  folderType: String,
})

const API_BASE = '/api/training'

const images = ref([])
const selectedImages = ref([])

/* ✅ Encode class name ONCE */
const encodedClass = computed(() =>
  encodeURIComponent(props.dataset.name)
)

onMounted(fetchImages)

/* Reload when folder or dataset changes */
watch(
  () => [props.folderType, props.dataset?.name],
  fetchImages
)

async function fetchImages() {
  try {
    const res = await axios.get(
      `${API_BASE}/images/${props.folderType}/${encodedClass.value}`
    )
    images.value = res.data.images || []
  } catch (err) {
    console.error('[❌ ERROR] Failed to fetch images:', err)
    images.value = []
  }
}

function toggleSelect(index) {
  selectedImages.value.includes(index)
    ? (selectedImages.value = selectedImages.value.filter(i => i !== index))
    : selectedImages.value.push(index)
}

async function deleteImage(index) {
  const filePath = images.value[index]
  const fileName = filePath.split('/').pop()

  if (!confirm(`Delete image "${fileName}"?`)) return

  await axios.delete(
    `${API_BASE}/delete-image/${props.folderType}/${encodedClass.value}/${fileName}`
  )

  await fetchImages()
}

async function deleteSelected() {
  if (!selectedImages.value.length) return
  if (!confirm(`Delete ${selectedImages.value.length} selected images?`)) return

  for (const index of selectedImages.value) {
    const fileName = images.value[index].split('/').pop()
    await axios.delete(
      `${API_BASE}/delete-image/${props.folderType}/${encodedClass.value}/${fileName}`
    )
  }

  selectedImages.value = []
  await fetchImages()
}

async function handleFileSelect(e) {
  await uploadFiles(Array.from(e.target.files))
}

async function handleDrop(e) {
  await uploadFiles(Array.from(e.dataTransfer.files))
}

async function uploadFiles(files) {
  for (const file of files) {
    const formData = new FormData()
    formData.append('file', file)

    await axios.post(
      `${API_BASE}/upload-image/${props.folderType}/${encodedClass.value}`,
      formData
    )
  }

  await fetchImages()
}
</script>
