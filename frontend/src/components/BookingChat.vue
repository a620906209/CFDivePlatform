<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import api from '../api/axios'
import coachApi from '../api/coachAxios'
import echo from '../plugins/echo'
import { useNotificationStore } from '../stores/notifications'

const props = defineProps({
  bookingId: { type: Number, required: true },
  bookingStatus: { type: String, required: true },
  currentUserType: { type: String, required: true }, // 'member' | 'provider'
})

const emit = defineEmits(['read'])

const messages = ref([])
const messageListRef = ref(null)
const textInput = ref('')
const isSending = ref(false)
const otherUserOnline = ref(false)
const channel = ref(null)

const notificationStore = useNotificationStore()

const isConfirmed = computed(() => props.bookingStatus === 'confirmed')
const isCompleted = computed(() => props.bookingStatus === 'completed')
const canSend    = computed(() => isConfirmed.value && !isSending.value && textInput.value.trim())
const otherType  = computed(() => props.currentUserType === 'member' ? 'provider' : 'member')

const axiosInstance = computed(() => props.currentUserType === 'provider' ? coachApi : api)

// 請求瀏覽器通知權限（只問一次）
async function requestBrowserNotificationPermission() {
  if ('Notification' in window && Notification.permission === 'default') {
    await Notification.requestPermission()
  }
}

// 使用者不在頁面時才推瀏覽器通知
function showBrowserNotification(msg) {
  if (!('Notification' in window) || Notification.permission !== 'granted') return
  if (!document.hidden) return   // 使用者正在看這個 tab，不需要

  const body = msg.type === 'image' ? '傳送了一張圖片' : msg.content
  new Notification('新訊息', {
    body,
    icon: '/favicon.ico',
    tag: `booking-chat-${props.bookingId}`,  // 同一個預約只顯示一則，不疊加
  })
}

function scrollToBottom() {
  nextTick(() => {
    if (messageListRef.value) {
      messageListRef.value.scrollTop = messageListRef.value.scrollHeight
    }
  })
}

async function loadHistory() {
  try {
    const res = await axiosInstance.value.get(`/bookings/${props.bookingId}/messages`)
    messages.value = res.data.data
    scrollToBottom()
    await markLastRead()
    // 使用者打開聊天室後已讀，立刻刷新 bell badge
    notificationStore.fetchUnreadCount()
  } catch (e) {
    // 403 means no access, silently ignore
  }
}

async function markLastRead() {
  if (!messages.value.length) return
  const lastId = messages.value[messages.value.length - 1].id
  try {
    await axiosInstance.value.post(`/bookings/${props.bookingId}/messages/read`, {
      last_read_message_id: lastId,
    })
    messages.value.forEach(m => {
      if (m.sender_type !== props.currentUserType && !m.read_at) {
        m.read_at = new Date().toISOString()
      }
    })
    emit('read')  // 通知父層清除未讀角標
  } catch (e) {}
}

async function sendText() {
  if (!canSend.value) return
  const content = textInput.value.trim()
  textInput.value = ''
  isSending.value = true
  try {
    await axiosInstance.value.post(`/bookings/${props.bookingId}/messages`, {
      type: 'text',
      content,
    })
  } catch (e) {
    textInput.value = content
  } finally {
    isSending.value = false
  }
}

