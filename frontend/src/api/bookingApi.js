import api from './axios'

export function getMyBookings() {
  return api.get('/member/bookings')
}

export function getBooking(id) {
  return api.get(`/member/bookings/${id}`)
}

export function createBooking(payload) {
  return api.post('/member/bookings', payload)
}

export function cancelBooking(id) {
  return api.delete(`/member/bookings/${id}`)
}
