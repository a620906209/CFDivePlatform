<script setup>
import { computed, onMounted } from 'vue'
import { useAuthStore } from './stores/auth'
import { useCoachAuthStore } from './stores/coachAuth'
import { useRoute } from 'vue-router'
import NavBar from './components/NavBar.vue'

const auth      = useAuthStore()
const coachAuth = useCoachAuthStore()
const route     = useRoute()

onMounted(() => {
  auth.init()
  coachAuth.init()
})

const isCoachPage = computed(() => route.path.startsWith('/coach'))
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <NavBar v-if="!isCoachPage" />
    <RouterView />
  </div>
</template>
