import { createApp } from 'vue'
import App from './page/aiTraining/App.vue'
import router from './page/aiTraining/router'
import '../css/training.css' // 👈 add this

createApp(App)
  .use(router)
  .mount('#ai-app')
