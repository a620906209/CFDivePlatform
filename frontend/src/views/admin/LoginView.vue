<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import adminApi from '../../api/adminAxios'
import { useAdminAuthStore } from '../../stores/adminAuth'

const router    = useRouter()
const adminAuth = useAdminAuthStore()

const email    = ref('')
const password = ref('')
const error    = ref('')
const loading  = ref(false)

async function submit() {
  error.value   = ''
  loading.value = true
  try {
    const res = await adminApi.post('/admin/login', { email: email.value, password: password.value })
    adminAuth.setAuth(res.data.data.user, res.data.data.token)
    router.push('/admin/dashboard')
  } catch (e) {
    error.value = e.response?.data?.message || '帳號或密碼錯誤'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="min-h-screen bg-slate-100 flex items-center justify-center px-4">
    <div class="bg-white rounded-2xl shadow-lg w-full max-w-sm p-8">
      <div class="text-center mb-8">
        <p class="text-slate-400 text-sm mb-1">CFDive</p>
        <h1 class="text-2xl font-bold text-slate-800">管理員登入</h1>
      </div>
      <div v-if="error" class="bg-red-50 text-red-600 text-sm rounded-lg px-4 py-3 mb-4">{{ error }}</div>
      <form @submit.prevent="submit" class="flex flex-col gap-4">
        <div>
          <label class="block text-sm text-slate-600 mb-1">Email</label>
          <input v-model="email" type="email" required
            class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400" />
        </div>
        <div>
          <label class="block text-sm text-slate-600 mb-1">密碼</label>
          <input v-model="password" type="password" required
            class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400" />
        </div>
        <button type="submit" :disabled="loading"
          class="bg-slate-800 hover:bg-slate-700 text-white font-semibold py-2.5 rounded-lg transition disabled:opacity-60">
          {{ loading ? '登入中...' : '登入' }}
        </button>
      </form>
    </div>
  </main>
</template>
