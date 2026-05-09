<script setup>
import { ref, onMounted } from 'vue'
import adminApi from '../../api/adminAxios'

const providers = ref([])
const loading   = ref(true)
const search    = ref('')

async function fetchProviders() {
  loading.value = true
  try {
    const params = {}
    if (search.value) params.q = search.value
    const res = await adminApi.get('/admin/providers', { params })
    providers.value = res.data.data
  } finally {
    loading.value = false
  }
}

async function toggleActive(p) {
  try {
    const res = await adminApi.put(`/admin/providers/${p.id}/toggle-active`)
    p.is_active = res.data.data.is_active
  } catch (e) { alert(e.response?.data?.message || '操作失敗') }
}

async function toggleVerified(p) {
  try {
    const res = await adminApi.put(`/admin/providers/${p.id}/toggle-verified`)
    if (p.provider_profile) p.provider_profile.is_verified = res.data.data.is_verified
  } catch (e) { alert(e.response?.data?.message || '操作失敗') }
}

onMounted(fetchProviders)
</script>

<template>
  <main class="max-w-6xl mx-auto px-4 py-10">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">教練管理</h1>

    <div class="flex gap-3 mb-6">
      <input v-model="search" @keyup.enter="fetchProviders" type="text" placeholder="搜尋姓名或 Email..."
        class="flex-1 border border-slate-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-400" />
      <button @click="fetchProviders"
        class="bg-slate-800 text-white px-5 py-2 rounded-lg text-sm hover:bg-slate-700 transition">搜尋</button>
    </div>

    <div v-if="loading" class="text-center text-slate-400 py-20">載入中...</div>

    <div v-else class="bg-white rounded-2xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-6 py-3 text-left">姓名</th>
            <th class="px-6 py-3 text-left">工作室</th>
            <th class="px-6 py-3 text-center">驗證</th>
            <th class="px-6 py-3 text-center">狀態</th>
            <th class="px-6 py-3 text-center">操作</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="p in providers" :key="p.id" class="hover:bg-slate-50">
            <td class="px-6 py-4">
              <p class="font-medium text-slate-800">{{ p.name }}</p>
              <p class="text-xs text-slate-400">{{ p.email }}</p>
            </td>
            <td class="px-6 py-4 text-slate-500">{{ p.provider_profile?.business_name || '-' }}</td>
            <td class="px-6 py-4 text-center">
              <span :class="p.provider_profile?.is_verified ? 'bg-teal-100 text-teal-700' : 'bg-slate-100 text-slate-500'"
                class="text-xs px-2 py-1 rounded-full font-medium">
                {{ p.provider_profile?.is_verified ? '已驗證' : '未驗證' }}
              </span>
            </td>
            <td class="px-6 py-4 text-center">
              <span :class="p.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'"
                class="text-xs px-2 py-1 rounded-full font-medium">
                {{ p.is_active ? '啟用' : '停用' }}
              </span>
            </td>
            <td class="px-6 py-4 text-center">
              <div class="flex justify-center gap-2">
                <button @click="toggleVerified(p)"
                  :class="p.provider_profile?.is_verified ? 'bg-slate-100 text-slate-600' : 'bg-teal-50 text-teal-700'"
                  class="text-xs px-3 py-1 rounded-lg hover:opacity-80 transition">
                  {{ p.provider_profile?.is_verified ? '取消驗證' : '驗證' }}
                </button>
                <button @click="toggleActive(p)"
                  :class="p.is_active ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-700'"
                  class="text-xs px-3 py-1 rounded-lg hover:opacity-80 transition">
                  {{ p.is_active ? '停用' : '啟用' }}
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="providers.length === 0">
            <td colspan="5" class="px-6 py-10 text-center text-slate-400">無符合的教練</td>
          </tr>
        </tbody>
      </table>
    </div>
  </main>
</template>
