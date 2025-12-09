<template>
  <div class="mt-6 space-y-2">
    <!-- 🟡 Upload progress -->
    <div v-if="uploading" class="text-sm text-yellow-400">
      ⏳ Uploading... {{ uploadProgress }}%
    </div>

    <!-- 🧾 Logs container -->
    <div
      ref="logContainer"
      class="bg-black text-xs font-mono rounded p-3 h-64 overflow-y-auto border border-gray-700 w-full max-w-full whitespace-pre-wrap log-info"
    >
      <pre
        v-for="(log, index) in logs"
        :key="index"
        :class="{
          'text-yellow-400': log.includes('[UPLOAD]'),
          'text-green-400': log.includes('[SUCCESS]') || log.includes('✅'),
          'text-blue-400': log.includes('[TRAIN]'),
          'text-red-400': log.includes('[ERROR]'),
          'text-white': log.startsWith('Epoch') || log.includes('loss') || log.includes('acc')
        }"
        class="m-0 p-0 leading-tight text-left break-words"
      >
        {{ log }}
      </pre>
    </div>
  </div>
</template>

<script setup>
import useTraining from '../scripts/training-script.js'
import { watch, nextTick } from 'vue'

const { logs, logContainer, uploading, uploadProgress, trainingActive, autoScroll } = useTraining()

// 🌀 Auto-scroll when new logs appear (but only during training)
watch(
  logs,
  async () => {
    await nextTick()
    const el = logContainer.value
    if (el && trainingActive.value && autoScroll.value) {
      requestAnimationFrame(() => {
        el.scrollTop = el.scrollHeight
      })
    }
  },
  { deep: true }
)
</script>

<style scoped>
pre {
  font-family: 'JetBrains Mono', monospace;
}

/* optional: subtle glow */
.log-info {
  overflow-x: hidden;
  background-color: black;
  overflow-y: auto;
  overflow-x: hidden;
  height: 16rem; /* or h-64 in Tailwind */
  box-shadow: inset 0 0 6px rgba(0, 0, 0, 0.8);
}

.log-info pre {
  margin: 0;           /* remove internal padding */
  white-space: normal; /* allow wrapping */
  word-break: break-word;
  line-height: 1.3;
}
</style>
