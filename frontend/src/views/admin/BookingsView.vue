<script setup>
import { ref, onMounted } from 'vue'
import adminApi from '../../api/adminAxios'

const bookings = ref([])
const loading  = ref(true)

const STATUS_LABEL = {
  pending:            { text: '待確認',   color: 'bg-yellow-100 text-yellow-700' },
  confirmed:          { text: '已確認',   color: 'bg-green-100 text-green-700' },
  completed:          { text: '已完成',   color: 'bg-gray-100 text-gray-600' },
  rejected:           { text: '已拒絕',   color: 'bg-red-100 text-red-600' },
  expired:            { text: '已過期',   color: 'bg-gray-100 text-gray-400' },
  member_cancelled:   { text: '學員取消', color: 'bg-gray-100 text-gray-500' },
  provider_cancelled: { text: '教練取消', color: 'bg-orange-100 text-orange-600' },
}

onMounted(async () => {
  try {
    const res = await adminApi.get('/admin/bookings')
    bookings.value = res.data.data
  } finally {
    loading.value = false
  }
})

async function doComplete(booking) {
  if (!confirm(`確定要將「${booking.member_name}」的預約標記為完成？`)) return
  try {
    await adminApi.put(`/admin/bookings/${booking.id}/complete`)
    booking.status = 'completed'
  } catch (e) {
    alert(e.response?.data?.message || '操作失敗')
  }
}
</script>

<template>
  <div class="p-6 max-w-6xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">預約管理</h1>

    <div v-if="loading" class="text-center text-gray-400 py-20">載入中...</div>
    <div v-else-if="bookings.length === 0" class="text-center text-gray-400 py-20">目前沒有預約</div>

    <div v-else class="bg-white rounded-2xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-5 py-3 text-left">課程</th>
            <th class="px-5 py-3 text-left">學員</th>
            <th class="px-5 py-3 text-left">日期</th>
            <th class="px-5 py-3 text-center">人數</th>
            <th class="px-5 py-3 text-right">金額</th>
            <th class="px-5 py-3 text-center">狀態</th>
            <th class="px-5 py-3 text-center">操作</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="b in bookings" :key="b.id" class="hover:bg-gray-50">
            <td class="px-5 py-3 font-medium text-gray-800 max-w-[140px] truncate">{{ b.offer_title }}</td>
            <td class="px-5 py-3 text-gray-500 text-xs">
              <p>{{ b.member_name }}</p>
              <p class="text-gray-400">{{ b.member_email }}</p>
            </td>
            <td class="px-5 py-3 text-gray-500 text-xs">{{ b.scheduled_date }} {{ b.start_time }}</td>
            <td class="px-5 py-3 text-center text-gray-600">{{ b.participants }}</td>
            <td class="px-5 py-3 text-right text-gray-700">NT$ {{ b.total_price?.toLocaleString() }}</td>
            <td class="px-5 py-3 text-center">
              <span class="text-xs px-2 py-1 rounded-full font-medium" :class="STATUS_LABEL[b.status]?.color">
                {{ STATUS_LABEL[b.status]?.text || b.status }}
              </span>
            </td>
            <td class="px-5 py-3 text-center">
              <button v-if="b.status === 'confirmed'" @click="doComplete(b)"
                class="text-xs bg-blue-600 hover:bg-blue-500 text-white px-3 py-1 rounded-full transition">
                標記完成
              </button>
              <span v-else class="text-gray-300 text-xs">—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
