<script setup>
import { ref, onMounted } from 'vue'
import api from '../api/axios'
import CourseCard from '../components/CourseCard.vue'

const offers  = ref([])
const meta    = ref(null)
const loading = ref(false)
const error   = ref('')

const search = ref('')
const region = ref('')

const REGIONS = ['北部', '中部', '南部', '東部', '離島']

async function fetchOffers(page = 1) {
  loading.value = true
  error.value   = ''
  try {
    const params = { page, per_page: 12 }
    if (search.value) params.q      = search.value
    if (region.value) params.region = region.value

    const res  = await api.get('/diving-offers', { params })
    offers.value = res.data.data
    meta.value   = res.data.meta
  } catch {
    error.value = '無法載入課程，請稍後再試。'
  } finally {
    loading.value = false
  }
}

function onSearch() { fetchOffers(1) }
function onRegion() { fetchOffers(1) }

onMounted(() => fetchOffers())
</script>

<template>
  <main class="max-w-6xl mx-auto px-4 py-10">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">探索潛水課程</h1>

    <div class="flex flex-col sm:flex-row gap-3 mb-8">
      <input
        v-model="search"
        @keyup.enter="onSearch"
        type="text"
        placeholder="搜尋課程名稱、地點..."
        class="flex-1 border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-ocean-400"
      />
      <button
        @click="onSearch"
        class="bg-ocean-700 text-white px-6 py-2 rounded-lg hover:bg-ocean-600 transition"
      >
        搜尋
      </button>
      <select
        v-model="region"
        @change="onRegion"
        class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-ocean-400"
      >
        <option value="">所有地區</option>
        <option v-for="r in REGIONS" :key="r" :value="r">{{ r }}</option>
      </select>
    </div>

    <div v-if="loading" class="text-center text-gray-400 py-20">載入中...</div>

    <div v-else-if="error" class="text-center text-red-500 py-20">{{ error }}</div>

    <div v-else-if="offers.length === 0" class="text-center text-gray-400 py-20">
      😢 找不到符合的課程，試試其他關鍵字
    </div>

    <div v-else class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <CourseCard v-for="offer in offers" :key="offer.id" :offer="offer" />
    </div>

    <div v-if="meta && meta.last_page > 1" class="flex justify-center gap-2 mt-10">
      <button
        v-for="p in meta.last_page"
        :key="p"
        @click="fetchOffers(p)"
        :class="[
          'px-3 py-1 rounded-lg border transition',
          p === meta.current_page
            ? 'bg-ocean-700 text-white border-ocean-700'
            : 'border-gray-300 text-gray-600 hover:bg-gray-100'
        ]"
      >
        {{ p }}
      </button>
    </div>
  </main>
</template>
