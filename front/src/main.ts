import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { vueQueryPlugin, vueQueryOptions } from '@/lib/query'

const app = createApp(App)
app.use(vueQueryPlugin, vueQueryOptions)
app.use(createPinia())
app.use(router)

app.mount('#app')
