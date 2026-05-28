import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '../api/notificationAxios'
import echo from '../plugins/echo'

export const useNotificationStore = defineStore('notifications', () => {
  const unreadCount   = ref(0)
  const notifications = ref([])
  const isOpen        = ref(false)

  let intervalId         = null
  let currentInterval    = null
  let visibilityHandler  = null
  let privateChannel     = null   // 訂閱 private user channel 用

  async function fetchUnreadCount() {
    try {
      const res = await api.get('/notifications/unread-count')
      const newCount = res.data?.data?.count ?? 0
      if (newCount !== unreadCount.value) {
        const wasZero = unreadCount.value === 0
        unreadCount.value = newCount
        if ((wasZero && newCount > 0) || (!wasZero && newCount === 0)) {
          restartInterval()
        }
      }
    } catch (e) {
      console.error('[NotificationStore] fetchUnreadCount failed:', e?.response?.status, e?.message)
    }
  }

  async function fetchNotifications() {
    try {
      const res = await api.get('/notifications')
      notifications.value = res.data.data
      unreadCount.value   = res.data.unread_count
    } catch (e) {
      console.error('[NotificationStore] fetchNotifications failed:', e?.response?.status, e?.message)
    }
  }

  function getInterval() {
    return unreadCount.value > 0 ? 30000 : 60000
  }

  function restartInterval() {
    if (intervalId) clearInterval(intervalId)
    const ms = getInterval()
    currentInterval = ms
    intervalId = setInterval(fetchUnreadCount, ms)
  }

  function startPolling() {
    fetchUnreadCount()
    restartInterval()

    visibilityHandler = () => {
      if (document.visibilityState === 'hidden') {
        if (intervalId) clearInterval(intervalId)
        intervalId = null
      } else {
        fetchUnreadCount()
        restartInterval()
      }
    }
    document.addEventListener('visibilitychange', visibilityHandler)
  }

  function stopPolling() {
    if (intervalId) clearInterval(intervalId)
    intervalId = null
    if (visibilityHandler) {
      document.removeEventListener('visibilitychange', visibilityHandler)
      visibilityHandler = null
    }
    unreadCount.value   = 0
    notifications.value = []
    isOpen.value        = false
  }

  /**
   * 訂閱使用者的 private channel，收到 notification.created 立刻更新 bell。
   * 在 startPolling() 後呼叫，需要傳入 userId。
   */
  let realtimeUserId = null

  function startRealtime(userId) {
    if (!userId) return
    stopRealtime()   // 防止重複訂閱
    realtimeUserId = userId
    privateChannel = echo
      .private(`App.Models.User.${userId}`)
      .listen('.notification.created', () => {
        fetchUnreadCount()
      })
  }

  function stopRealtime() {
    if (realtimeUserId) {
      echo.leave(`App.Models.User.${realtimeUserId}`)
      realtimeUserId = null
      privateChannel = null
    }
  }

  async function markRead(id) {
    const n = notifications.value.find(n => n.id === id)
    if (n && !n.read_at) {
      n.read_at = new Date().toISOString()
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    }
    try {
      await api.patch(`/notifications/${id}/read`)
    } catch {}
  }

  async function markAllRead() {
    notifications.value.forEach(n => {
      if (!n.read_at) n.read_at = new Date().toISOString()
    })
    unreadCount.value = 0
    try {
      await api.patch('/notifications/read-all')
    } catch {}
  }

  async function remove(id) {
    const n = notifications.value.find(n => n.id === id)
    if (n && !n.read_at) unreadCount.value = Math.max(0, unreadCount.value - 1)
    notifications.value = notifications.value.filter(n => n.id !== id)
    try {
      await api.delete(`/notifications/${id}`)
    } catch {}
  }

  return {
    unreadCount, notifications, isOpen,
    fetchNotifications, fetchUnreadCount,
    startPolling, stopPolling,
    startRealtime, stopRealtime,
    markRead, markAllRead, remove,
  }
})
