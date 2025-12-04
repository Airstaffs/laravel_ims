import { createRouter, createWebHistory } from 'vue-router'
import Training from './pages/training.vue'
import DatasetManager from './pages/DatasetManager.vue'
import TestingPage from './pages/TestingPage.vue'

const routes = [
  {
    path: '/aiTraining',              // ✅ URL path, NOT file path
    name: 'training',
    component: Training,
  },
  {
    path: '/dataset-management',      // ✅ URL path, NOT file path
    name: 'dataset-management',
    component: DatasetManager,
  },
  {
    path: '/testing-training',      // ✅ URL path, NOT file path
    name: 'testing-training',
    component: TestingPage,
  },
  {
    path: '/:pathMatch(.*)*',         // ✅ fallback route
    redirect: '/aiTraining',
  },
  
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

export default router
