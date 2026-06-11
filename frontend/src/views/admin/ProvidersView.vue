<script setup>
import { ref, onMounted } from 'vue'
import adminApi from '../../api/adminAxios'

const providers     = ref([])
const loading       = ref(true)
const search        = ref('')
const pendingOnly   = ref(false)
const certsOf       = ref(null)   // { name, certifications } 查看證照面板
const rejectTarget  = ref(null)   // 駁回原因輸入面板的對象
const rejectReason  = ref('')

const STATUS_META = {
  unsubmitted: { label: '未送審', cls: 'bg-slate-100 text-slate-500' },
  pending:     { label: '待審核', cls: 'bg-amber-100 text-amber-700' },
  approved:    { label: '已通過', cls: 'bg-teal-100 text-teal-700' },
  rejected:    { label: '已駁回', cls: 'bg-rose-100 text-rose-600' },
}

function statusMeta(p) {
  return STATUS_META[p.provider_profile?.verification_status] ?? STATUS_META.unsubmitted
}

async function fetchProviders() {
  loading.value = true
  try {
    const params = {}
    if (search.value) params.q = search.value
    const res = await adminApi.get('/admin/providers', { params })
    providers.value = pendingOnly.value
      ? res.data.data.filter(p => p.provider_profile?.verification_status === 'pending')
      : res.data.data
  } finally {
    loading.value = false
  }
}

function togglePendingFilter() {
  pendingOnly.value = !pendingOnly.value
  fetchProviders()
}

async function toggleActive(p) {
  try {
    const res = await adminApi.put(`/admin/providers/${p.id}/toggle-active`)
    p.is_active = res.data.data.is_active
  } catch (e) { alert(e.response?.data?.message || '操作失敗') }
}

async function viewCertifications(p) {
  try {
    const res = await adminApi.get('/admin/verifications', { params: { status: 'all' } })
    const row = res.data.data.find(v => v.user_id === p.id)
    certsOf.value = { name: p.name, certifications: row?.certifications ?? [] }
  } catch (e) { alert(e.response?.data?.message || '讀取失敗') }
}

async function approve(p) {
  try {
    await adminApi.put(`/admin/verifications/${p.id}/approve`)
    await fetchProviders()
  } catch (e) { alert(e.response?.data?.message || '操作失敗') }
}

function openReject(p) {
  rejectTarget.value = p
  rejectReason.value = ''
}

async function confirmReject() {
  if (!rejectReason.value.trim()) { alert('請填寫駁回原因'); return }
  try {
    await adminApi.put(`/admin/verifications/${rejectTarget.value.id}/reject`, { reason: rejectReason.value.trim() })
    rejectTarget.value = null
    await fetchProviders()
  } catch (e) { alert(e.response?.data?.message || '操作失敗') }
}

onMounted(fetchProviders)
</script>

