<script setup>
import { useNotificationStore } from '../stores/notifications'
import { useRouter } from 'vue-router'

const store  = useNotificationStore()
const router = useRouter()

function formatTime(iso) {
  if (!iso) return ''
  const diff = Date.now() - new Date(iso).getTime()
  const m = Math.floor(diff / 60000)
  if (m < 1)  return '剛剛'
  if (m < 60) return `${m} 分鐘前`
  const h = Math.floor(m / 60)
  if (h < 24) return `${h} 小時前`
  return `${Math.floor(h / 24)} 天前`
}

function truncate(text, max = 80) {
  return text && text.length > max ? text.slice(0, max) + '…' : text
}

async function clickItem(item) {
  await store.markRead(item.id)
  store.isOpen = false
  if (item.action_url) {
    try {
      const path = new URL(item.action_url).pathname
      await router.push(path)
    } catch (e) {
      console.error('[NotificationDrawer] navigation failed:', item.action_url, e)
    }
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="drawer">
      <div v-if="store.isOpen" class="fixed inset-0 z-50 flex justify-end">
        <div class="absolute inset-0 bg-black/30" @click="store.isOpen = false" />

        <div class="relative w-80 sm:w-96 h-full bg-white shadow-2xl flex flex-col">
          <div class="flex items-center justify-between px-4 py-3 border-b">
            <h2 class="font-semibold text-gray-800">通知</h2>
            <div class="flex items-center gap-2">
              <button
                v-if="store.unreadCount > 0"
                @click="store.markAllRead()"
                class="text-xs text-blue-600 hover:underline"
              >
                全部標為已讀
              </button>
              <button @click="store.isOpen = false" class="text-gray-400 hover:text-gray-600 p-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <div class="flex-1 overflow-y-auto">
            <p v-if="store.notifications.length === 0" class="text-center text-gray-400 text-sm py-12">
              目前沒有通知
            </p>

            <ul v-else>
              <li
                v-for="item in store.notifications"
                :key="item.id"
                class="flex items-start gap-3 px-4 py-3 border-b hover:bg-gray-50 transition cursor-pointer"
                :class="{ 'bg-blue-50': !item.read_at }"
                @click="clickItem(item)"
              >
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-gray-800 truncate">{{ item.title }}</p>
                  <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ truncate(item.body) }}</p>
                  <p class="text-[10px] text-gray-400 mt-1">{{ formatTime(item.created_at) }}</p>
                </div>
                <button
                  @click.stop="store.remove(item.id)"
                  class="shrink-0 text-gray-300 hover:text-gray-500 p-1 mt-0.5"
                  title="刪除"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.drawer-enter-active,
.drawer-leave-active {
  transition: opacity 0.2s ease;
}
.drawer-enter-active > div:last-child,
.drawer-leave-active > div:last-child {
  transition: transform 0.2s ease;
}
.drawer-enter-from,
.drawer-leave-to {
  opacity: 0;
}
.drawer-enter-from > div:last-child,
.drawer-leave-to > div:last-child {
  transform: translateX(100%);
}
</style>
