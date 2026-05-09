<script setup>
import { useAuthStore } from '../stores/auth'
import { useRouter } from 'vue-router'

const auth   = useAuthStore()
const router = useRouter()

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <nav class="bg-ocean-800 text-white shadow-md">
    <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
      <RouterLink to="/" class="text-xl font-bold tracking-wide hover:text-ocean-100 transition">
        🤿 CFDive
      </RouterLink>

      <div class="flex items-center gap-6 text-sm font-medium">
        <RouterLink to="/courses" class="hover:text-ocean-100 transition">探索課程</RouterLink>

        <template v-if="auth.isLoggedIn">
          <span class="text-ocean-200 hidden sm:inline">
            👤 {{ auth.user?.name }}
          </span>
          <RouterLink to="/profile" class="hover:text-ocean-100 transition">個人資料</RouterLink>
          <button
            @click="handleLogout"
            class="bg-ocean-600 hover:bg-ocean-500 px-4 py-1.5 rounded-full transition"
          >
            登出
          </button>
        </template>
        <template v-else>
          <RouterLink to="/login"    class="hover:text-ocean-100 transition">登入</RouterLink>
          <RouterLink
            to="/register"
            class="bg-ocean-600 hover:bg-ocean-500 px-4 py-1.5 rounded-full transition"
          >
            註冊
          </RouterLink>
        </template>
      </div>
    </div>
  </nav>
</template>
