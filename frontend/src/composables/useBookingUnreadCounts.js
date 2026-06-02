import { ref, onUnmounted } from 'vue'

/**
 * 追蹤多個 booking 的未讀訊息數。
 * @param {import('axios').AxiosInstance} axiosInstance - member 或 provider 的 axios
 */
export function useBookingUnreadCounts(axiosInstance) {
  const counts = ref({})  // { [bookingId]: number }
  let timer = null

  async function fetchCounts() {
    try {
      const res = await axiosInstance.get('/bookings/messages/unread-counts')
      counts.value = res.data.data ?? {}
    } catch {
      // 靜默失敗，不影響主要頁面
    }
  }

  /** 開啟聊天室後呼叫，清除該 booking 的角標 */
  function clearCount(bookingId) {
    counts.value = { ...counts.value, [bookingId]: 0 }
  }

  /** 頁面 mount 時呼叫，立即拉取一次並啟動 60s 輪詢 */
  function startPolling() {
    fetchCounts()
    timer = setInterval(fetchCounts, 60_000)
  }

  function stopPolling() {
    if (timer) clearInterval(timer)
    timer = null
  }

  onUnmounted(stopPolling)

  return { counts, fetchCounts, clearCount, startPolling, stopPolling }
}
