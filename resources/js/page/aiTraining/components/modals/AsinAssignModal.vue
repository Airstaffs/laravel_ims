<template>
  <div class="fixed inset-0 bg-black bg-opacity-60 flex justify-center items-center z-50">
    <div
      class="bg-gray-900 p-6 rounded-lg w-11/12 md:w-2/3 max-h-[90vh] overflow-y-auto shadow-xl border border-gray-700"
    >
      <!-- Header -->
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold">
          🔖 {{ dataset.name }} — ASIN Management
        </h2>
        <button
          class="text-gray-400 hover:text-white text-2xl leading-none"
          @click="$emit('close')"
        >
          ×
        </button>
      </div>

      <!-- Add ASIN -->
      <div class="flex gap-2 mb-4">
        <input
          v-model="newAsin"
          type="text"
          placeholder="Enter ASIN (e.g., B0B6WNS5SZ)"
          class="flex-1 p-2 rounded bg-gray-800 border border-gray-700 text-gray-100 focus:outline-none focus:border-indigo-500 uppercase"
          maxlength="10"
        />
        <button
          class="bg-indigo-600 hover:bg-indigo-700 px-4 py-2 rounded transition"
          @click="addAsinHandler"
        >
          ➕ Add
        </button>
      </div>

      <!-- ASIN Table -->
      <div class="overflow-x-auto border border-gray-700 rounded-lg">
        <table class="min-w-full bg-gray-800 text-gray-200">
          <thead class="bg-gray-700 text-gray-300 uppercase text-sm">
            <tr>
              <th class="py-3 px-4 text-left">ASIN Code</th>
              <th class="py-3 px-4 text-center">Updated</th>
              <th class="py-3 px-4 text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(asin, index) in asinList"
              :key="index"
              class="border-t border-gray-700 hover:bg-gray-750 transition"
            >
              <!-- ASIN Code -->
              <td class="py-3 px-4 font-mono text-gray-100 uppercase">
                <span v-if="editIndex !== index">{{ asin.code }}</span>
                <input
                  v-else
                  v-model="asinEditValue.code"
                  maxlength="10"
                  class="bg-gray-700 border border-gray-600 rounded p-1 w-full text-gray-100 uppercase font-mono"
                />
              </td>

              <!-- Updated -->
              <td class="py-3 px-4 text-center text-gray-400">
                {{ asin.updated }}
              </td>

              <!-- Actions -->
              <td class="py-3 px-4 text-center">
                <div v-if="editIndex === index">
                  <button
                    class="bg-green-600 hover:bg-green-700 px-3 py-1 rounded mr-2 transition"
                    @click="saveEdit(index)"
                  >
                    💾 Save
                  </button>
                  <button
                    class="bg-gray-600 hover:bg-gray-700 px-3 py-1 rounded transition"
                    @click="cancelEdit"
                  >
                    ✖ Cancel
                  </button>
                </div>
                <div v-else>
                  <button
                    class="bg-yellow-600 hover:bg-yellow-700 px-3 py-1 rounded mr-2 transition"
                    @click="startEdit(index)"
                  >
                    ✏ Edit
                  </button>
                  <button
                    class="bg-red-600 hover:bg-red-700 px-3 py-1 rounded transition"
                    @click="deleteAsinHandler(index)"
                  >
                    🗑 Delete
                  </button>
                </div>
              </td>
            </tr>

            <!-- Empty State -->
            <tr v-if="asinList.length === 0">
              <td colspan="3" class="py-4 text-center text-gray-500">
                No ASINs added yet.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { fetchAsinsForClass, addAsin, deleteAsin } from "../../scripts/asin-script";


const props = defineProps({
  dataset: Object,
});

const asinList = ref([]);
const newAsin = ref("");
const editIndex = ref(null);
const asinEditValue = ref({ code: "" });

// 🧩 Load ASINs
async function loadAsins() {
  asinList.value = await fetchAsinsForClass(props.dataset.name);
}

// ➕ Add ASIN
async function addAsinHandler() {
  const asin = newAsin.value.trim().toUpperCase();
  if (!asin) return alert("ASIN is required.");
  if (asin.length !== 10) return alert("ASIN must be exactly 10 characters.");

  try {
    await addAsin(props.dataset.name, asin);
    await loadAsins();
    newAsin.value = "";
  } catch (err) {
    if (err.response && err.response.status === 409) {
      alert(err.response.data.error); // "ASIN already assigned to SONOS_PLAY1_WHITE"
    } else {
      console.error(err);
      alert("Failed to add ASIN.");
    }
  }
}

// 🗑 Delete
async function deleteAsinHandler(index) {
  const asin = asinList.value[index];
  if (confirm(`Delete ASIN "${asin.code}"?`)) {
    await deleteAsin(asin.code);
    await loadAsins();
  }
}

// Local edit logic (unchanged)
function startEdit(index) {
  editIndex.value = index;
  asinEditValue.value = { ...asinList.value[index] };
}

function cancelEdit() {
  editIndex.value = null;
  asinEditValue.value = { code: "" };
}

async function saveEdit(index) {
  const updatedCode = asinEditValue.value.code.toUpperCase();
  await addAsin(props.dataset.name, updatedCode);
  await deleteAsin(asinList.value[index].code);
  editIndex.value = null;
  await loadAsins();
}


onMounted(loadAsins);

</script>

<style scoped>
::-webkit-scrollbar {
  width: 8px;
}
::-webkit-scrollbar-thumb {
  background: #4b5563;
  border-radius: 8px;
}
</style>
