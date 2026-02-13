<template>
  <div class="mt-6 space-y-2">
    <!-- 🧾 Logs container -->
    <div
      ref="logContainer"
      class="bg-black text-xs font-mono rounded p-3 h-64 overflow-y-auto border border-gray-700 w-full whitespace-pre-wrap log-info"
    >
      <!-- Render animated logs -->
      <pre
        v-for="(log, index) in displayLogs"
        :key="index"
        :class="logClass(log)"
        class="m-0 p-0 leading-tight text-left break-words"
      >
        {{ log }}
      </pre>

      <!-- ⏳ Waiting indicator -->
      <pre v-if="showCursor" class="text-gray-500">
        ▍
      </pre>
    </div>
  </div>
</template>

<script setup>
import useTraining from '../scripts/training-script.js'
import { ref, watch, nextTick } from 'vue'

const {
  logs,
  logContainer,
  trainingActive,
  autoScroll,
} = useTraining()

// ========================
// Typing animation state
// ========================
const displayLogs = ref([])
const queue = ref([])
const typing = ref(false)
const showCursor = ref(false)

const TYPE_SPEED = 12 // ms per character (tweak this)

// ========================
// Log styling
// ========================
function logClass(log) {
  return {
    'text-yellow-400': log.includes('[UPLOAD]'),
    'text-green-400': log.includes('[SUCCESS]') || log.includes('✅'),
    'text-blue-400': log.includes('[TRAIN]'),
    'text-red-400': log.includes('[ERROR]'),
    'text-white':
      log.startsWith('Epoch') ||
      log.includes('loss') ||
      log.includes('acc'),
  }
}

// ========================
// Typing effect
// ========================
async function typeLine(line) {
  typing.value = true
  showCursor.value = false

  let current = ''
  displayLogs.value.push('')
  const index = displayLogs.value.length - 1

  for (const char of line) {
    current += char
    displayLogs.value[index] = current

    await new Promise((r) => setTimeout(r, TYPE_SPEED))
    await scrollToBottom()
  }

  typing.value = false
}

// ========================
// Process queue
// ========================
async function processQueue() {
  if (typing.value || !queue.value.length) return

  const line = queue.value.shift()
  await typeLine(line)

  if (queue.value.length) {
    processQueue()
  }
}

// ========================
// Scroll
// ========================
async function scrollToBottom() {
  await nextTick()
  const el = logContainer.value
  if (el && trainingActive.value && autoScroll.value) {
    el.scrollTop = el.scrollHeight
  }
}

// ========================
// Watch raw logs (backend)
// ========================
watch(
  logs,
  (newLogs, oldLogs) => {
    const start = oldLogs?.length || 0
    const incoming = newLogs.slice(start)

    if (incoming.length) {
      queue.value.push(...incoming)
      processQueue()
    }
  },
  { deep: true }
)

// ========================
// Cursor animation (waiting)
// ========================
watch(
  () => trainingActive.value,
  (active) => {
    if (active) {
      const interval = setInterval(() => {
        showCursor.value = !typing.value
      }, 500)

      return () => clearInterval(interval)
    } else {
      showCursor.value = false
    }
  }
)
</script>

<style scoped>
pre {
  font-family: 'JetBrains Mono', monospace;
}

.log-info {
  background-color: black;
  overflow-y: auto;
  height: 16rem;
  box-shadow: inset 0 0 6px rgba(0, 0, 0, 0.8);
}

.log-info pre {
  margin: 0;
  white-space: normal;
  word-break: break-word;
  line-height: 1.3;
}
</style>
