<template>
  <div
    class="border-2 border-dashed border-gray-400 p-6 rounded text-center cursor-pointer transition hover:border-blue-500"
    @dragover.prevent
    @dragenter.prevent="isDragging = true"
    @dragleave.prevent="isDragging = false"
    @drop.prevent="handleDrop"
    @click="triggerFileInput"
    :class="{ 'bg-blue-50': isDragging }"
  >
    <p class="text-gray-600">{{ label }}</p>
    <input
      ref="fileInput"
      type="file"
      :accept="accept"
      :multiple="multiple"
      class="hidden"
      @change="handleFileSelect"
    />
  </div>
</template>

<script setup>
import { ref, defineProps, defineEmits } from 'vue'

const props = defineProps({
  label: { type: String, default: 'Drag & drop files or click to select' },
  accept: { type: String, default: '*' },
  multiple: { type: Boolean, default: true }
})

const emit = defineEmits(['files-selected'])

const fileInput = ref(null)
const isDragging = ref(false)

function triggerFileInput() {
  fileInput.value?.click()
}

function handleFileSelect(e) {
  const files = Array.from(e.target.files)
  emit('files-selected', files)
}

function handleDrop(e) {
  const files = Array.from(e.dataTransfer.files)
  emit('files-selected', files)
  isDragging.value = false
}
</script>
