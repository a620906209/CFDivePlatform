import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import adminApi from '../api/adminAxios'

export const useAdminAuthStore = defineStore('adminAuth', () => {
  const user  = ref(null)
  const token = ref(null)

  const isLoggedIn = computed(() => !!token.value)

  function init() {
    const savedToken = localStorage.getItem('admin_token')
    const savedUser  = localStorage.getItem('admin_user')
    if (savedToken) {
      token.value = savedToken
      user.value  = savedUser ? JSON.parse(savedUser) : null
    }
  }

  function setAuth(userData, tokenValue) {
    user.value  = userData
    token.value = tokenValue
    localStorage.setItem('admin_token', tokenValue)
    localStorage.setItem('admin_user', JSON.stringify(userData))
  }

  async function logout() {
    try {
      await adminApi.post('/admin/logout')
    } catch {}
    user.value  = null
    token.value = null
    localStorage.removeItem('admin_token')
    localStorage.removeItem('admin_user')
  }

  return { user, token, isLoggedIn, init, setAuth, logout }
})
