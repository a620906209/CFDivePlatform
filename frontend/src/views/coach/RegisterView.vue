<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import coachApi from '../../api/coachAxios'

const router = useRouter()

const form = ref({
  name:                  '',
  email:                 '',
  password:              '',
  password_confirmation: '',
  phone:                 '',
  business_name:         '',
  description:           '',
  contact_phone:         '',
  contact_email:         '',
  address:               '',
})

const error   = ref('')
const errors  = ref({})
const loading = ref(false)

async function submit() {
  error.value   = ''
  errors.value  = {}
  loading.value = true
  try {
    await coachApi.post('/provider/register', form.value)
    router.push('/coach/login?registered=1')
  } catch (e) {
    const data = e.response?.data
    error.value  = data?.message || '註冊失敗，請稍後再試'
    errors.value = data?.errors  || {}
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="min-h-screen bg-gray-50 flex items-center justify-center px-4 py-12">
    <div class="bg-white rounded-2xl shadow-lg w-full max-w-lg p-8">

      <div class="text-center mb-8">
        <p class="text-ocean-600 text-sm font-medium mb-1">CFDive 教練後台</p>
        <h1 class="text-2xl font-bold text-gray-800">申請成為教練</h1>
      </div>

      <div v-if="error" class="bg-red-50 text-red-600 text-sm rounded-lg px-4 py-3 mb-6">
        {{ error }}
      </div>

      <form @submit.prevent="submit" class="space-y-5">

        <fieldset class="space-y-4">
          <legend class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2">帳號資訊</legend>

          <div>
            <label class="block text-sm text-gray-600 mb-1">姓名 <span class="text-red-400">*</span></label>
            <input v-model="form.name" type="text" required
              class="w-full border rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ocean-400"
              :class="errors.name ? 'border-red-400' : 'border-gray-300'" />
            <p v-if="errors.name" class="text-red-500 text-xs mt-1">{{ errors.name[0] }}</p>
          </div>

          <div>
            <label class="block text-sm text-gray-600 mb-1">Email <span class="text-red-400">*</span></label>
            <input v-model="form.email" type="email" required
              class="w-full border rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ocean-400"
              :class="errors.email ? 'border-red-400' : 'border-gray-300'" />
            <p v-if="errors.email" class="text-red-500 text-xs mt-1">{{ errors.email[0] }}</p>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm text-gray-600 mb-1">密碼 <span class="text-red-400">*</span></label>
              <input v-model="form.password" type="password" required minlength="6"
                class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ocean-400" />
            </div>
            <div>
              <label class="block text-sm text-gray-600 mb-1">確認密碼 <span class="text-red-400">*</span></label>
              <input v-model="form.password_confirmation" type="password" required
                class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ocean-400" />
            </div>
          </div>

          <div>
            <label class="block text-sm text-gray-600 mb-1">手機號碼</label>
            <input v-model="form.phone" type="tel"
              class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ocean-400" />
          </div>
        </fieldset>

        <hr class="border-gray-100" />

        <fieldset class="space-y-4">
          <legend class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2">教練 / 業者資訊</legend>

          <div>
            <label class="block text-sm text-gray-600 mb-1">工作室 / 個人教練名稱</label>
            <input v-model="form.business_name" type="text" placeholder="例：藍海潛水工作室（選填）"
              class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ocean-400" />
          </div>

          <div>
            <label class="block text-sm text-gray-600 mb-1">自我介紹</label>
            <textarea v-model="form.description" rows="3" placeholder="簡短介紹你的教學風格與專長..."
              class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ocean-400 resize-none" />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm text-gray-600 mb-1">聯絡電話</label>
              <input v-model="form.contact_phone" type="tel"
                class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ocean-400" />
            </div>
            <div>
              <label class="block text-sm text-gray-600 mb-1">聯絡信箱</label>
              <input v-model="form.contact_email" type="email"
                class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ocean-400" />
            </div>
          </div>

          <div>
            <label class="block text-sm text-gray-600 mb-1">地址</label>
            <input v-model="form.address" type="text"
              class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ocean-400" />
          </div>
        </fieldset>

        <button type="submit" :disabled="loading"
          class="w-full bg-ocean-700 hover:bg-ocean-600 text-white font-semibold py-3 rounded-lg transition disabled:opacity-60 mt-2">
          {{ loading ? '送出中...' : '申請教練帳號' }}
        </button>
      </form>

      <p class="text-center text-sm text-gray-500 mt-6">
        已有帳號？
        <RouterLink to="/coach/login" class="text-ocean-600 hover:underline">返回登入</RouterLink>
      </p>
    </div>
  </main>
</template>
