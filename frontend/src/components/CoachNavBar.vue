<script setup>
import { useCoachAuthStore } from '../stores/coachAuth'
import { useRouter } from 'vue-router'

const coachAuth = useCoachAuthStore()
const router    = useRouter()

async function handleLogout() {
  await coachAuth.logout()
  router.push('/coach/login')
}
</script>

<template>
  <nav class="bg-gray-900 text-white shadow-md">
    <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
      <div class="flex items-center gap-6">
        <RouterLink to="/coach/dashboard" class="text-lg font-bold tracking-wide hover:text-gray-300 transition">
          🤿 Coach Portal
        </RouterLink>
        <RouterLink to="/coach/dashboard"  class="text-sm hover:text-gray-300 transition">我的課程</RouterLink>
        <RouterLink to="/coach/schedules"  class="text-sm hover:text-gray-300 transition">時段管理</RouterLink>
        <RouterLink to="/coach/bookings"   class="text-sm hover:text-gray-300 transition">預約管理</RouterLink>
        <RouterLink to="/coach/profile"    class="text-sm hover:text-gray-300 transition">個人資料</RouterLink>
      </div>

      <div class="flex items-center gap-4 text-sm">
        <span class="text-gray-400">{{ coachAuth.user?.name }}</span>
        <button
          @click="handleLogout"
          class="bg-gray-700 hover:bg-gray-600 px-4 py-1.5 rounded-full transition"
        >
          登出
        </button>
      </div>
    </div>
  </nav>
</template>
