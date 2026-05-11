import coachApi from './coachAxios'

export function getSchedules() {
  return coachApi.get('/provider/schedules')
}

export function createSchedule(payload) {
  return coachApi.post('/provider/schedules', payload)
}

export function updateSchedule(id, payload) {
  return coachApi.put(`/provider/schedules/${id}`, payload)
}

export function deleteSchedule(id) {
  return coachApi.delete(`/provider/schedules/${id}`)
}
