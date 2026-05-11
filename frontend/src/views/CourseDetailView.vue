<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api/axios'
import { getSchedulesByOffer } from '../api/scheduleApi'
import { createBooking } from '../api/bookingApi'
import { useAuthStore } from '../stores/auth'

const route  = useRoute()
const router = useRouter()
const auth   = useAuthStore()

const offer     = ref(null)
const loading   = ref(true)
const notFound  = ref(false)
const schedules = ref([])
const selected  = ref(null)
const participants = ref(1)
const booking   = ref({ loading: false, success: false, error: '' })

onMounted(async () => {
  try {
    const res = await api.get(`/diving-offers/${route.params.id}`)
    offer.value = res.data.data
    const sRes = await getSchedulesByOffer(route.params.id)
    schedules.value = sRes.data.data
  } catch (e) {
    notFound.value = true
  } finally {
    loading.value = false
  }
})

async function submitBooking() {
  if (!selected.value) return
  booking.value = { loading: true, success: false, error: '' }
  try {
    await createBooking({ schedule_id: selected.value.id, participants: participants.value })
    booking.value.success = true
  } catch (e) {
    booking.value.error = e.response?.data?.message || '預約失敗，請稍後再試'
  } finally {
    booking.value.loading = false
  }
}
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

      <div class="flex items-center justify-between bg-ocean-50 rounded-2xl p-6 mb-6">
        <div>
          <p class="text-sm text-gray-500">課程費用</p>
          <p class="text-3xl font-bold text-ocean-800">NT$ {{ offer.price.toLocaleString() }}</p>
        </div>
      </div>

      <!-- 可用時段 -->
      <div class="bg-white rounded-2xl shadow p-6 mb-6">
        <div class="flex items-start gap-2 mb-4">
          <h2 class="text-lg font-semibold text-gray-800">可預約時段</h2>
        </div>
        <div class="flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-4 text-sm text-amber-700">
          <span>⚠️</span>
          <span>送出預約後需等待教練確認，確認後才算預約成功。</span>
        </div>

        <div v-if="schedules.length === 0" class="text-gray-400 text-sm">目前沒有開放時段</div>

        <div v-else class="space-y-3">
          <label
            v-for="s in schedules"
            :key="s.id"
            class="flex items-center justify-between border rounded-xl px-4 py-3 cursor-pointer transition"
            :class="selected?.id === s.id ? 'border-ocean-600 bg-ocean-50' : 'border-gray-200 hover:border-ocean-400'"
          >
            <div class="flex items-center gap-3">
              <input type="radio" :value="s" v-model="selected" class="accent-ocean-600" />
              <div>
                <p class="font-medium text-gray-800">{{ s.scheduled_date }} {{ s.start_time }}</p>
                <p class="text-sm text-gray-500">剩餘名額：{{ s.remaining_spots }} 人</p>
              </div>
            </div>
            <p class="text-ocean-700 font-semibold">NT$ {{ (offer.price * participants).toLocaleString() }}</p>
          </label>
        </div>

        <!-- 人數選擇與預約按鈕 -->
        <div v-if="selected" class="mt-5 border-t pt-4">
          <div class="flex items-center gap-4 mb-4">
            <label class="text-sm text-gray-600">預約人數</label>
            <input
              v-model.number="participants"
              type="number"
              min="1"
              :max="selected.remaining_spots"
              class="border rounded-lg px-3 py-1 w-20 text-center"
            />
          </div>

          <div v-if="booking.success" class="text-green-600 text-sm mb-3">✓ 預約已送出！請等待教練確認。前往 <RouterLink to="/my-bookings" class="underline">我的預約</RouterLink> 查看。</div>
          <div v-if="booking.error" class="text-red-500 text-sm mb-3">{{ booking.error }}</div>

          <div v-if="!auth.isLoggedIn" class="text-sm text-gray-500">
            請先 <RouterLink to="/login" class="text-ocean-600 underline">登入</RouterLink> 才能預約
          </div>
          <button
            v-else
            @click="submitBooking"
            :disabled="booking.loading || booking.success"
            class="w-full bg-ocean-700 hover:bg-ocean-600 disabled:opacity-50 text-white font-semibold py-3 rounded-full transition"
          >
            {{ booking.loading ? '送出中...' : booking.success ? '已送出預約' : '立即預約' }}
          </button>
        </div>
      </div>
    </template>

  </main>
</template>
