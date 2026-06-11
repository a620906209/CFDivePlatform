<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api/axios'
import { getSchedulesByOffer } from '../api/scheduleApi'
import { createBooking } from '../api/bookingApi'
import { getReviews, createReview, updateReview, deleteReview, toggleHelpful } from '../api/reviewApi'
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

// 評價相關
const reviewSort    = ref('helpful')
const reviewSummary = ref(null)
const reviews       = ref([])
const myReview      = ref(null)
const reviewForm    = ref({ show: false, rating: 5, comment: '', saving: false, error: '' })
const editTarget    = ref(null)

onMounted(async () => {
  try {
    const res = await api.get(`/diving-offers/${route.params.id}`)
    offer.value = res.data.data
    const [sRes, rRes] = await Promise.all([
      getSchedulesByOffer(route.params.id),
      getReviews(route.params.id, reviewSort.value),
    ])
    schedules.value = sRes.data.data
    applyReviewData(rRes.data.data)
  } catch (e) {
    notFound.value = true
  } finally {
    loading.value = false
  }
})

function applyReviewData(data) {
  reviewSummary.value = data.summary
  reviews.value       = data.reviews
  myReview.value      = data.reviews.find(r => r.is_mine) || null
}

async function switchSort(sort) {
  reviewSort.value = sort
  const res = await getReviews(route.params.id, sort)
  applyReviewData(res.data.data)
}

async function submitReview() {
  reviewForm.value.saving = true
  reviewForm.value.error  = ''
  try {
    if (editTarget.value) {
      await updateReview(editTarget.value.id, { rating: reviewForm.value.rating, comment: reviewForm.value.comment })
    } else {
      await createReview({ diving_offer_id: offer.value.id, rating: reviewForm.value.rating, comment: reviewForm.value.comment })
    }
    reviewForm.value.show = false
    editTarget.value = null
    const res = await getReviews(route.params.id, reviewSort.value)
    applyReviewData(res.data.data)
  } catch (e) {
    reviewForm.value.error = e.response?.data?.message || '送出失敗'
  } finally {
    reviewForm.value.saving = false
  }
}

function openEdit(review) {
  editTarget.value = review
  reviewForm.value = { show: true, rating: review.rating, comment: review.comment, saving: false, error: '' }
}

async function doDeleteReview(review) {
  if (!confirm('確定要刪除此評價？')) return
  await deleteReview(review.id)
  const res = await getReviews(route.params.id, reviewSort.value)
  applyReviewData(res.data.data)
}

