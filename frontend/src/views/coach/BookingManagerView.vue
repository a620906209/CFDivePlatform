<script setup>
import { ref, onMounted, computed } from 'vue'
import { getProviderBookings, confirmBooking, rejectBooking, cancelBooking, completeBooking } from '../../api/coachBookingApi'
import BookingChat from '../../components/BookingChat.vue'
import { useBookingUnreadCounts } from '../../composables/useBookingUnreadCounts'
import coachApi from '../../api/coachAxios'

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

// 依課程名稱分組，同課程再依時段日期排序
const groupedByOffer = computed(() => {
  const map = {}
  for (const b of bookings.value) {
    const key = b.offer_title || '未知課程'
    if (!map[key]) map[key] = []
    map[key].push(b)
  }
  // 每組內依日期排序
  for (const key of Object.keys(map)) {
    map[key].sort((a, b) => (a.scheduled_date + a.start_time).localeCompare(b.scheduled_date + b.start_time))
  }
  return map
})

const pendingCount = computed(() => bookings.value.filter(b => b.status === 'pending').length)
const chatExpanded = ref(new Set())

const { counts: unreadCounts, clearCount, startPolling } = useBookingUnreadCounts(coachApi)

function toggleChat(id) {
  if (chatExpanded.value.has(id)) chatExpanded.value.delete(id)
  else chatExpanded.value.add(id)
}

function canChat(status) {
  return status === 'confirmed' || status === 'completed'
}

onMounted(() => {
  fetchBookings()
  startPolling()
})

async function fetchBookings() {
  loading.value = true
  try {
    const res = await getProviderBookings()
    bookings.value = res.data.data
  } finally {
    loading.value = false
  }
}

async function doAction(booking, action) {
  const labels = { confirm: '確認', reject: '拒絕', cancel: '取消' }
  if (!confirm(`確定要${labels[action]}此預約？`)) return
  try {
    if (action === 'confirm')  await confirmBooking(booking.id)
    if (action === 'reject')   await rejectBooking(booking.id)
    if (action === 'cancel')   await cancelBooking(booking.id)
    if (action === 'complete') await completeBooking(booking.id)
    await fetchBookings()
  } catch (e) {
    alert(e.response?.data?.message || '操作失敗')
  }
}
</script>

<template>
  <div class="p-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-800">預約管理</h1>
      <span v-if="pendingCount > 0"
        class="bg-yellow-100 text-yellow-700 text-sm font-medium px-3 py-1 rounded-full">
        {{ pendingCount }} 筆待確認
      </span>
    </div>

    <div v-if="loading" class="text-center text-gray-400 py-20">載入中...</div>
    <div v-else-if="bookings.length === 0" class="text-center text-gray-400 py-20">目前沒有任何預約</div>

    <div v-else class="space-y-8">
      <!-- 依課程分組 -->
      <div v-for="(group, offerTitle) in groupedByOffer" :key="offerTitle">

        <!-- 課程標題列 -->
        <div class="flex items-center gap-3 mb-3">
          <div class="h-px flex-1 bg-gray-200"></div>
          <h2 class="text-sm font-semibold text-gray-500 whitespace-nowrap px-1">🤿 {{ offerTitle }}</h2>
          <div class="h-px flex-1 bg-gray-200"></div>
        </div>

        <!-- 同課程的預約列表 -->
        <div class="space-y-2">
          <div
            v-for="b in group"
            :key="b.id"
            class="bg-white rounded-xl border overflow-hidden"
            :class="b.status === 'pending' ? 'border-yellow-200 shadow-sm' : 'border-gray-100'"
          >
            <div class="px-5 py-4 flex items-start justify-between flex-wrap gap-3">
              <div class="min-w-0">
                <p class="text-sm font-medium text-gray-700">
                  {{ b.scheduled_date }} {{ b.start_time }}
                </p>
                <p class="text-sm text-gray-500 mt-0.5">
                  {{ b.member_name }}
                  <span class="text-gray-400">（{{ b.member_email }}）</span>
                  ・{{ b.participants }} 人・NT$ {{ b.total_price?.toLocaleString() }}
                </p>
                <p v-if="b.notes" class="text-xs text-gray-400 mt-1">備注：{{ b.notes }}</p>
              </div>

              <div class="flex flex-col items-end gap-2 shrink-0">
                <span class="text-xs px-3 py-1 rounded-full font-medium" :class="STATUS_LABEL[b.status]?.color">
                  {{ STATUS_LABEL[b.status]?.text || b.status }}
                </span>
                <div class="flex gap-2 flex-wrap justify-end">
                  <button v-if="b.status === 'pending'" @click="doAction(b, 'confirm')"
                    class="text-xs bg-green-600 hover:bg-green-500 text-white px-3 py-1 rounded-full transition">
                    確認
                  </button>
                  <button v-if="b.status === 'pending'" @click="doAction(b, 'reject')"
                    class="text-xs bg-red-500 hover:bg-red-400 text-white px-3 py-1 rounded-full transition">
                    拒絕
                  </button>
                  <button v-if="b.status === 'confirmed'" @click="doAction(b, 'complete')"
                    class="text-xs bg-blue-600 hover:bg-blue-500 text-white px-3 py-1 rounded-full transition">
                    完成
                  </button>
                  <button v-if="b.status === 'confirmed'" @click="doAction(b, 'cancel')"
                    class="text-xs text-orange-500 hover:text-orange-700 underline">
                    取消
                  </button>
                  <button v-if="canChat(b.status)" @click="toggleChat(b.id)"
                    class="relative text-xs border px-3 py-1 rounded-full transition"
                    :class="chatExpanded.has(b.id)
                      ? 'border-blue-400 text-blue-600'
                      : 'border-gray-300 hover:border-blue-400 hover:text-blue-600 text-gray-600'"
                  >
                    {{ chatExpanded.has(b.id) ? '收起訊息' : '訊息' }}
                    <!-- 未讀紅點 -->
                    <span
                      v-if="(unreadCounts[b.id] ?? 0) > 0 && !chatExpanded.has(b.id)"
                      class="absolute -top-1 -right-1 min-w-[1rem] h-4 flex items-center justify-center bg-red-500 text-white text-[9px] font-bold rounded-full px-0.5"
                    >{{ unreadCounts[b.id] }}</span>
                  </button>
                </div>
              </div>
            </div>

            <!-- 即時訊息（confirmed / completed，點擊展開） -->
            <div v-if="canChat(b.status) && chatExpanded.has(b.id)" class="border-t border-gray-100 p-4">
              <BookingChat
                :bookingId="b.id"
                :bookingStatus="b.status"
                currentUserType="provider"
                @read="clearCount(b.id)"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
