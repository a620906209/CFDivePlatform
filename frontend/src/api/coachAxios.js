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

export default coachApi
