<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import coachApi from '../../api/coachAxios'
import { useCoachAuthStore } from '../../stores/coachAuth'

const router    = useRouter()
const route     = useRoute()
const coachAuth = useCoachAuthStore()

const email    = ref('')
const password = ref('')
const error    = ref('')
const loading  = ref(false)

const registeredMsg = route.query.registered ? '註冊成功，請登入。' : ''

async function submit() {
  error.value   = ''
  loading.value = true
  try {
    const res  = await coachApi.post('/provider/login', {
      email:    email.value,
      password: password.value,
    })
    const { user, token } = res.data.data
    coachAuth.setAuth(user, token)
    router.push('/coach/dashboard')
  } catch (e) {
    error.value = e.response?.data?.message || '帳號或密碼錯誤'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="min-h-screen bg-gray-50 flex items-center justify-center px-4">
    <div class="bg-white rounded-2xl shadow-lg w-full max-w-md p-8">

      <div class="text-center mb-8">
        <p class="text-gray-500 text-sm mb-1">CFDive 教練後台</p>
        <h1 class="text-2xl font-bold text-gray-800">教練登入</h1>
      </div>

      <div v-if="registeredMsg" class="bg-green-50 text-green-700 text-sm rounded-lg px-4 py-3 mb-4">
        {{ registeredMsg }}
      </div>
      <div v-if="error" class="bg-red-50 text-red-600 text-sm rounded-lg px-4 py-3 mb-4">
        {{ error }}
      </div>

      <form @submit.prevent="submit" class="flex flex-col gap-4">
        <div>
          <label class="block text-sm text-gray-600 mb-1">Email</label>
          <input v-model="email" type="email" required
            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-gray-400" />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">密碼</label>
          <input v-model="password" type="password" required
            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-gray-400" />
        </div>
        <button type="submit" :disabled="loading"
          class="bg-gray-900 hover:bg-gray-700 text-white font-semibold py-2.5 rounded-lg transition disabled:opacity-60">
          {{ loading ? '登入中...' : '登入' }}
        </button>
      </form>

      <p class="text-center text-sm text-gray-500 mt-6">
        還沒有帳號？
        <RouterLink to="/coach/register" class="text-gray-700 hover:underline font-medium">申請教練帳號</RouterLink>
      </p>
    </div>
  </main>
</template>
