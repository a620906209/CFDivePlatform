<script setup>
import { useNotificationStore } from '../stores/notifications'

const store = useNotificationStore()

function toggle() {
  if (!store.isOpen) {
    store.fetchNotifications()
  }
  store.isOpen = !store.isOpen
}
</script>

<template>
  <button
    @click="toggle"
    class="relative p-2 rounded-full hover:bg-white/10 transition"
    aria-label="通知"
  >
    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round"
        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0a3 3 0 11-6 0m6 0H9" />
    </svg>
    <span
      v-if="store.unreadCount > 0"
      class="absolute -top-0.5 -right-0.5 min-w-[1.1rem] h-[1.1rem] flex items-center justify-center
             bg-red-500 text-white text-[10px] font-bold rounded-full px-0.5 leading-none"
    >
      {{ store.unreadCount > 99 ? '99+' : store.unreadCount }}
    </span>
  </button>
</template>
