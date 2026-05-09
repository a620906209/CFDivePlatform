<script setup>
import { ref, onMounted } from 'vue'
import adminApi from '../../api/adminAxios'

const stats   = ref(null)
const loading = ref(true)

onMounted(async () => {
  try {
    const res = await adminApi.get('/admin/stats')
    stats.value = res.data.data
  } finally {
    loading.value = false
  }
})

const cards = [
  { key: 'total_members',   label: '總會員數', icon: '👤', color: 'bg-blue-50 text-blue-700' },
  { key: 'total_providers', label: '總教練數', icon: '🤿', color: 'bg-teal-50 text-teal-700' },
  { key: 'total_offers',    label: '總課程數', icon: '📋', color: 'bg-purple-50 text-purple-700' },
]
</script>

<template>
  <main class="max-w-5xl mx-auto px-4 py-10">
    <h1 class="text-2xl font-bold text-slate-800 mb-8">平台總覽</h1>

    <div v-if="loading" class="text-center text-slate-400 py-20">載入中...</div>

    <div v-else class="grid sm:grid-cols-3 gap-6">
      <div v-for="card in cards" :key="card.key"
        class="bg-white rounded-2xl shadow p-6 flex items-center gap-4">
        <div :class="['text-3xl w-14 h-14 rounded-xl flex items-center justify-center', card.color]">
          {{ card.icon }}
        </div>
        <div>
          <p class="text-sm text-slate-500">{{ card.label }}</p>
          <p class="text-3xl font-bold text-slate-800">{{ stats?.[card.key] ?? '-' }}</p>
        </div>
      </div>
    </div>
  </main>
</template>
