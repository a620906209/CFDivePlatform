<script setup>
import { ref, onMounted } from 'vue'
import coachApi from '../../api/coachAxios'

const loading = ref(true)
const saving  = ref(false)
const success = ref(false)
const error   = ref('')

const profile = ref(null)
const form    = ref({
  name: '', phone: '',
  business_name: '', description: '',
  certifications: '', dive_sites: '', services: '', facilities: '',
  contact_person: '', contact_phone: '', contact_email: '',
  address: '', business_hours: '',
  website: '', social_media: '',
})

onMounted(async () => {
  try {
    const res = await coachApi.get('/provider/profile')
    const d   = res.data.data
    profile.value = d
    const p = d.provider_profile || {}
    form.value = {
      name:           d.name              || '',
      phone:          d.phone             || '',
      business_name:  p.business_name     || '',
      description:    p.description       || '',
      certifications: p.certifications    || '',
      dive_sites:     p.dive_sites        || '',
      services:       p.services          || '',
      facilities:     p.facilities        || '',
      contact_person: p.contact_person    || '',
      contact_phone:  p.contact_phone     || '',
      contact_email:  p.contact_email     || '',
      address:        p.address           || '',
      business_hours: p.business_hours    || '',
      website:        p.website           || '',
      social_media:   p.social_media      || '',
    }
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
    await coachApi.put('/provider/profile', form.value)
    success.value = true
    setTimeout(() => (success.value = false), 3000)
  } catch (e) {
    error.value = e.response?.data?.message || '儲存失敗'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <main class="max-w-2xl mx-auto px-4 py-10">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">教練個人資料</h1>

    <div v-if="loading" class="text-center text-gray-400 py-20">載入中...</div>

    <form v-else @submit.prevent="save" class="space-y-6">

      <div v-if="success" class="bg-green-50 text-green-700 text-sm rounded-lg px-4 py-3">✅ 資料已更新</div>
      <div v-if="error"   class="bg-red-50 text-red-600 text-sm rounded-lg px-4 py-3">{{ error }}</div>

      <!-- 唯讀資訊 -->
      <div class="bg-white rounded-2xl shadow p-6 space-y-3">
        <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">帳號資訊</h2>
        <p class="text-sm text-gray-500">Email：<span class="text-gray-800">{{ profile?.email }}</span></p>
        <p class="text-sm text-gray-500">
          驗證狀態：
          <span :class="profile?.provider_profile?.is_verified ? 'text-green-600' : 'text-yellow-600'">
            {{ profile?.provider_profile?.is_verified ? '✅ 已驗證' : '⏳ 審核中' }}
          </span>
        </p>
        <p class="text-sm text-gray-500">評分：<span class="text-gray-800">{{ profile?.provider_profile?.rating ?? '-' }}</span></p>
      </div>

      <!-- 可編輯表單 -->
      <div class="bg-white rounded-2xl shadow p-6 space-y-4">
        <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">基本資料</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm text-gray-600 mb-1">姓名</label>
            <input v-model="form.name" type="text" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">手機</label>
            <input v-model="form.phone" type="tel" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" />
          </div>
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">工作室 / 教練名稱</label>
          <input v-model="form.business_name" type="text" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">自我介紹</label>
          <textarea v-model="form.description" rows="3" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400 resize-none" />
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow p-6 space-y-4">
        <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">專業資訊</h2>
        <div>
          <label class="block text-sm text-gray-600 mb-1">認證（PADI / SSI 等）</label>
          <input v-model="form.certifications" type="text" placeholder="例：PADI OWSI, SSI Instructor" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">常駐潛點</label>
          <input v-model="form.dive_sites" type="text" placeholder="例：墾丁,小琉球,綠島" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">服務項目</label>
          <input v-model="form.services" type="text" placeholder="例：體驗潛水,初級課程,進階課程" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">設施</label>
          <input v-model="form.facilities" type="text" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" />
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow p-6 space-y-4">
        <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">聯絡資訊</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm text-gray-600 mb-1">聯絡人</label>
            <input v-model="form.contact_person" type="text" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">聯絡電話</label>
            <input v-model="form.contact_phone" type="tel" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" />
          </div>
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">聯絡信箱</label>
          <input v-model="form.contact_email" type="email" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">地址</label>
          <input v-model="form.address" type="text" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">營業時間</label>
          <input v-model="form.business_hours" type="text" placeholder="例：週一至週五 09:00-18:00" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm text-gray-600 mb-1">官網</label>
            <input v-model="form.website" type="url" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">社群媒體</label>
            <input v-model="form.social_media" type="text" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" />
          </div>
        </div>
      </div>

      <button type="submit" :disabled="saving"
        class="w-full bg-gray-900 hover:bg-gray-700 text-white font-semibold py-3 rounded-lg transition disabled:opacity-60">
        {{ saving ? '儲存中...' : '儲存變更' }}
      </button>
    </form>
  </main>
</template>
