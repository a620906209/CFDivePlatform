import api from './axios'
import axios from 'axios'

const publicApi = axios.create({
  baseURL: import.meta.env.VITE_API_URL + '/api',
  headers: { Accept: 'application/json' },
})

export function getReviews(offerId, sort = 'helpful') {
  return publicApi.get(`/diving-offers/${offerId}/reviews`, { params: { sort } })
}

export function createReview(payload) {
  return api.post('/member/reviews', payload)
}

export function updateReview(id, payload) {
  return api.put(`/member/reviews/${id}`, payload)
}

export function deleteReview(id) {
  return api.delete(`/member/reviews/${id}`)
}

export function toggleHelpful(reviewId) {
  return api.post(`/reviews/${reviewId}/helpful`)
}
