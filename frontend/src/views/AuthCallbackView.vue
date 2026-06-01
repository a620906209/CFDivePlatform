<script setup>
import { onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import api from '../api/axios'

const router = useRouter()
const route  = useRoute()
const auth   = useAuthStore()

onMounted(async () => {
  const token = new URLSearchParams(window.location.hash.substring(1)).get('token')
  const error = route.query.error

  if (error || !token) {
    router.push('/login?error=oauth_failed')
    return
  }

  // 存 token 先，再拉 profile
  localStorage.setItem('token', token)
  try {
    const res = await api.get('/member/profile')
    auth.setAuth(res.data.data, token)
  } catch {
    auth.setAuth(null, token)
  }

  // 清除 URL 上的 token
  history.replaceState({}, '', '/auth/callback')
  router.push('/courses')
})
</script>

<template>
  <main class="min-h-[80vh] flex items-center justify-center text-gray-400">
    正在完成登入，請稍候...
  </main>
</template>
