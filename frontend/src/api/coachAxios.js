import axios from 'axios'

const coachApi = axios.create({
  baseURL: import.meta.env.VITE_API_URL + '/api',
  headers: { Accept: 'application/json' },
})

coachApi.interceptors.request.use((config) => {
  const token = localStorage.getItem('coach_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

coachApi.interceptors.response.use(
  (response) => response,
  (error) => {
    if (
      error.response?.status === 401 &&
      !error.config.url.includes('/login') &&
      !error.config.url.includes('/register')
    ) {
      localStorage.removeItem('coach_token')
      localStorage.removeItem('coach_user')
      window.location.href = '/coach/login'
    }
    return Promise.reject(error)
  }
)

export default coachApi
