<template>
  <div class="p-4 bg-white dark:bg-gray-900 rounded-lg shadow">
    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100 mb-4">
      📊 Training Progress (Mock Data)
    </h2>
    <Line :data="data" :options="options" />
  </div>
</template>

<script setup>
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  LineElement,
  CategoryScale,
  LinearScale,
  PointElement
} from 'chart.js'

// Register chart components
ChartJS.register(
  Title,
  Tooltip,
  Legend,
  LineElement,
  CategoryScale,
  LinearScale,
  PointElement
)

// Sample data
const data = {
  labels: ['Epoch 1', 'Epoch 2', 'Epoch 3', 'Epoch 4', 'Epoch 5'],
  datasets: [
    {
      label: 'Loss',
      backgroundColor: '#8884d8',
      borderColor: '#8884d8',
      data: [0.9, 0.75, 0.6, 0.48, 0.4],
      tension: 0.3
    },
    {
      label: 'Accuracy',
      backgroundColor: '#82ca9d',
      borderColor: '#82ca9d',
      data: [0.5, 0.58, 0.66, 0.73, 0.81],
      tension: 0.3
    }
  ]
}

const options = {
  responsive: true,
  plugins: {
    legend: {
      labels: {
        color: '#d1d5db'
      }
    }
  },
  scales: {
    x: {
      ticks: {
        color: '#d1d5db'
      }
    },
    y: {
      ticks: {
        color: '#d1d5db'
      }
    }
  }
}
</script>
