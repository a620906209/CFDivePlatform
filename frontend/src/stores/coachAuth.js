import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import coachApi from '../api/coachAxios'
import { useNotificationStore } from './notifications'
import { updateEchoToken } from '../plugins/echo'

export const useCoachAuthStore = defineStore('coachAuth', () => {
  const user  = ref(null)
  const token = ref(null)

  const isLoggedIn = computed(() => !!token.value)

  function init() {
    const savedToken = sessionStorage.getItem('coach_token')
    const savedUser  = sessionStorage.getItem('coach_user')
    if (savedToken) {
      token.value = savedToken
      user.value  = savedUser ? JSON.parse(savedUser) : null
      const ns = useNotificationStore()
      ns.startPolling()
      ns.startRealtime(user.value?.id)
    }
  }

  function setAuth(userData, tokenValue) {
    user.value  = userData
    token.value = tokenValue
    sessionStorage.setItem('coach_token', tokenValue)
    sessionStorage.setItem('coach_user', JSON.stringify(userData))
    const ns = useNotificationStore()
    ns.startPolling()
    updateEchoToken()
    ns.startRealtime(userData.id)
  }

  async function logout() {
    try {
      await coachApi.post('/provider/logout')
    } catch {}
    const ns = useNotificationStore()
    ns.stopRealtime()
    ns.stopPolling()
    user.value  = null
    token.value = null
    sessionStorage.removeItem('coach_token')
    sessionStorage.removeItem('coach_user')
  }

  return { user, token, isLoggedIn, init, setAuth, logout }
})
