<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api/axios'

const route  = useRoute()
const router = useRouter()

const offer   = ref(null)
const loading = ref(true)
const notFound = ref(false)

onMounted(async () => {
  try {
    const res = await api.get(`/diving-offers/${route.params.id}`)
    offer.value = res.data.data
  } catch (e) {
    notFound.value = true
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <main class="max-w-4xl mx-auto px-4 py-10">

    <div v-if="loading" class="text-center text-gray-400 py-20">載入中...</div>

    <div v-else-if="notFound" class="text-center py-20">
      <p class="text-5xl mb-4">🌊</p>
      <p class="text-gray-500 text-lg mb-6">課程不存在或已下架</p>
      <RouterLink to="/courses" class="text-ocean-600 hover:underline">← 返回課程列表</RouterLink>
    </div>

    <template v-else-if="offer">
      <RouterLink to="/courses" class="text-ocean-600 hover:underline text-sm mb-6 inline-block">
        ← 返回課程列表
      </RouterLink>

      <div class="bg-ocean-700 rounded-2xl h-56 flex items-center justify-center text-white text-7xl mb-6">🤿</div>

      <div class="flex flex-wrap gap-2 mb-3">
        <span
          v-for="badge in (offer.badges || [])"
          :key="badge"
          class="text-sm bg-ocean-100 text-ocean-700 px-3 py-1 rounded-full"
        >
          {{ badge }}
        </span>
        <span v-if="offer.tag" class="text-sm bg-gray-100 text-gray-600 px-3 py-1 rounded-full">
          {{ offer.tag }}
        </span>
      </div>

      <h1 class="text-3xl font-bold text-gray-800 mb-2">{{ offer.title }}</h1>

      <div class="flex flex-wrap gap-6 text-sm text-gray-500 mb-6">
        <span>📍 {{ offer.location }}・{{ offer.spot }}</span>
        <span>🗺️ {{ offer.region }}</span>
        <span>★ {{ offer.rating }} （{{ offer.reviews }} 則評論）</span>
      </div>

      <div class="bg-white rounded-2xl shadow p-6 mb-6">
        <p class="text-gray-700 leading-relaxed whitespace-pre-wrap">{{ offer.description || '暫無課程說明。' }}</p>
      </div>

      <div class="flex items-center justify-between bg-ocean-50 rounded-2xl p-6">
        <div>
          <p class="text-sm text-gray-500">課程費用</p>
          <p class="text-3xl font-bold text-ocean-800">NT$ {{ offer.price.toLocaleString() }}</p>
        </div>
        <button class="bg-ocean-700 hover:bg-ocean-600 text-white font-semibold px-8 py-3 rounded-full transition">
          立即洽詢
        </button>
      </div>
    </template>

  </main>
</template>
