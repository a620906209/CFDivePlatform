<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import NavBar from './components/NavBar.vue'
import NotificationDrawer from './components/NotificationDrawer.vue'

const route = useRoute()

const isBackofficePage = computed(() =>
  route.path.startsWith('/coach') || route.path.startsWith('/admin')
)
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <NavBar v-if="!isBackofficePage" />
    <RouterView />
    <NotificationDrawer />
  </div>
</template>
