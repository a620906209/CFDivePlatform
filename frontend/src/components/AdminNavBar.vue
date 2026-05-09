<script setup>
import { useAdminAuthStore } from '../stores/adminAuth'
import { useRouter } from 'vue-router'

const adminAuth = useAdminAuthStore()
const router    = useRouter()

async function handleLogout() {
  await adminAuth.logout()
  router.push('/admin/login')
}
</script>

<template>
  <nav class="bg-slate-800 text-white shadow-md">
    <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
      <div class="flex items-center gap-6">
        <span class="text-lg font-bold tracking-wide">⚙️ Admin Panel</span>
        <RouterLink to="/admin/dashboard" class="text-sm hover:text-slate-300 transition">儀表板</RouterLink>
        <RouterLink to="/admin/members"   class="text-sm hover:text-slate-300 transition">會員管理</RouterLink>
        <RouterLink to="/admin/providers" class="text-sm hover:text-slate-300 transition">教練管理</RouterLink>
        <RouterLink to="/admin/offers"    class="text-sm hover:text-slate-300 transition">課程管理</RouterLink>
      </div>
      <div class="flex items-center gap-4 text-sm">
        <span class="text-slate-400">{{ adminAuth.user?.name }}</span>
        <button @click="handleLogout"
          class="bg-slate-600 hover:bg-slate-500 px-4 py-1.5 rounded-full transition">
          登出
        </button>
      </div>
    </div>
  </nav>
</template>
