import axios from 'axios'

const notificationApi = axios.create({
  baseURL: import.meta.env.VITE_API_URL + '/api',
  headers: { Accept: 'application/json' },
})

notificationApi.interceptors.request.use((config) => {
  // 優先用 coach_token，因為 coach 身份通知優先；member 也可用自己的 token
  // 兩者都存在時（測試情境），以當前頁面路徑決定：/coach 開頭用 coach_token，其餘用 token
  const isCoachPage = window.location.pathname.startsWith('/coach')
  const token = isCoachPage
    ? (localStorage.getItem('coach_token') || localStorage.getItem('token'))
    : (localStorage.getItem('token') || localStorage.getItem('coach_token'))
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

export default notificationApi
