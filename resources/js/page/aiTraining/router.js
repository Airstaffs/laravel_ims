import { createRouter, createWebHistory } from 'vue-router'
import Training from './training.vue'   // 👈 correct relative path

const routes = [
  {
    path: '/aiTraining',             // landing page
    name: 'training',
    component: Training,
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

export default router