import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../api/axios'

export const useAuthStore = defineStore('auth', () => {
  const user  = ref(null)
  const token = ref(null)

  const isLoggedIn = computed(() => !!token.value)

  function init() {
    const saved = localStorage.getItem('token')
    const savedUser = localStorage.getItem('user')
    if (saved) {
      token.value = saved
      user.value  = savedUser ? JSON.parse(savedUser) : null
    }
  }

  function setAuth(userData, tokenValue) {
    user.value  = userData
    token.value = tokenValue
    localStorage.setItem('token', tokenValue)
    localStorage.setItem('user', JSON.stringify(userData))
  }

  async function logout() {
    try {
      await api.post('/member/logout')
    } catch {}
    user.value  = null
    token.value = null
    localStorage.removeItem('token')
    localStorage.removeItem('user')
  }

  return { user, token, isLoggedIn, init, setAuth, logout }
})
