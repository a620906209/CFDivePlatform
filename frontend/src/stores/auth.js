import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../api/axios'
import { useNotificationStore } from './notifications'
import { updateEchoToken } from '../plugins/echo'

export const useAuthStore = defineStore('auth', () => {
  const user  = ref(null)
  const token = ref(null)

  const isLoggedIn = computed(() => !!token.value)

  function init() {
    const saved = sessionStorage.getItem('token')
    const savedUser = sessionStorage.getItem('user')
    if (saved) {
      token.value = saved
      user.value  = savedUser ? JSON.parse(savedUser) : null
      const ns = useNotificationStore()
      ns.startPolling()
      ns.startRealtime(user.value?.id)
    }
  }

  function setAuth(userData, tokenValue) {
    user.value  = userData
    token.value = tokenValue
    sessionStorage.setItem('token', tokenValue)
    sessionStorage.setItem('user', JSON.stringify(userData))
    const ns = useNotificationStore()
    ns.startPolling()
    updateEchoToken()
    ns.startRealtime(userData.id)
  }

  async function logout() {
    try {
      await api.post('/member/logout')
    } catch {}
    const ns = useNotificationStore()
    ns.stopRealtime()
    ns.stopPolling()
    user.value  = null
    token.value = null
    sessionStorage.removeItem('token')
    sessionStorage.removeItem('user')
  }

  return { user, token, isLoggedIn, init, setAuth, logout }
})
