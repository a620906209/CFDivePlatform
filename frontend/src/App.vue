<script setup>
import { computed, onMounted } from 'vue'
import { useAuthStore } from './stores/auth'
import { useCoachAuthStore } from './stores/coachAuth'
import { useAdminAuthStore } from './stores/adminAuth'
import { useRoute } from 'vue-router'
import NavBar from './components/NavBar.vue'

const auth      = useAuthStore()
const coachAuth = useCoachAuthStore()
const adminAuth = useAdminAuthStore()
const route     = useRoute()

onMounted(() => {
  auth.init()
  coachAuth.init()
  adminAuth.init()
})

const isBackofficePage = computed(() =>
  route.path.startsWith('/coach') || route.path.startsWith('/admin')
)
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <NavBar v-if="!isBackofficePage" />
    <RouterView />
  </div>
</template>
