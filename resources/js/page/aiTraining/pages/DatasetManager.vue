<template>
  <div class="p-6 space-y-6 bg-gray-900 text-white rounded-lg shadow-lg">
    <h1 class="text-2xl font-bold mb-4">📂 Dataset Manager</h1>

    <!-- Dataset Table -->
    <div class="overflow-x-auto">
      <table class="min-w-full bg-gray-800 border border-gray-700 rounded-lg overflow-hidden">
        <thead class="bg-gray-700 text-gray-300 uppercase text-sm">
          <tr>
            <th class="py-3 px-4 text-left">Title</th>
            <th class="py-3 px-4 text-center">Val</th>
            <th class="py-3 px-4 text-center">Train</th>
            <th class="py-3 px-4 text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(dataset, index) in datasets"
            :key="index"
            class="border-t border-gray-700 hover:bg-gray-750 transition"
          >
            <td class="py-3 px-4 font-medium text-gray-100">
              {{ dataset.name }}
            </td>
            <td
              class="py-3 px-4 text-center text-indigo-400 hover:underline cursor-pointer"
              @click="openImageModal(dataset, 'val')"
            >
              {{ dataset.val }}
            </td>
            <td
              class="py-3 px-4 text-center text-indigo-400 hover:underline cursor-pointer"
              @click="openImageModal(dataset, 'train')"
            >
              {{ dataset.train }}
            </td>
            <td class="py-3 px-4 text-center">
              <button
                class="bg-indigo-600 hover:bg-indigo-700 px-3 py-1 rounded mr-2 transition"
                @click="assignAsin(dataset)"
              >
                Assign ASIN
              </button>
              <button
                class="bg-gray-600 hover:bg-gray-700 px-3 py-1 rounded mr-2 transition"
                @click="openBulkModal(dataset)"
              >
                Add Images Bulk
              </button>
              <button
                class="bg-red-600 hover:bg-red-700 px-3 py-1 rounded transition"
                @click="deleteDataset(dataset)"
              >
                Delete
              </button>
            </td>
          </tr>

          <tr v-if="!datasets.length">
            <td colspan="4" class="text-center py-4 text-gray-500">
              No datasets found.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Image Modal -->
    <ImageModal
      v-if="showImageModal"
      :dataset="selectedDataset"
      :folderType="selectedFolder"
      @close="showImageModal = false"
    />

    <!-- ASIN Assign Modal -->
    <AsinAssignModal
      v-if="showAsinModal"
      :dataset="selectedDataset"
      @close="showAsinModal = false"
    />
    <!-- Bulk Image Modal -->
    <BulkImageModal
      v-if="showBulkModal"
      :dataset="selectedDataset"
      @close="handleBulkClose"
    />
    
  </div>
</template>

<script setup>
import axios from 'axios'
import { ref, onMounted } from "vue";
import ImageModal from "../components/modals/ImageModal.vue";
import AsinAssignModal from "../components/modals/AsinAssignModal.vue";
import BulkImageModal from "../components/modals/BulkImageModal.vue";
import {
  fetchDatasetFolders,
  deleteDatasetFolder,
} from "../scripts/dataset-script.js";

// 🔹 State
const datasets = ref([]);
const showImageModal = ref(false);
const showAsinModal = ref(false);
const selectedDataset = ref(null);
const selectedFolder = ref("");
const showBulkModal = ref(false);

// 🔄 Load datasets
async function loadDatasets() {
  datasets.value = await fetchDatasetFolders();
}

onMounted(loadDatasets);

// 🖼 Image modal
function openImageModal(dataset, folderType) {
  selectedDataset.value = dataset;
  selectedFolder.value = folderType;
  showImageModal.value = true;
}

// 🔖 ASIN modal
function assignAsin(dataset) {
  selectedDataset.value = dataset;
  showAsinModal.value = true;
}

// 📦 Bulk upload modal
function openBulkModal(dataset) {
  selectedDataset.value = dataset;
  showBulkModal.value = true;
}

// 🗑 Delete dataset (FINAL & SAFE)
async function deleteDataset(dataset) {
  if (!confirm(`Are you sure you want to delete "${dataset.name}"?`)) return

  try {
    // ✅ FIX: pass dataset.name, NOT the whole object
    await axios.delete(
      `/api/datasets/${encodeURIComponent(dataset.name)}`
    )

    // 🔁 Refresh list
    await loadDatasets()
  } catch (err) {
    console.error('❌ Failed to delete dataset:', err)
    alert('Failed to delete dataset. Check console for details.')
  }
}

// 🔄 Refresh after bulk upload
async function handleBulkClose() {
  showBulkModal.value = false;
  await loadDatasets();
}
</script>

