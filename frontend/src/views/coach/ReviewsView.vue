<script setup>
import { ref, onMounted } from 'vue'
import coachApi from '../../api/coachAxios'
import axios from 'axios'

const publicApi = axios.create({
  baseURL: import.meta.env.VITE_API_URL + '/api',
  headers: { Accept: 'application/json' },
})

const offers   = ref([])
const reviews  = ref([])   // [{ offer, reviews, summary }]
const loading  = ref(true)

onMounted(async () => {
  try {
    const offersRes = await coachApi.get('/provider/offers')
    offers.value = offersRes.data.data

    const results = await Promise.all(
      offers.value.map(async (offer) => {
        const res = await publicApi.get(`/diving-offers/${offer.id}/reviews`)
        return { offer, ...res.data.data }
      })
    )
    // 只顯示有評價的課程
    reviews.value = results.filter(r => r.summary.total > 0)
  } finally {
    loading.value = false
  }
})

function stars(n) {
  return '★'.repeat(n) + '☆'.repeat(5 - n)
}
</script>

<template>
  <div class="p-6 max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-2">課程評價</h1>
    <p class="text-sm text-gray-500 mb-6">學員對你課程的回饋（評價人已匿名）</p>

    <div v-if="loading" class="text-center text-gray-400 py-20">載入中...</div>

    <div v-else-if="reviews.length === 0" class="text-center text-gray-400 py-20">
      目前沒有學員評價
    </div>

    <div v-else class="space-y-8">
      <div v-for="group in reviews" :key="group.offer.id" class="bg-white rounded-2xl shadow p-6">

        <!-- 課程標題與統計 -->
        <div class="flex items-start justify-between mb-4 flex-wrap gap-3">
          <div>
            <h2 class="text-lg font-semibold text-gray-800">{{ group.offer.title }}</h2>
            <p class="text-sm text-gray-500 mt-0.5">
              ★ {{ group.summary.average }} · {{ group.summary.total }} 則評價
            </p>
          </div>
          <!-- 評分分布 -->
          <div class="space-y-0.5 min-w-[160px]">
            <div v-for="star in [5,4,3,2,1]" :key="star" class="flex items-center gap-1.5 text-xs">
              <span class="text-gray-400 w-4">{{ star }}★</span>
              <div class="flex-1 bg-gray-100 rounded-full h-1.5">
                <div class="bg-yellow-400 h-1.5 rounded-full"
                  :style="`width:${group.summary.total > 0 ? (group.summary.distribution[star] / group.summary.total * 100) : 0}%`">
                </div>
              </div>
              <span class="text-gray-400 w-3 text-right">{{ group.summary.distribution[star] }}</span>
            </div>
          </div>
        </div>

        <!-- 評價列表 -->
        <div class="divide-y divide-gray-100">
          <div v-for="r in group.reviews" :key="r.id" class="py-4 first:pt-0">
            <div class="flex items-start gap-3">
              <div class="w-8 h-8 rounded-full bg-ocean-100 flex items-center justify-center text-ocean-600 text-sm font-bold shrink-0">
                匿
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                  <span class="text-yellow-400 text-sm">{{ stars(r.rating) }}</span>
                  <span class="text-xs text-gray-400">{{ r.reviewer_name }}</span>
                  <span v-if="r.is_edited" class="text-xs text-gray-400">（已修改）</span>
                  <span class="text-xs text-gray-400 ml-auto">
                    {{ new Date(r.created_at).toLocaleDateString('zh-TW') }}
                  </span>
                </div>
                <p class="text-sm text-gray-700 leading-relaxed">{{ r.comment }}</p>
                <p class="text-xs text-gray-400 mt-1.5">
                  👍 {{ r.helpful_count }} 人覺得有幫助
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
