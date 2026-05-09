<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '../api/axios'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const route  = useRoute()
const auth   = useAuthStore()

const email    = ref('')
const password = ref('')
const error    = ref('')
const loading  = ref(false)

const oauthError = route.query.error === 'oauth_failed'
  ? 'Google 登入失敗，請重試。'
  : ''

const successMsg = route.query.registered ? '註冊成功，請登入。' : ''

async function submit() {
  error.value   = ''
  loading.value = true
  try {
    const res = await api.post('/member/login', {
      email:    email.value,
      password: password.value,
    })
    auth.setAuth(res.data.data.user, res.data.data.token)
    router.push('/courses')
  } catch (e) {
    error.value = e.response?.data?.message || '帳號或密碼錯誤'
  } finally {
    loading.value = false
  }
}

function loginWithGoogle() {
  window.location.href = import.meta.env.VITE_API_URL + '/api/auth/google/redirect'
}
</script>

<template>
  <main class="min-h-[80vh] flex items-center justify-center px-4">
    <div class="bg-white rounded-2xl shadow-lg w-full max-w-md p-8">
      <h1 class="text-2xl font-bold text-gray-800 mb-6 text-center">會員登入</h1>

      <div v-if="successMsg" class="bg-green-50 text-green-700 text-sm rounded-lg px-4 py-3 mb-4">
        {{ successMsg }}
      </div>
      <div v-if="oauthError || error" class="bg-red-50 text-red-600 text-sm rounded-lg px-4 py-3 mb-4">
        {{ oauthError || error }}
      </div>

      <form @submit.prevent="submit" class="flex flex-col gap-4">
        <div>
          <label class="block text-sm text-gray-600 mb-1">Email</label>
          <input
            v-model="email"
            type="email"
            required
            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-ocean-400"
          />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">密碼</label>
          <input
            v-model="password"
            type="password"
            required
            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-ocean-400"
          />
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="bg-ocean-700 hover:bg-ocean-600 text-white font-semibold py-2.5 rounded-lg transition disabled:opacity-60"
        >
          {{ loading ? '登入中...' : '登入' }}
        </button>
      </form>

      <div class="relative my-5 flex items-center">
        <div class="flex-1 border-t border-gray-200"></div>
        <span class="mx-3 text-sm text-gray-400">或</span>
        <div class="flex-1 border-t border-gray-200"></div>
      </div>

      <button
        @click="loginWithGoogle"
        class="w-full flex items-center justify-center gap-3 border border-gray-300 rounded-lg py-2.5 hover:bg-gray-50 transition text-sm font-medium text-gray-700"
      >
        <svg class="w-5 h-5" viewBox="0 0 48 48">
          <path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9 3.2l6.7-6.7C35.8 2.5 30.2 0 24 0 14.6 0 6.6 5.4 2.5 13.3l7.8 6C12.3 13.1 17.7 9.5 24 9.5z"/>
          <path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.2-.4-4.7H24v9h12.7c-.6 3-2.3 5.5-4.8 7.2l7.6 5.9C43.8 37.6 46.5 31.5 46.5 24.5z"/>
          <path fill="#FBBC05" d="M10.3 28.7A14.7 14.7 0 0 1 9.5 24c0-1.6.3-3.2.8-4.7l-7.8-6A23.9 23.9 0 0 0 0 24c0 3.9.9 7.5 2.5 10.7l7.8-6z"/>
          <path fill="#34A853" d="M24 48c6.2 0 11.4-2 15.2-5.5l-7.6-5.9c-2 1.4-4.7 2.2-7.6 2.2-6.3 0-11.7-3.6-13.7-9.1l-7.8 6C6.6 42.6 14.6 48 24 48z"/>
        </svg>
        以 Google 帳號登入
      </button>

      <p class="text-center text-sm text-gray-500 mt-6">
        還沒有帳號？
        <RouterLink to="/register" class="text-ocean-600 hover:underline">立即註冊</RouterLink>
      </p>
    </div>
  </main>
</template>
