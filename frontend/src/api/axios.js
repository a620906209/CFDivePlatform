import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL + '/api',
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = sessionStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

let isRefreshing   = false
let isRedirecting  = false
let pendingRequests = []

function resolvePending(token) {
  pendingRequests.forEach((cb) => cb(token))
  pendingRequests = []
}

function rejectPending(error) {
  pendingRequests.forEach((cb) => cb(null, error))
  pendingRequests = []
}

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const config = error.config
    const status = error.response?.status

    const isAuthEndpoint =
      config.url.includes('/login') ||
      config.url.includes('/register') ||
      config.url.includes('/refresh')

    if (status !== 401 || isAuthEndpoint || config._retry) {
      return Promise.reject(error)
    }

    if (isRefreshing) {
      return new Promise((resolve, reject) => {
        pendingRequests.push((token, err) => {
          if (err) return reject(err)
          config.headers.Authorization = `Bearer ${token}`
          resolve(api(config))
        })
      })
    }

    config._retry = true
    isRefreshing = true

    try {
      const { data } = await api.post('/member/refresh')
      const newToken = data.data.token
      sessionStorage.setItem('token', newToken)
      api.defaults.headers.common['Authorization'] = `Bearer ${newToken}`
      resolvePending(newToken)
      config.headers.Authorization = `Bearer ${newToken}`
      return api(config)
    } catch {
      rejectPending(error)
      if (!isRedirecting) {
        isRedirecting = true
        sessionStorage.removeItem('token')
        sessionStorage.removeItem('user')
        window.location.href = '/login'
      }
      return Promise.reject(error)
    } finally {
      isRefreshing = false
    }
  }
)

export default api
