<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import coachApi from '../../api/coachAxios'
import { uploadCover, deleteCover, uploadImage, deleteImage } from '../../api/courseImageApi'

const route  = useRoute()
const router = useRouter()

const isEdit  = computed(() => !!route.params.id)
const loading = ref(false)
const saving  = ref(false)
const error   = ref('')
const errors  = ref({})

const REGIONS = ['北部', '中部', '南部', '東部', '離島']

const form = ref({
  title:       '',
  location:    '',
  spot:        '',
  price:       '',
  region:      '',
  tag:         '',
  badges:      '',
  description: '',
})

// 圖片管理
const coverUrl    = ref(null)
const galleryImgs = ref([])
const imgUploading = ref(false)
const imgError     = ref('')

onMounted(async () => {
  if (!isEdit.value) return
  loading.value = true
  try {
    const res  = await coachApi.get(`/provider/offers/${route.params.id}`)
    const o    = res.data.data
    form.value = {
      title:       o.title       || '',
      location:    o.location    || '',
      spot:        o.spot        || '',
      price:       o.price       ?? '',
      region:      o.region      || '',
      tag:         o.tag         || '',
      badges:      Array.isArray(o.badges) ? o.badges.join(', ') : (o.badges || ''),
      description: o.description || '',
    }
    coverUrl.value    = o.cover_image_url || null
    galleryImgs.value = o.images || []
  } catch (e) {
    error.value = e.response?.data?.message || '無法載入課程資料'
  } finally {
    loading.value = false
  }
})

async function onCoverChange(e) {
  const file = e.target.files[0]
  if (!file) return
  imgUploading.value = true
  imgError.value = ''
  try {
    const res = await uploadCover(route.params.id, file)
    coverUrl.value = res.data.cover_image_url
  } catch (e) {
    imgError.value = e.response?.data?.message || '封面上傳失敗'
  } finally {
    imgUploading.value = false
    e.target.value = ''
  }
}

async function onDeleteCover() {
  if (!confirm('確定刪除封面？')) return
  await deleteCover(route.params.id)
  coverUrl.value = null
}

async function onGalleryChange(e) {
  const file = e.target.files[0]
  if (!file) return
  imgUploading.value = true
  imgError.value = ''
  try {
    const res = await uploadImage(route.params.id, file)
    galleryImgs.value.push(res.data.data)
  } catch (e) {
    imgError.value = e.response?.data?.message || '圖片上傳失敗'
  } finally {
    imgUploading.value = false
    e.target.value = ''
  }
}

async function onDeleteImage(img) {
  await deleteImage(img.id)
  galleryImgs.value = galleryImgs.value.filter(i => i.id !== img.id)
}

