<script setup>
import { ref, onMounted } from 'vue'
import api from '../api/axios'
import { useAuthStore } from '../stores/auth'

const auth    = useAuthStore()
const loading = ref(true)
const saving  = ref(false)
const success = ref(false)
const error   = ref('')

const form = ref({
  name:               '',
  birthday:           '',
  gender:             '',
  address:            '',
  emergency_contact:  '',
  emergency_phone:    '',
})

onMounted(async () => {
  try {
    const res = await api.get('/member/profile')
    const d   = res.data.data
    form.value.name              = d.name  || ''
    form.value.birthday          = d.profile?.birthday         || ''
    form.value.gender            = d.profile?.gender           || ''
    form.value.address           = d.profile?.address          || ''
    form.value.emergency_contact = d.profile?.emergency_contact|| ''
    form.value.emergency_phone   = d.profile?.emergency_phone  || ''
  } catch {
    error.value = '無法載入個人資料'
  } finally {
    loading.value = false
  }
})

async function save() {
  saving.value  = true
  success.value = false
  error.value   = ''
  try {
    await api.put('/member/profile', form.value)
    auth.user = { ...auth.user, name: form.value.name }
    success.value = true
    setTimeout(() => (success.value = false), 3000)
  } catch (e) {
    error.value = e.response?.data?.message || '儲存失敗，請稍後再試'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <main class="max-w-2xl mx-auto px-4 py-10">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">個人資料</h1>

    <div v-if="loading" class="text-center text-gray-400 py-20">載入中...</div>

    <form v-else @submit.prevent="save" class="bg-white rounded-2xl shadow p-6 flex flex-col gap-5">

      <div v-if="success" class="bg-green-50 text-green-700 text-sm rounded-lg px-4 py-3">
        ✅ 資料已更新
      </div>
      <div v-if="error" class="bg-red-50 text-red-600 text-sm rounded-lg px-4 py-3">
        {{ error }}
      </div>

      <div>
        <label class="block text-sm text-gray-600 mb-1">姓名</label>
        <input
          v-model="form.name"
          type="text"
          class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-ocean-400"
        />
      </div>

      <div>
        <label class="block text-sm text-gray-600 mb-1">生日</label>
        <input
          v-model="form.birthday"
          type="date"
          class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-ocean-400"
        />
      </div>

      <div>
        <label class="block text-sm text-gray-600 mb-1">性別</label>
        <select
          v-model="form.gender"
          class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-ocean-400"
        >
          <option value="">請選擇</option>
          <option value="male">男</option>
          <option value="female">女</option>
          <option value="other">其他</option>
        </select>
      </div>

      <div>
        <label class="block text-sm text-gray-600 mb-1">地址</label>
        <input
          v-model="form.address"
          type="text"
          class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-ocean-400"
        />
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm text-gray-600 mb-1">緊急聯絡人</label>
          <input
            v-model="form.emergency_contact"
            type="text"
            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-ocean-400"
          />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">緊急聯絡電話</label>
          <input
            v-model="form.emergency_phone"
            type="tel"
            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-ocean-400"
          />
        </div>
      </div>

      <button
        type="submit"
        :disabled="saving"
        class="bg-ocean-700 hover:bg-ocean-600 text-white font-semibold py-2.5 rounded-lg transition disabled:opacity-60"
      >
        {{ saving ? '儲存中...' : '儲存變更' }}
      </button>
    </form>
  </main>
</template>
