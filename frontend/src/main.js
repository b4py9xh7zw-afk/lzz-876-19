import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import App from './App.vue'
import ToastContainer from './components/ToastContainer.vue'
import Modal from './components/Modal.vue'
import './style.css'

const app = createApp(App)

app.config.errorHandler = (err, instance, info) => {
  console.error('Global error:', err)
  console.error('Component:', instance)
  console.error('Error info:', info)
}

app.use(createPinia())
app.use(router)

app.component('ToastContainer', ToastContainer)
app.component('Modal', Modal)

app.mount('#app')
