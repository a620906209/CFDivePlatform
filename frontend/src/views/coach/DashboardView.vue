<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import coachApi from '../../api/coachAxios'

const router  = useRouter()
const offers  = ref([])
const loading = ref(true)
const error   = ref('')
const confirmId = ref(null)

async function fetchOffers() {
  loading.value = true
  try {
    const res  = await coachApi.get('/provider/offers')
    offers.value = res.data.data
  } catch {
    error.value = '無法載入課程列表'
  } finally {
    loading.value = false
  }
}

async function deleteOffer(id) {
  try {
    await coachApi.delete(`/provider/offers/${id}`)
    confirmId.value = null
    await fetchOffers()
  } catch (e) {
    alert(e.response?.data?.message || '刪除失敗')
  }
}

onMounted(fetchOffers)
</script>

<template>
  <main class="max-w-5xl mx-auto px-4 py-10">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-800">我的課程</h1>
      <RouterLink
        to="/coach/offers/new"
        class="bg-gray-900 hover:bg-gray-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition"
      >
        + 新增課程
      </RouterLink>
    </div>

    <div v-if="loading" class="text-center text-gray-400 py-20">載入中...</div>
    <div v-else-if="error" class="text-center text-red-500 py-20">{{ error }}</div>

    <div v-else-if="offers.length === 0" class="text-center py-20">
      <p class="text-5xl mb-4">🌊</p>
      <p class="text-gray-500 mb-4">尚無課程，立即新增第一堂課</p>
      <RouterLink to="/coach/offers/new"
        class="bg-gray-900 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition text-sm">
        新增課程
      </RouterLink>
    </div>

    <div v-else class="bg-white rounded-2xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-6 py-3 text-left">課程名稱</th>
            <th class="px-6 py-3 text-left">地點</th>
            <th class="px-6 py-3 text-left">地區</th>
            <th class="px-6 py-3 text-right">價格</th>
            <th class="px-6 py-3 text-center">操作</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="offer in offers" :key="offer.id" class="hover:bg-gray-50">
            <td class="px-6 py-4 font-medium text-gray-800">{{ offer.title }}</td>
            <td class="px-6 py-4 text-gray-500">{{ offer.location }}</td>
            <td class="px-6 py-4 text-gray-500">{{ offer.region }}</td>
            <td class="px-6 py-4 text-right font-medium">NT$ {{ offer.price?.toLocaleString() }}</td>
            <td class="px-6 py-4 text-center">
              <div class="flex justify-center gap-2">
                <RouterLink :to="`/coach/offers/${offer.id}/edit`"
                  class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1 rounded-lg transition">
                  編輯
                </RouterLink>
                <button @click="confirmId = offer.id"
                  class="text-xs bg-red-50 hover:bg-red-100 text-red-600 px-3 py-1 rounded-lg transition">
                  刪除
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 刪除確認 dialog -->
    <div v-if="confirmId" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl shadow-xl p-6 w-80">
        <p class="font-semibold text-gray-800 mb-2">確定要刪除這堂課程？</p>
        <p class="text-sm text-gray-500 mb-6">此操作無法復原。</p>
        <div class="flex gap-3 justify-end">
          <button @click="confirmId = null"
            class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition">
            取消
          </button>
          <button @click="deleteOffer(confirmId)"
            class="px-4 py-2 text-sm bg-red-600 hover:bg-red-500 text-white rounded-lg transition">
            確定刪除
          </button>
        </div>
      </div>
    </div>
  </main>
</template>