async function submit() {
  errors.value = {}
  error.value  = ''

  if (!form.value.title)    { errors.value.title    = '課程名稱為必填'; }
  if (!form.value.location) { errors.value.location = '地點為必填'; }
  if (!form.value.price)    { errors.value.price    = '價格為必填'; }
  if (!form.value.region)   { errors.value.region   = '地區為必填'; }
  if (Object.keys(errors.value).length) return

  const payload = {
    ...form.value,
    price:  Number(form.value.price),
    badges: form.value.badges
      ? form.value.badges.split(',').map(b => b.trim()).filter(Boolean)
      : [],
  }

  saving.value = true
  try {
    if (isEdit.value) {
      await coachApi.put(`/provider/offers/${route.params.id}`, payload)
      router.push('/coach/dashboard')
    } else {
      const res = await coachApi.post('/provider/offers', payload)
      const newId = res.data.data?.id
      router.push(`/coach/schedules?offer_id=${newId}&new=1`)
    }
  } catch (e) {
    const data   = e.response?.data
    error.value  = data?.message || '儲存失敗'
    errors.value = data?.errors  || {}
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <main class="max-w-2xl mx-auto px-4 py-10">
    <div class="flex items-center gap-3 mb-6">
      <RouterLink to="/coach/dashboard" class="text-gray-400 hover:text-gray-600 text-sm">← 返回</RouterLink>
      <h1 class="text-2xl font-bold text-gray-800">{{ isEdit ? '編輯課程' : '新增課程' }}</h1>
    </div>

    <div v-if="loading" class="text-center text-gray-400 py-20">載入中...</div>

    <form v-else @submit.prevent="submit" class="bg-white rounded-2xl shadow p-6 space-y-5">

      <div v-if="error" class="bg-red-50 text-red-600 text-sm rounded-lg px-4 py-3">{{ error }}</div>

      <div>
        <label class="block text-sm text-gray-600 mb-1">課程名稱 <span class="text-red-400">*</span></label>
        <input v-model="form.title" type="text"
          class="w-full border rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400"
          :class="errors.title ? 'border-red-400' : 'border-gray-300'" />
        <p v-if="errors.title" class="text-red-500 text-xs mt-1">{{ errors.title }}</p>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm text-gray-600 mb-1">地點 <span class="text-red-400">*</span></label>
          <input v-model="form.location" type="text"
            class="w-full border rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400"
            :class="errors.location ? 'border-red-400' : 'border-gray-300'" />
          <p v-if="errors.location" class="text-red-500 text-xs mt-1">{{ errors.location }}</p>
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">潛點</label>
          <input v-model="form.spot" type="text"
            class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" />
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm text-gray-600 mb-1">地區 <span class="text-red-400">*</span></label>
          <select v-model="form.region"
            class="w-full border rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400"
            :class="errors.region ? 'border-red-400' : 'border-gray-300'">
            <option value="">請選擇</option>
            <option v-for="r in REGIONS" :key="r" :value="r">{{ r }}</option>
          </select>
          <p v-if="errors.region" class="text-red-500 text-xs mt-1">{{ errors.region }}</p>
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">價格（NT$）<span class="text-red-400">*</span></label>
          <input v-model="form.price" type="number" min="0"
            class="w-full border rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400"
            :class="errors.price ? 'border-red-400' : 'border-gray-300'" />
          <p v-if="errors.price" class="text-red-500 text-xs mt-1">{{ errors.price }}</p>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm text-gray-600 mb-1">標籤</label>
          <input v-model="form.tag" type="text" placeholder="例：初學者"
            class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">徽章（逗號分隔）</label>
          <input v-model="form.badges" type="text" placeholder="例：PADI認證, 含裝備"
            class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400" />
        </div>
      </div>

      <div>
        <label class="block text-sm text-gray-600 mb-1">課程說明</label>
        <textarea v-model="form.description" rows="4"
          class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400 resize-none" />
      </div>

      <!-- 圖片管理（僅編輯模式） -->
      <div v-if="isEdit" class="border border-gray-200 rounded-xl p-5 space-y-5">
        <h3 class="text-sm font-semibold text-gray-700">課程圖片</h3>
        <p v-if="imgError" class="text-red-500 text-xs">{{ imgError }}</p>

        <!-- 封面 -->
        <div>
          <label class="block text-xs text-gray-500 mb-2">封面圖片（1 張）</label>
          <div class="flex items-center gap-4">
            <div class="w-32 h-24 rounded-lg overflow-hidden bg-gray-100 shrink-0">
              <img v-if="coverUrl" :src="coverUrl" class="w-full h-full object-cover" />
              <div v-else class="w-full h-full flex items-center justify-center text-gray-400 text-2xl">🤿</div>
            </div>
            <div class="flex flex-col gap-2">
              <label class="cursor-pointer text-xs bg-gray-900 text-white px-3 py-1.5 rounded-lg hover:bg-gray-700 transition text-center">
                {{ coverUrl ? '更換封面' : '上傳封面' }}
                <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onCoverChange" :disabled="imgUploading" />
              </label>
              <button v-if="coverUrl" @click="onDeleteCover" type="button"
                class="text-xs text-red-500 hover:text-red-700 underline">刪除封面</button>
            </div>
          </div>
        </div>

        <!-- 相簿 -->
        <div>
          <label class="block text-xs text-gray-500 mb-2">相簿圖片（最多 3 張）</label>
          <div class="flex gap-3 flex-wrap">
            <div v-for="img in galleryImgs" :key="img.id" class="relative w-24 h-20 rounded-lg overflow-hidden">
              <img :src="img.url" class="w-full h-full object-cover" />
              <button @click="onDeleteImage(img)" type="button"
                class="absolute top-1 right-1 bg-black/60 text-white rounded-full w-5 h-5 text-xs flex items-center justify-center hover:bg-black/80">
                ✕
              </button>
            </div>
            <label v-if="galleryImgs.length < 3"
              class="w-24 h-20 rounded-lg border-2 border-dashed border-gray-300 flex items-center justify-center cursor-pointer hover:border-ocean-400 transition text-gray-400 text-xl">
              +
              <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onGalleryChange" :disabled="imgUploading" />
            </label>
          </div>
          <p v-if="imgUploading" class="text-xs text-gray-400 mt-1">上傳中...</p>
        </div>
      </div>

      <div class="flex gap-3 justify-end pt-2">
        <RouterLink to="/coach/dashboard"
          class="px-5 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition">
          取消
        </RouterLink>
        <button type="submit" :disabled="saving"
          class="px-5 py-2 text-sm bg-gray-900 hover:bg-gray-700 text-white rounded-lg transition disabled:opacity-60">
          {{ saving ? '儲存中...' : (isEdit ? '更新課程' : '新增課程') }}
        </button>
      </div>
    </form>
  </main>
</template>
