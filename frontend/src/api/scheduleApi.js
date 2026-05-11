import axios from 'axios'

const publicApi = axios.create({
  baseURL: import.meta.env.VITE_API_URL + '/api',
  headers: { Accept: 'application/json' },
})

export function getSchedulesByOffer(offerId) {
  return publicApi.get(`/diving-offers/${offerId}/schedules`)
}
