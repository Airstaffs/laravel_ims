<template>
  <div class="fixed inset-0 bg-black bg-opacity-60 flex justify-center items-center z-50">
    <div class="bg-gray-900 p-6 rounded-lg w-11/12 md:w-3/4 shadow-xl border border-gray-700">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold">📦 Add Bulk Images — {{ dataset.name }}</h2>
        <button class="text-gray-400 hover:text-white text-2xl" @click="$emit('close')">×</button>
      </div>

      <!-- Upload Section -->
      <UploadDataset
        label="📁 Drag & drop ZIP or images"
        accept=".zip,image/*"
        :multiple="true"
        @files-selected="handleFiles"
      />

      <!-- Uploaded File Previews -->
      <div class="mt-4">
        <h3 class="text-lg font-semibold text-gray-200 mb-2">Uploaded Files:</h3>

        <!-- If no files yet -->
        <div v-if="!files.length" class="text-gray-500 text-sm">
          No files selected yet.
        </div>

        <!-- Preview Grid -->
        <div v-else class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
          <div
            v-for="(file, index) in files"
            :key="index"
            class="bg-gray-800 border border-gray-700 rounded-lg p-2 flex flex-col items-center"
          >
            <!-- If ZIP file -->
            <div v-if="file.name.toLowerCase().endsWith('.zip')" class="flex flex-col items-center text-center">
              <div class="text-4xl">📦</div>
              <p class="text-gray-300 text-sm truncate w-full">{{ file.name }}</p>
            </div>

            <!-- If image file -->
            <div v-else class="flex flex-col items-center">
              <img
                :src="file.preview"
                class="w-24 h-24 object-cover rounded-md border border-gray-600 mb-1"
                alt="preview"
              />
              <p class="text-gray-400 text-xs truncate w-24 text-center">{{ file.name }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Split Input -->
      <div class="mt-6">
        <label class="text-sm mr-2 text-gray-300">Train/Val Split (%):</label>
        <input
          v-model.number="split"
          type="number"
          min="50"
          max="100"
          class="mt-1 w-24 p-2 bg-gray-800 border border-gray-700 rounded text-center text-white"
        />
      </div>

      <!-- Upload button -->
      <div class="flex justify-end mt-6">
        <button class="bg-indigo-600 hover:bg-indigo-700 px-4 py-2 rounded"
                @click="uploadBulk">🚀 Upload</button>
      </div>

      <div v-if="progress > 0" class="mt-3 text-gray-400">Uploading... {{ progress }}%</div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'
import UploadDataset from '../UploadDataset.vue'

const props = defineProps({ dataset: Object })
const emit = defineEmits(['close']) // ✅ added

const files = ref([])
const split = ref(80)
const progress = ref(0)

function handleFiles(selected) {
  files.value = Array.from(selected).map(file => {
    if (file.type.startsWith("image/")) {
      file.preview = URL.createObjectURL(file)
    }
    return file
  })
}

async function uploadBulk() {
  if (!files.value.length) return alert("Please select images or a zip file.")

  const formData = new FormData()
  for (const file of files.value) formData.append("files", file)
  formData.append("dataset_name", props.dataset.name)
  formData.append("split", split.value)

  try {
    const res = await axios.post("http://localhost:8001/api/upload-bulk-dataset", formData, {
      headers: { "Content-Type": "multipart/form-data" },
      onUploadProgress: e => progress.value = Math.round((e.loaded * 100) / e.total),
    })
    alert(res.data.message)
    emit("close") // ✅ works now
  } catch (err) {
    console.error("❌ Upload failed:", err)
    alert("Upload failed. See console for details.")
  } finally {
    progress.value = 0
  }
}
</script>

