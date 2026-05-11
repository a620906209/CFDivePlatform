import coachApi from './coachAxios'

function toFormData(file) {
  const fd = new FormData()
  fd.append('image', file)
  return fd
}

export function uploadCover(offerId, file) {
  return coachApi.post(`/provider/offers/${offerId}/cover`, toFormData(file), {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

export function deleteCover(offerId) {
  return coachApi.delete(`/provider/offers/${offerId}/cover`)
}

export function uploadImage(offerId, file) {
  return coachApi.post(`/provider/offers/${offerId}/images`, toFormData(file), {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

export function deleteImage(imageId) {
  return coachApi.delete(`/provider/images/${imageId}`)
}