async function doToggleHelpful(review) {
  if (!auth.isLoggedIn) return
  const res = await toggleHelpful(review.id)
  review.helpful_count = res.data.data.helpful_count
  review.has_voted     = res.data.data.has_voted
}

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

      <!-- 封面大圖 -->
      <div class="rounded-2xl h-64 overflow-hidden mb-4">
        <img
          v-if="offer.cover_image_url"
          :src="offer.cover_image_url"
          :alt="offer.title"
          class="w-full h-full object-cover"
        />
        <div v-else class="bg-gradient-to-br from-ocean-700 to-ocean-500 h-full flex items-center justify-center text-white text-7xl">
          🤿
        </div>
      </div>

      <!-- 相簿縮圖列 -->
      <div v-if="offer.images && offer.images.length > 0" class="flex gap-2 mb-6">
        <a
          v-for="img in offer.images"
          :key="img.id"
          :href="img.url"
          target="_blank"
          class="w-24 h-20 rounded-xl overflow-hidden shrink-0 border-2 border-transparent hover:border-ocean-400 transition"
        >
          <img :src="img.url" loading="lazy" class="w-full h-full object-cover" />
        </a>
      </div>

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

      <!-- 評價區塊 -->
      <div class="bg-white rounded-2xl shadow p-6 mb-6">
        <!-- 標題 + 排序 -->
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
          <div>
            <h2 class="text-lg font-semibold text-gray-800">課程評價</h2>
            <p v-if="reviewSummary" class="text-sm text-gray-500 mt-0.5">
              ★ {{ reviewSummary.average }} · {{ reviewSummary.total }} 則評價
            </p>
          </div>
          <div class="flex gap-2 text-sm">
            <button v-for="s in [['helpful','最多幫助'],['rating','最高分'],['newest','最新']]" :key="s[0]"
              @click="switchSort(s[0])"
              :class="reviewSort === s[0]
                ? 'bg-ocean-700 text-white px-3 py-1 rounded-full'
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200 px-3 py-1 rounded-full transition'">
              {{ s[1] }}
            </button>
          </div>
        </div>

        <!-- 星等分布條 -->
        <div v-if="reviewSummary?.total > 0" class="space-y-1 mb-6">
          <div v-for="star in [5,4,3,2,1]" :key="star" class="flex items-center gap-2 text-sm">
            <span class="w-8 text-right text-gray-500">{{ star }}★</span>
            <div class="flex-1 bg-gray-100 rounded-full h-2">
              <div class="bg-yellow-400 h-2 rounded-full transition-all"
                :style="`width:${reviewSummary.total > 0 ? (reviewSummary.distribution[star] / reviewSummary.total * 100) : 0}%`">
              </div>
            </div>
            <span class="w-6 text-gray-400 text-xs">{{ reviewSummary.distribution[star] }}</span>
          </div>
        </div>

        <!-- 我的評價 / 新增表單 -->
        <div v-if="auth.isLoggedIn" class="mb-5">
          <div v-if="!myReview && !reviewForm.show">
            <button @click="reviewForm = { show: true, rating: 5, comment: '', saving: false, error: '' }; editTarget = null"
              class="text-sm text-ocean-600 hover:underline">
              + 撰寫評價
            </button>
          </div>
          <div v-if="reviewForm.show" class="border border-ocean-200 rounded-xl p-4 bg-ocean-50">
            <p class="text-sm font-medium text-gray-700 mb-3">{{ editTarget ? '修改評價' : '撰寫評價' }}</p>
            <!-- 星等選擇 -->
            <div class="flex gap-1 mb-3">
              <button v-for="n in [1,2,3,4,5]" :key="n" @click="reviewForm.rating = n"
                :class="n <= reviewForm.rating ? 'text-yellow-400' : 'text-gray-300'"
                class="text-2xl transition">★</button>
            </div>
            <textarea v-model="reviewForm.comment" rows="3" placeholder="分享你的課程體驗..."
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-ocean-400" />
            <p v-if="reviewForm.error" class="text-red-500 text-xs mt-1">{{ reviewForm.error }}</p>
            <div class="flex gap-2 mt-3">
              <button @click="submitReview" :disabled="reviewForm.saving"
                class="bg-ocean-700 text-white text-sm px-4 py-1.5 rounded-full hover:bg-ocean-600 transition disabled:opacity-60">
                {{ reviewForm.saving ? '送出中...' : '送出' }}
              </button>
              <button @click="reviewForm.show = false; editTarget = null"
                class="text-sm text-gray-500 hover:text-gray-700 px-4 py-1.5">取消</button>
            </div>
          </div>
        </div>

        <!-- 評價列表 -->
        <div v-if="reviews.length === 0" class="text-gray-400 text-sm py-4 text-center">尚無評價</div>
        <div v-else class="space-y-4">
          <div v-for="r in reviews" :key="r.id"
            class="pb-4 border-b border-gray-100 last:border-0">
            <div class="flex items-start justify-between">
              <div>
                <div class="flex items-center gap-2">
                  <span class="text-yellow-400 text-sm">{{ '★'.repeat(r.rating) }}{{ '☆'.repeat(5 - r.rating) }}</span>
                  <span class="text-xs text-gray-400">{{ r.reviewer_name }}</span>
                  <span v-if="r.is_edited" class="text-xs text-gray-400">（已修改）</span>
                </div>
                <p class="text-sm text-gray-700 mt-1 leading-relaxed">{{ r.comment }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ new Date(r.created_at).toLocaleDateString('zh-TW') }}</p>
              </div>
              <!-- 本人操作 -->
              <div v-if="r.is_mine" class="flex gap-2 text-xs ml-3 shrink-0">
                <button @click="openEdit(r)" class="text-ocean-600 hover:underline">修改</button>
                <button @click="doDeleteReview(r)" class="text-red-400 hover:underline">刪除</button>
              </div>
            </div>
            <!-- 有幫助 -->
            <button @click="doToggleHelpful(r)"
              :class="r.has_voted ? 'text-ocean-600' : 'text-gray-400 hover:text-gray-600'"
              class="mt-2 text-xs flex items-center gap-1 transition"
              :disabled="!auth.isLoggedIn">
              👍 有幫助 {{ r.helpful_count > 0 ? `(${r.helpful_count})` : '' }}
            </button>
          </div>
        </div>
      </div>
    </template>

  </main>
</template>