async function sendImage(event) {
  if (!isConfirmed.value) return
  const file = event.target.files[0]
  if (!file) return
  event.target.value = ''

  const formData = new FormData()
  formData.append('type', 'image')
  formData.append('file', file)

  isSending.value = true
  try {
    await axiosInstance.value.post(`/bookings/${props.bookingId}/messages`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  } catch (e) {
    console.error('圖片上傳失敗', e)
  } finally {
    isSending.value = false
  }
}

function subscribeChannel() {
  channel.value = echo.join(`booking.${props.bookingId}`)
    .here(users => {
      otherUserOnline.value = users.some(u => u.user_type === otherType.value)
      // Reverb 不會發 member_added，主動 whisper 告知對方自己已上線
      channel.value?.whisper('presence', { user_type: props.currentUserType, online: true })
    })
    .joining(user => {
      if (user.user_type === otherType.value) otherUserOnline.value = true
    })
    .leaving(user => {
      if (user.user_type === otherType.value) otherUserOnline.value = false
    })
    .listenForWhisper('presence', (e) => {
      if (e.user_type === otherType.value) otherUserOnline.value = e.online
    })
    .listen('.MessageSent', async (e) => {
      messages.value.push({
        id: e.id,
        sender_id: e.sender_id,
        sender_type: e.sender_type,
        type: e.type,
        content: e.content,
        read_at: null,
        created_at: e.created_at,
      })
      scrollToBottom()
      if (e.sender_type !== props.currentUserType) {
        // 對方傳來的訊息：推瀏覽器通知、刷新 bell badge
        showBrowserNotification(e)
        notificationStore.fetchUnreadCount()
        await markLastRead()
      }
    })
    .listen('.MessageRead', (e) => {
      if (e.reader_type !== props.currentUserType) {
        messages.value.forEach(m => {
          if (m.sender_type === props.currentUserType && m.id <= e.last_read_message_id) {
            m.read_at = m.read_at || new Date().toISOString()
          }
        })
      }
    })
}

onMounted(async () => {
  await requestBrowserNotificationPermission()
  await loadHistory()
  if (isConfirmed.value) {
    subscribeChannel()
  }
})

onUnmounted(() => {
  if (channel.value) {
    channel.value.whisper('presence', { user_type: props.currentUserType, online: false })
    echo.leave(`booking.${props.bookingId}`)
  }
})
</script>

<template>
  <div v-if="isConfirmed || isCompleted" class="flex flex-col h-full border rounded-lg overflow-hidden">
    <!-- 頂部狀態列 -->
    <div class="flex items-center justify-between px-4 py-2 bg-gray-50 border-b text-sm">
      <span class="font-medium text-gray-700">訊息</span>
      <div v-if="isConfirmed" class="flex items-center gap-1.5">
        <span
          :class="otherUserOnline ? 'bg-green-400' : 'bg-gray-300'"
          class="w-2 h-2 rounded-full"
        />
        <span class="text-gray-500">{{ otherUserOnline ? '對方在線' : '對方離線' }}</span>
      </div>
      <span v-else class="text-gray-400">對話已封存</span>
    </div>

    <!-- 訊息列表 -->
    <div ref="messageListRef" class="flex-1 overflow-y-auto p-4 space-y-3 bg-white" style="max-height: 400px">
      <div v-if="messages.length === 0" class="text-center text-gray-400 text-sm py-8">
        尚無訊息
      </div>
      <div
        v-for="msg in messages"
        :key="msg.id"
        :class="msg.sender_type === currentUserType ? 'items-end' : 'items-start'"
        class="flex flex-col"
      >
        <div
          :class="msg.sender_type === currentUserType
            ? 'bg-blue-500 text-white rounded-br-none'
            : 'bg-gray-100 text-gray-800 rounded-bl-none'"
          class="max-w-[75%] px-3 py-2 rounded-2xl text-sm"
        >
          <img
            v-if="msg.type === 'image'"
            :src="msg.content"
            alt="圖片訊息"
            class="max-w-full rounded-lg"
            style="max-height: 200px; object-fit: contain"
          />
          <span v-else>{{ msg.content }}</span>
        </div>
        <div class="flex items-center gap-1 mt-0.5 text-[10px] text-gray-400">
          <span>{{ new Date(msg.created_at).toLocaleTimeString('zh-TW', { hour: '2-digit', minute: '2-digit' }) }}</span>
          <span v-if="msg.sender_type === currentUserType">
            {{ msg.read_at ? '已讀' : '未讀' }}
          </span>
        </div>
      </div>
    </div>

    <!-- 輸入區（僅 confirmed） -->
    <div v-if="isConfirmed" class="border-t bg-white p-3">
      <div class="flex items-end gap-2">
        <label class="flex-shrink-0 cursor-pointer text-gray-400 hover:text-blue-500 transition">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
          <input type="file" accept="image/*" class="hidden" @change="sendImage" :disabled="isSending" />
        </label>
        <textarea
          v-model="textInput"
          rows="1"
          placeholder="輸入訊息..."
          class="flex-1 resize-none rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:border-blue-400"
          style="max-height: 80px"
          @keydown.enter.exact.prevent="sendText"
        />
        <button
          @click="sendText"
          :disabled="!canSend"
          class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-blue-500 text-white disabled:opacity-40 transition hover:bg-blue-600"
        >
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- 封存提示（completed） -->
    <div v-if="isCompleted" class="border-t bg-gray-50 px-4 py-3 text-center text-sm text-gray-400">
      課程已結束，對話已封存
    </div>
  </div>
</template>
