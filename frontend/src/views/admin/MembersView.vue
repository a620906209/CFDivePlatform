<script setup>
import { ref, onMounted } from 'vue'
import adminApi from '../../api/adminAxios'

const members = ref([])
const loading = ref(true)
const search  = ref('')

async function fetchMembers() {
  loading.value = true
  try {
    const params = {}
    if (search.value) params.q = search.value
    const res = await adminApi.get('/admin/members', { params })
    members.value = res.data.data
  } finally {
    loading.value = false
  }
}

async function toggleActive(member) {
  try {
    const res = await adminApi.put(`/admin/members/${member.id}/toggle-active`)
    member.is_active = res.data.data.is_active
  } catch (e) {
    alert(e.response?.data?.message || '操作失敗')
  }
}

onMounted(fetchMembers)
</script>

<template>
  <main class="max-w-6xl mx-auto px-4 py-10">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">會員管理</h1>

    <div class="flex gap-3 mb-6">
      <input v-model="search" @keyup.enter="fetchMembers" type="text" placeholder="搜尋姓名或 Email..."
        class="flex-1 border border-slate-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-400" />
      <button @click="fetchMembers"
        class="bg-slate-800 text-white px-5 py-2 rounded-lg text-sm hover:bg-slate-700 transition">搜尋</button>
    </div>

    <div v-if="loading" class="text-center text-slate-400 py-20">載入中...</div>

    <div v-else class="bg-white rounded-2xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-6 py-3 text-left">姓名</th>
            <th class="px-6 py-3 text-left">Email</th>
            <th class="px-6 py-3 text-left">註冊時間</th>
            <th class="px-6 py-3 text-center">狀態</th>
            <th class="px-6 py-3 text-center">操作</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="m in members" :key="m.id" class="hover:bg-slate-50">
            <td class="px-6 py-4 font-medium text-slate-800">{{ m.name }}</td>
            <td class="px-6 py-4 text-slate-500">{{ m.email }}</td>
            <td class="px-6 py-4 text-slate-400 text-xs">{{ m.created_at?.slice(0,10) }}</td>
            <td class="px-6 py-4 text-center">
              <span :class="m.is_active
                ? 'bg-green-100 text-green-700'
                : 'bg-red-100 text-red-600'"
                class="text-xs px-2 py-1 rounded-full font-medium">
                {{ m.is_active ? '啟用' : '停用' }}
              </span>
            </td>
            <td class="px-6 py-4 text-center">
              <button @click="toggleActive(m)"
                :class="m.is_active
                  ? 'bg-red-50 text-red-600 hover:bg-red-100'
                  : 'bg-green-50 text-green-700 hover:bg-green-100'"
                class="text-xs px-3 py-1 rounded-lg transition">
                {{ m.is_active ? '停用' : '啟用' }}
              </button>
            </td>
          </tr>
          <tr v-if="members.length === 0">
            <td colspan="5" class="px-6 py-10 text-center text-slate-400">無符合的會員</td>
          </tr>
        </tbody>
      </table>
    </div>
  </main>
</template>
