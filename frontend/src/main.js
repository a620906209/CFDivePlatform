import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import { useCoachAuthStore } from './stores/coachAuth'
import { useAdminAuthStore } from './stores/adminAuth'
import echo from './plugins/echo'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)

// 在 router 安裝前同步初始化所有 auth store，
// 確保 beforeEach guard 跑時 isLoggedIn 已反映 localStorage 的實際狀態
useAuthStore().init()
useCoachAuthStore().init()
useAdminAuthStore().init()

app.use(router)
app.config.globalProperties.$echo = echo
app.mount('#app')
