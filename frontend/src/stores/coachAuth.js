import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import coachApi from '../api/coachAxios'

export const useCoachAuthStore = defineStore('coachAuth', () => {
  const user  = ref(null)
  const token = ref(null)

  const isLoggedIn = computed(() => !!token.value)

  function init() {
    const savedToken = localStorage.getItem('coach_token')
    const savedUser  = localStorage.getItem('coach_user')
    if (savedToken) {
      token.value = savedToken
      user.value  = savedUser ? JSON.parse(savedUser) : null
    }
  }

  function setAuth(userData, tokenValue) {
    user.value  = userData
    token.value = tokenValue
    localStorage.setItem('coach_token', tokenValue)
    localStorage.setItem('coach_user', JSON.stringify(userData))
  }

  async function logout() {
    try {
      await coachApi.post('/provider/logout')
    } catch {}
    user.value  = null
    token.value = null
    localStorage.removeItem('coach_token')
    localStorage.removeItem('coach_user')
  }

  return { user, token, isLoggedIn, init, setAuth, logout }
})
