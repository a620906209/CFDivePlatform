<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api/axios'

const router = useRouter()

const name     = ref('')
const email    = ref('')
const password = ref('')
const confirm  = ref('')
const error    = ref('')
const loading  = ref(false)

async function submit() {
  error.value   = ''
  if (password.value !== confirm.value) {
    error.value = '兩次密碼輸入不一致'
    return
  }
  loading.value = true
  try {
    await api.post('/member/register', {
      name:                  name.value,
      email:                 email.value,
      password:              password.value,
      password_confirmation: confirm.value,
    })
    router.push('/login?registered=1')
  } catch (e) {
    error.value = e.response?.data?.message || '註冊失敗，請稍後再試'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="min-h-[80vh] flex items-center justify-center px-4">
    <div class="bg-white rounded-2xl shadow-lg w-full max-w-md p-8">
      <h1 class="text-2xl font-bold text-gray-800 mb-6 text-center">建立帳號</h1>

      <div v-if="error" class="bg-red-50 text-red-600 text-sm rounded-lg px-4 py-3 mb-4">
        {{ error }}
      </div>

      <form @submit.prevent="submit" class="flex flex-col gap-4">
        <div>
          <label class="block text-sm text-gray-600 mb-1">姓名</label>
          <input
            v-model="name"
            type="text"
            required
            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-ocean-400"
          />
        </div>
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
            minlength="8"
            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-ocean-400"
          />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">確認密碼</label>
          <input
            v-model="confirm"
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
          {{ loading ? '處理中...' : '建立帳號' }}
        </button>
      </form>

      <p class="text-center text-sm text-gray-500 mt-6">
        已有帳號？
        <RouterLink to="/login" class="text-ocean-600 hover:underline">立即登入</RouterLink>
      </p>
    </div>
  </main>
</template>
