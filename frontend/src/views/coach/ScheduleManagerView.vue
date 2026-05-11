<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { getSchedules, createSchedule, deleteSchedule } from '../../api/coachScheduleApi'
import coachApi from '../../api/coachAxios'

const route     = useRoute()
const schedules = ref([])
const offers    = ref([])
const loading   = ref(true)
const showForm  = ref(false)
const formError = ref('')
const isNewCourse = ref(route.query.new === '1')
const form = ref({
  diving_offer_id: route.query.offer_id ? Number(route.query.offer_id) : '',
  scheduled_date: '',
  start_time: '',
  max_participants: 1,
})

// 時間選擇器
const timePeriod = ref('AM')
const timeHour   = ref('08')
const timeMinute = ref('00')
const HOURS_AM = ['06','07','08','09','10','11']
const HOURS_PM = ['12','13','14','15','16','17','18']
const MINUTES  = ['00','30']

const hourOptions = computed(() => timePeriod.value === 'AM' ? HOURS_AM : HOURS_PM)

function syncTime() {
  if (timePeriod.value === 'AM' && !HOURS_AM.includes(timeHour.value)) timeHour.value = '08'
  if (timePeriod.value === 'PM' && !HOURS_PM.includes(timeHour.value)) timeHour.value = '13'
  form.value.start_time = `${timeHour.value}:${timeMinute.value}`
}

watch([timePeriod, timeHour, timeMinute], syncTime, { immediate: true })

const today = computed(() => new Date().toISOString().split('T')[0])

onMounted(async () => {
  try {
    const [sRes, oRes] = await Promise.all([
      getSchedules(),
      coachApi.get('/provider/offers'),
    ])
    schedules.value = sRes.data.data
    offers.value    = oRes.data.data
    if (isNewCourse.value) showForm.value = true
  } finally {
    loading.value = false
  }
})

async function submitForm() {
  formError.value = ''
  try {
    const res = await createSchedule(form.value)
    schedules.value.unshift(res.data.data)
    showForm.value = false
    form.value = { diving_offer_id: '', scheduled_date: '', start_time: '', max_participants: 1 }
  } catch (e) {
    formError.value = e.response?.data?.message || '建立失敗'
  }
}

async function doDelete(schedule) {
  if (!confirm(`確定取消「${schedule.offer_title} ${schedule.scheduled_date}」這個時段？\n該時段下的預約將自動取消。`)) return
  try {
    await deleteSchedule(schedule.id)
    schedule.status = 'cancelled'
  } catch (e) {
    alert(e.response?.data?.message || '操作失敗')
  }
}

const STATUS_COLOR = {
  open:      'bg-green-100 text-green-700',
  full:      'bg-yellow-100 text-yellow-700',
  cancelled: 'bg-gray-100 text-gray-400',
}
</script>

<template>
  <div class="p-6 max-w-4xl mx-auto">
    <!-- 新建課程引導提示 -->
    <div v-if="isNewCourse" class="bg-ocean-50 border border-ocean-200 rounded-xl px-5 py-4 mb-6 flex items-start gap-3">
      <span class="text-2xl">🎉</span>
      <div>
        <p class="font-semibold text-ocean-800">課程建立成功！</p>
        <p class="text-sm text-ocean-700 mt-0.5">請為課程新增開課時段，學員才能看到可預約的日期。</p>
      </div>
    </div>

    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-800">時段管理</h1>
      <button
        @click="showForm = !showForm"
        class="bg-ocean-700 hover:bg-ocean-600 text-white px-5 py-2 rounded-full text-sm font-medium transition"
      >
        {{ showForm ? '取消' : '+ 新增時段' }}
      </button>
    </div>

    <!-- 新增表單 -->
    <div v-if="showForm" class="bg-ocean-50 rounded-2xl p-6 mb-6 border border-ocean-200">
      <h2 class="font-semibold text-gray-700 mb-4">新增開課時段</h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm text-gray-600 mb-1">課程</label>
          <select v-model="form.diving_offer_id" class="w-full border rounded-lg px-3 py-2 text-sm">
            <option value="" disabled>請選擇課程</option>
            <option v-for="o in offers" :key="o.id" :value="o.id">{{ o.title }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">日期</label>
          <input v-model="form.scheduled_date" type="date" :min="today" class="w-full border rounded-lg px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">開始時間</label>
          <div class="flex gap-2">
            <select v-model="timePeriod" @change="syncTime"
              class="border rounded-lg px-3 py-2 text-sm w-24 bg-white">
              <option value="AM">上午</option>
              <option value="PM">下午</option>
            </select>
            <select v-model="timeHour" @change="syncTime"
              class="border rounded-lg px-3 py-2 text-sm flex-1 bg-white">
              <option v-for="h in hourOptions" :key="h" :value="h">{{ h }} 時</option>
            </select>
            <select v-model="timeMinute" @change="syncTime"
              class="border rounded-lg px-3 py-2 text-sm w-24 bg-white">
              <option value="00">00 分</option>
              <option value="30">30 分</option>
            </select>
          </div>
          <p class="text-xs text-gray-400 mt-1">已選：{{ form.start_time }}</p>
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">人數上限</label>
          <input v-model.number="form.max_participants" type="number" min="1" class="w-full border rounded-lg px-3 py-2 text-sm" />
        </div>
      </div>
      <p v-if="formError" class="text-red-500 text-sm mt-3">{{ formError }}</p>
      <button @click="submitForm" class="mt-4 bg-ocean-700 hover:bg-ocean-600 text-white px-6 py-2 rounded-full text-sm font-medium transition">
        建立時段
      </button>
    </div>

    <div v-if="loading" class="text-center text-gray-400 py-20">載入中...</div>

    <div v-else-if="schedules.length === 0" class="text-center text-gray-400 py-20">尚未建立任何時段</div>

    <div v-else class="space-y-3">
      <div
        v-for="s in schedules"
        :key="s.id"
        class="bg-white rounded-xl shadow px-5 py-4 flex items-center justify-between"
      >
        <div>
          <p class="font-medium text-gray-800">{{ s.offer_title }}</p>
          <p class="text-sm text-gray-500 mt-0.5">{{ s.scheduled_date }} {{ s.start_time }}・剩餘 {{ s.remaining_spots }}/{{ s.max_participants }} 人</p>
        </div>
        <div class="flex items-center gap-3">
          <span class="text-xs px-3 py-1 rounded-full font-medium" :class="STATUS_COLOR[s.status]">
            {{ { open: '開放', full: '已滿', cancelled: '已取消' }[s.status] || s.status }}
          </span>
          <button
            v-if="s.status !== 'cancelled'"
            @click="doDelete(s)"
            class="text-sm text-red-400 hover:text-red-600 underline"
          >
            取消時段
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
