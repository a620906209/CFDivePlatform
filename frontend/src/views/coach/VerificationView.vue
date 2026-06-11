<script setup>
import { ref, computed, onMounted } from 'vue'
import coachApi from '../../api/coachAxios'

const status        = ref('unsubmitted')
const reason        = ref(null)
const certs         = ref([])
const loading       = ref(true)
const uploading     = ref(false)
const submitting    = ref(false)
const errorMessage  = ref('')

const STATUS_META = {
  unsubmitted: { label: '尚未送審', cls: 'bg-slate-100 text-slate-600',  hint: '上傳教練證照後即可送出審核。通過審核前，你的課程不會公開曝光。' },
  pending:     { label: '審核中',   cls: 'bg-amber-100 text-amber-700',  hint: '已送出審核，平台將盡快處理。審核期間證照不可變更。' },
  approved:    { label: '已通過',   cls: 'bg-teal-100 text-teal-700',    hint: '你的教練資格已通過審核，課程已公開曝光並可接受預約。' },
  rejected:    { label: '未通過',   cls: 'bg-rose-100 text-rose-700',    hint: '請依駁回原因補正證照後重新送審。' },
}

const meta       = computed(() => STATUS_META[status.value] ?? STATUS_META.unsubmitted)
const canEdit    = computed(() => ['unsubmitted', 'rejected'].includes(status.value))
const canSubmit  = computed(() => canEdit.value && certs.value.length > 0)

async function load() {
  loading.value = true
  try {
    const res = await coachApi.get('/provider/verification')
    status.value = res.data.data.verification_status
    reason.value = res.data.data.rejection_reason
    certs.value  = res.data.data.certifications
  } finally {
    loading.value = false
  }
}

async function uploadCert(event) {
  const file = event.target.files[0]
  if (!file) return
  errorMessage.value = ''
  uploading.value = true
  try {
    const form = new FormData()
    form.append('image', file)
    await coachApi.post('/provider/verification/certifications', form)
    await load()
  } catch (e) {
    errorMessage.value = e.response?.data?.message || '上傳失敗'
  } finally {
    uploading.value = false
    event.target.value = ''
  }
}

async function removeCert(id) {
  errorMessage.value = ''
  try {
    await coachApi.delete(`/provider/verification/certifications/${id}`)
    await load()
  } catch (e) {
    errorMessage.value = e.response?.data?.message || '刪除失敗'
  }
}

async function submit() {
  errorMessage.value = ''
  submitting.value = true
  try {
    await coachApi.post('/provider/verification/submit')
    await load()
  } catch (e) {
    errorMessage.value = e.response?.data?.message || '送審失敗'
  } finally {
    submitting.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="p-6 max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-2">驗證申請</h1>
    <p class="text-gray-500 text-sm mb-6">上傳教練證照並送審，通過後課程才會公開曝光。</p>

    <div v-if="loading" class="text-gray-400">載入中…</div>

    <template v-else>
      <!-- 狀態卡 -->
      <div class="bg-white rounded-2xl shadow p-6 mb-6">
        <div class="flex items-center gap-3 mb-2">
          <span class="text-sm font-medium text-gray-600">目前狀態</span>
          <span :class="meta.cls" class="px-3 py-1 rounded-full text-sm font-medium">{{ meta.label }}</span>
        </div>
        <p class="text-sm text-gray-500">{{ meta.hint }}</p>
        <div v-if="status === 'rejected' && reason" class="mt-3 bg-rose-50 border border-rose-200 rounded-xl p-3 text-sm text-rose-700">
          駁回原因：{{ reason }}
        </div>
      </div>

      <!-- 證照管理 -->
      <div class="bg-white rounded-2xl shadow p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-semibold text-gray-700">教練證照（{{ certs.length }}/3）</h2>
          <label v-if="canEdit && certs.length < 3"
                 class="text-sm bg-ocean-600 hover:bg-ocean-700 text-white px-4 py-2 rounded-xl cursor-pointer transition"
                 :class="{ 'opacity-50 pointer-events-none': uploading }">
            {{ uploading ? '上傳中…' : '＋ 上傳證照' }}
            <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="uploadCert" />
          </label>
        </div>

        <div v-if="certs.length === 0" class="text-sm text-gray-400 py-6 text-center">尚未上傳任何證照</div>

        <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <div v-for="cert in certs" :key="cert.id" class="relative group rounded-xl overflow-hidden border">
            <a :href="cert.url" target="_blank">
              <img :src="cert.url" loading="lazy" class="w-full h-32 object-cover" />
            </a>
            <button v-if="canEdit"
                    @click="removeCert(cert.id)"
                    class="absolute top-1 right-1 bg-black/60 text-white text-xs px-2 py-1 rounded-lg opacity-0 group-hover:opacity-100 transition">
              刪除
            </button>
          </div>
        </div>
      </div>

      <p v-if="errorMessage" class="text-sm text-rose-600 mb-4">{{ errorMessage }}</p>

      <button v-if="canEdit"
              @click="submit"
              :disabled="!canSubmit || submitting"
              class="w-full bg-ocean-600 hover:bg-ocean-700 disabled:opacity-40 disabled:cursor-not-allowed text-white font-medium py-3 rounded-xl transition">
        {{ submitting ? '送出中…' : (status === 'rejected' ? '重新送出審核' : '送出審核') }}
      </button>
    </template>
  </div>
</template>
