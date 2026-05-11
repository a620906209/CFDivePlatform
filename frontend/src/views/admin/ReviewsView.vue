<script setup>
import { ref, onMounted } from 'vue'
import adminApi from '../../api/adminAxios'

const reviews = ref([])
const loading = ref(true)

onMounted(fetchReviews)

async function fetchReviews() {
  loading.value = true
  try {
    const res = await adminApi.get('/admin/reviews')
    reviews.value = res.data.data
  } finally {
    loading.value = false
  }
}

async function doDelete(review) {
  if (!confirm(`確定要刪除「${review.offer_title}」的這則評價？`)) return
  await adminApi.delete(`/admin/reviews/${review.id}`)
  reviews.value = reviews.value.filter(r => r.id !== review.id)
}
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">評價管理</h1>

    <div v-if="loading" class="text-center text-gray-400 py-20">載入中...</div>
    <div v-else-if="reviews.length === 0" class="text-center text-gray-400 py-20">目前沒有評價</div>

    <div v-else class="bg-white rounded-2xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-5 py-3 text-left">課程</th>
            <th class="px-5 py-3 text-left">會員</th>
            <th class="px-5 py-3 text-center">星等</th>
            <th class="px-5 py-3 text-left">內容</th>
            <th class="px-5 py-3 text-center">幫助</th>
            <th class="px-5 py-3 text-center">操作</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="r in reviews" :key="r.id" class="hover:bg-gray-50">
            <td class="px-5 py-3 font-medium text-gray-800 max-w-[140px] truncate">{{ r.offer_title }}</td>
            <td class="px-5 py-3 text-gray-500 text-xs">{{ r.member_email }}</td>
            <td class="px-5 py-3 text-center">
              <span class="text-yellow-400">{{ '★'.repeat(r.rating) }}</span>
              <span v-if="r.is_edited" class="text-gray-400 text-xs ml-1">（改）</span>
            </td>
            <td class="px-5 py-3 text-gray-600 max-w-[240px] truncate">{{ r.comment }}</td>
            <td class="px-5 py-3 text-center text-gray-400 text-xs">{{ r.helpful_count }}</td>
            <td class="px-5 py-3 text-center">
              <button @click="doDelete(r)"
                class="text-xs bg-red-50 hover:bg-red-100 text-red-600 px-3 py-1 rounded-lg transition">
                刪除
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