<template>
  <main class="max-w-6xl mx-auto px-4 py-10">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">教練管理</h1>

    <div class="flex gap-3 mb-6">
      <input v-model="search" @keyup.enter="fetchProviders" type="text" placeholder="搜尋姓名或 Email..."
        class="flex-1 border border-slate-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-400" />
      <button @click="fetchProviders"
        class="bg-slate-800 text-white px-5 py-2 rounded-lg text-sm hover:bg-slate-700 transition">搜尋</button>
      <button @click="togglePendingFilter"
        :class="pendingOnly ? 'bg-amber-500 text-white' : 'bg-amber-50 text-amber-700'"
        class="px-5 py-2 rounded-lg text-sm hover:opacity-80 transition">待審核</button>
    </div>

    <div v-if="loading" class="text-center text-slate-400 py-20">載入中...</div>

    <div v-else class="bg-white rounded-2xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-6 py-3 text-left">姓名</th>
            <th class="px-6 py-3 text-left">工作室</th>
            <th class="px-6 py-3 text-center">驗證</th>
            <th class="px-6 py-3 text-center">狀態</th>
            <th class="px-6 py-3 text-center">操作</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="p in providers" :key="p.id" class="hover:bg-slate-50">
            <td class="px-6 py-4">
              <p class="font-medium text-slate-800">{{ p.name }}</p>
              <p class="text-xs text-slate-400">{{ p.email }}</p>
            </td>
            <td class="px-6 py-4 text-slate-500">{{ p.provider_profile?.business_name || '-' }}</td>
            <td class="px-6 py-4 text-center">
              <span :class="statusMeta(p).cls" class="text-xs px-2 py-1 rounded-full font-medium">
                {{ statusMeta(p).label }}
              </span>
            </td>
            <td class="px-6 py-4 text-center">
              <span :class="p.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'"
                class="text-xs px-2 py-1 rounded-full font-medium">
                {{ p.is_active ? '啟用' : '停用' }}
              </span>
            </td>
            <td class="px-6 py-4 text-center">
              <div class="flex justify-center gap-2 flex-wrap">
                <button @click="viewCertifications(p)"
                  class="text-xs px-3 py-1 rounded-lg bg-slate-50 text-slate-600 hover:opacity-80 transition">
                  證照
                </button>
                <button v-if="p.provider_profile?.verification_status === 'pending'" @click="approve(p)"
                  class="text-xs px-3 py-1 rounded-lg bg-teal-50 text-teal-700 hover:opacity-80 transition">
                  通過
                </button>
                <button v-if="['pending', 'approved'].includes(p.provider_profile?.verification_status)" @click="openReject(p)"
                  class="text-xs px-3 py-1 rounded-lg bg-rose-50 text-rose-600 hover:opacity-80 transition">
                  駁回
                </button>
                <button @click="toggleActive(p)"
                  :class="p.is_active ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-700'"
                  class="text-xs px-3 py-1 rounded-lg hover:opacity-80 transition">
                  {{ p.is_active ? '停用' : '啟用' }}
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="providers.length === 0">
            <td colspan="5" class="px-6 py-10 text-center text-slate-400">無符合的教練</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 證照檢視面板 -->
    <div v-if="certsOf" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" @click.self="certsOf = null">
      <div class="bg-white rounded-2xl p-6 max-w-lg w-full mx-4">
        <h2 class="font-semibold text-slate-800 mb-4">{{ certsOf.name }} 的證照</h2>
        <p v-if="certsOf.certifications.length === 0" class="text-sm text-slate-400 py-6 text-center">尚未上傳證照</p>
        <div v-else class="grid grid-cols-2 gap-3">
          <a v-for="c in certsOf.certifications" :key="c.id" :href="c.url" target="_blank"
            class="rounded-xl overflow-hidden border hover:opacity-90 transition">
            <img :src="c.url" loading="lazy" class="w-full h-36 object-cover" />
          </a>
        </div>
        <button @click="certsOf = null" class="mt-4 w-full bg-slate-100 text-slate-600 py-2 rounded-xl text-sm hover:bg-slate-200 transition">關閉</button>
      </div>
    </div>

    <!-- 駁回原因面板 -->
    <div v-if="rejectTarget" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" @click.self="rejectTarget = null">
      <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4">
        <h2 class="font-semibold text-slate-800 mb-1">駁回 {{ rejectTarget.name }}</h2>
        <p class="text-xs text-slate-400 mb-4">原因將以站內通知與 Email 告知教練</p>
        <textarea v-model="rejectReason" rows="3" maxlength="500" placeholder="例如：證照影像不清晰，請重新拍攝上傳"
          class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300"></textarea>
        <div class="flex gap-2 mt-4">
          <button @click="rejectTarget = null" class="flex-1 bg-slate-100 text-slate-600 py-2 rounded-xl text-sm hover:bg-slate-200 transition">取消</button>
          <button @click="confirmReject" class="flex-1 bg-rose-600 text-white py-2 rounded-xl text-sm hover:bg-rose-700 transition">確認駁回</button>
        </div>
      </div>
    </div>
  </main>
</template>
