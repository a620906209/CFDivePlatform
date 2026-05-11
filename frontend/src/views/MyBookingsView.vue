<script setup>
import { ref, onMounted } from 'vue'
import { getMyBookings, cancelBooking } from '../api/bookingApi'

const bookings = ref([])
const loading  = ref(true)
const error    = ref('')
const expanded = ref(new Set())

const STATUS_LABEL = {
  pending:            { text: '待教練確認', color: 'bg-yellow-100 text-yellow-700', hint: '等待教練確認中，確認後才完成預約' },
  confirmed:          { text: '預約成功',   color: 'bg-green-100 text-green-700',   hint: '教練已確認，請準時出席' },
  completed:          { text: '已完成',     color: 'bg-gray-100 text-gray-600',     hint: '' },
  rejected:           { text: '已拒絕',     color: 'bg-red-100 text-red-600',       hint: '教練無法接受此預約' },
  expired:            { text: '已過期',     color: 'bg-gray-100 text-gray-400',     hint: '超過 48 小時未獲確認，預約自動取消' },
  member_cancelled:   { text: '已取消',     color: 'bg-gray-100 text-gray-500',     hint: '' },
  provider_cancelled: { text: '教練取消',   color: 'bg-orange-100 text-orange-600', hint: '教練因故取消此預約' },
}

onMounted(async () => {
  try {
    const res = await getMyBookings()
    bookings.value = res.data.data
  } catch {
    error.value = '無法載入預約記錄'
  } finally {
    loading.value = false
  }
})

function toggle(id) {
  if (expanded.value.has(id)) expanded.value.delete(id)
  else expanded.value.add(id)
}

async function doCancel(booking) {
  if (!confirm('確定要取消此預約？')) return
  try {
    await cancelBooking(booking.id)
    booking.status = 'member_cancelled'
  } catch (e) {
    alert(e.response?.data?.message || '取消失敗')
  }
}

function canCancel(status) {
  return status === 'pending' || status === 'confirmed'
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return `${d.getFullYear()}/${String(d.getMonth()+1).padStart(2,'0')}/${String(d.getDate()).padStart(2,'0')}`
}
</script>

<template>
  <main class="max-w-3xl mx-auto px-4 py-10">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">我的預約</h1>

    <div v-if="loading" class="text-center text-gray-400 py-20">載入中...</div>
    <div v-else-if="error" class="text-center text-red-500 py-10">{{ error }}</div>
    <div v-else-if="bookings.length === 0" class="text-center text-gray-400 py-20">
      目前沒有預約記錄。<RouterLink to="/courses" class="text-ocean-600 underline">瀏覽課程</RouterLink>
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="b in bookings"
        :key="b.id"
        class="bg-white rounded-2xl shadow border border-gray-100 overflow-hidden"
      >
        <!-- 摘要列（點擊展開） -->
        <button
          class="w-full text-left px-5 py-4 flex items-center justify-between gap-4 hover:bg-gray-50 transition"
          @click="toggle(b.id)"
        >
          <div class="flex-1 min-w-0">
            <p class="font-semibold text-gray-800 truncate">{{ b.offer_title }}</p>
            <p class="text-sm text-gray-500 mt-0.5">
              {{ b.scheduled_date }} {{ b.start_time }}
              ・{{ b.participants }} 人
              ・NT$ {{ b.total_price?.toLocaleString() }}
            </p>
          </div>
          <div class="flex items-center gap-3 shrink-0">
            <span class="text-xs px-3 py-1 rounded-full font-medium" :class="STATUS_LABEL[b.status]?.color">
              {{ STATUS_LABEL[b.status]?.text || b.status }}
            </span>
            <span class="text-gray-400 text-sm">{{ expanded.has(b.id) ? '▲' : '▼' }}</span>
          </div>
        </button>

        <!-- 展開詳情 -->
        <div v-if="expanded.has(b.id)" class="border-t border-gray-100 px-5 py-4 space-y-4 bg-gray-50">

          <!-- 狀態說明 -->
          <div v-if="STATUS_LABEL[b.status]?.hint"
            class="flex items-center gap-2 text-sm rounded-lg px-3 py-2"
            :class="b.status === 'pending' ? 'bg-yellow-50 text-yellow-700' : b.status === 'confirmed' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'"
          >
            <span>{{ b.status === 'pending' ? '⏳' : b.status === 'confirmed' ? '✅' : 'ℹ️' }}</span>
            <span>{{ STATUS_LABEL[b.status].hint }}</span>
          </div>

          <!-- 課程與時段資訊 -->
          <div class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
            <div>
              <p class="text-gray-400 text-xs mb-0.5">課程名稱</p>
              <p class="text-gray-700 font-medium">{{ b.offer_title }}</p>
            </div>
            <div>
              <p class="text-gray-400 text-xs mb-0.5">地點</p>
              <p class="text-gray-700">{{ b.offer_location || '—' }}
                <span v-if="b.offer_region" class="text-gray-400">・{{ b.offer_region }}</span>
              </p>
            </div>
            <div>
              <p class="text-gray-400 text-xs mb-0.5">上課日期</p>
              <p class="text-gray-700">{{ b.scheduled_date }} {{ b.start_time }}</p>
            </div>
            <div>
              <p class="text-gray-400 text-xs mb-0.5">預約人數</p>
              <p class="text-gray-700">{{ b.participants }} 人</p>
            </div>
            <div>
              <p class="text-gray-400 text-xs mb-0.5">課程單價</p>
              <p class="text-gray-700">NT$ {{ b.offer_price?.toLocaleString() }}</p>
            </div>
            <div>
              <p class="text-gray-400 text-xs mb-0.5">總金額</p>
              <p class="text-gray-800 font-semibold">NT$ {{ b.total_price?.toLocaleString() }}</p>
            </div>
            <div v-if="b.notes" class="col-span-2">
              <p class="text-gray-400 text-xs mb-0.5">備注</p>
              <p class="text-gray-600">{{ b.notes }}</p>
            </div>
            <div class="col-span-2">
              <p class="text-gray-400 text-xs mb-0.5">預約時間</p>
              <p class="text-gray-500 text-xs">{{ b.created_at ? new Date(b.created_at).toLocaleString('zh-TW') : '—' }}</p>
            </div>
          </div>

          <!-- 操作按鈕列 -->
          <div class="flex items-center justify-between pt-1">
            <RouterLink
              v-if="b.offer_id"
              :to="`/courses/${b.offer_id}`"
              class="text-sm text-ocean-600 hover:text-ocean-800 hover:underline"
            >
              查看課程介紹 →
            </RouterLink>
            <span v-else></span>
            <button
              v-if="canCancel(b.status)"
              @click="doCancel(b)"
              class="text-sm text-red-500 hover:text-red-700 underline"
            >
              取消預約
            </button>
          </div>
        </div>
      </div>
    </div>
  </main>
</template>
