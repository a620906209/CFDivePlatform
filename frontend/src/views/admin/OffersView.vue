<script setup>
import { ref, onMounted } from 'vue'
import adminApi from '../../api/adminAxios'

const offers    = ref([])
const loading   = ref(true)
const search    = ref('')
const confirmId = ref(null)

async function fetchOffers() {
  loading.value = true
  try {
    const params = {}
    if (search.value) params.q = search.value
    const res = await adminApi.get('/admin/offers', { params })
    offers.value = res.data.data
  } finally {
    loading.value = false
  }
}

async function deleteOffer(id) {
  try {
    await adminApi.delete(`/admin/offers/${id}`)
    confirmId.value = null
    await fetchOffers()
  } catch (e) { alert(e.response?.data?.message || '刪除失敗') }
}

onMounted(fetchOffers)
</script>

<template>
  <main class="max-w-6xl mx-auto px-4 py-10">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">課程管理</h1>

    <div class="flex gap-3 mb-6">
      <input v-model="search" @keyup.enter="fetchOffers" type="text" placeholder="搜尋課程名稱或地點..."
        class="flex-1 border border-slate-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-400" />
      <button @click="fetchOffers"
        class="bg-slate-800 text-white px-5 py-2 rounded-lg text-sm hover:bg-slate-700 transition">搜尋</button>
    </div>

    <div v-if="loading" class="text-center text-slate-400 py-20">載入中...</div>

    <div v-else class="bg-white rounded-2xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-6 py-3 text-left">課程名稱</th>
            <th class="px-6 py-3 text-left">地點</th>
            <th class="px-6 py-3 text-left">地區</th>
            <th class="px-6 py-3 text-right">價格</th>
            <th class="px-6 py-3 text-center">教練 ID</th>
            <th class="px-6 py-3 text-center">操作</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="offer in offers" :key="offer.id" class="hover:bg-slate-50">
            <td class="px-6 py-4 font-medium text-slate-800">{{ offer.title }}</td>
            <td class="px-6 py-4 text-slate-500">{{ offer.location }}</td>
            <td class="px-6 py-4 text-slate-500">{{ offer.region }}</td>
            <td class="px-6 py-4 text-right">NT$ {{ offer.price?.toLocaleString() }}</td>
            <td class="px-6 py-4 text-center text-slate-400">{{ offer.provider_id ?? '-' }}</td>
            <td class="px-6 py-4 text-center">
              <button @click="confirmId = offer.id"
                class="text-xs bg-red-50 hover:bg-red-100 text-red-600 px-3 py-1 rounded-lg transition">
                刪除
              </button>
            </td>
          </tr>
          <tr v-if="offers.length === 0">
            <td colspan="6" class="px-6 py-10 text-center text-slate-400">無符合的課程</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 刪除確認 dialog -->
    <div v-if="confirmId" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl shadow-xl p-6 w-80">
        <p class="font-semibold text-slate-800 mb-2">確定要刪除此課程？</p>
        <p class="text-sm text-slate-500 mb-6">此操作無法復原。</p>
        <div class="flex gap-3 justify-end">
          <button @click="confirmId = null"
            class="px-4 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 transition">取消</button>
          <button @click="deleteOffer(confirmId)"
            class="px-4 py-2 text-sm bg-red-600 hover:bg-red-500 text-white rounded-lg transition">確定刪除</button>
        </div>
      </div>
    </div>
  </main>
</template>
