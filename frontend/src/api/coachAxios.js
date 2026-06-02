import axios from 'axios'

const coachApi = axios.create({
  baseURL: import.meta.env.VITE_API_URL + '/api',
  headers: { Accept: 'application/json' },
})

coachApi.interceptors.request.use((config) => {
  const token = sessionStorage.getItem('coach_token')
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

coachApi.interceptors.response.use(
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
          resolve(coachApi(config))
        })
      })
    }

    config._retry = true
    isRefreshing = true

    try {
      const { data } = await coachApi.post('/provider/refresh')
      const newToken = data.data.token
      sessionStorage.setItem('coach_token', newToken)
      coachApi.defaults.headers.common['Authorization'] = `Bearer ${newToken}`
      resolvePending(newToken)
      config.headers.Authorization = `Bearer ${newToken}`
      return coachApi(config)
    } catch {
      rejectPending(error)
      if (!isRedirecting) {
        isRedirecting = true
        sessionStorage.removeItem('coach_token')
        sessionStorage.removeItem('coach_user')
        window.location.href = '/coach/login'
      }
      return Promise.reject(error)
    } finally {
      isRefreshing = false
    }
  }
)

export default coachApi
