import coachApi from './coachAxios'

export function getProviderBookings() {
  return coachApi.get('/provider/bookings')
}

export function confirmBooking(id) {
  return coachApi.put(`/provider/bookings/${id}/confirm`)
}

export function rejectBooking(id) {
  return coachApi.put(`/provider/bookings/${id}/reject`)
}

export function cancelBooking(id) {
  return coachApi.put(`/provider/bookings/${id}/cancel`)
}

export function completeBooking(id) {
  return coachApi.put(`/provider/bookings/${id}/complete`)
}
