<script setup>
defineProps({
  offer: { type: Object, required: true },
})
</script>

<template>
  <RouterLink
    :to="`/courses/${offer.id}`"
    class="bg-white rounded-2xl shadow hover:shadow-lg transition overflow-hidden flex flex-col"
  >
    <div class="h-40 overflow-hidden">
      <img
        v-if="offer.cover_image_url"
        :src="offer.cover_image_url"
        :alt="offer.title"
        loading="lazy"
        class="w-full h-full object-cover"
      />
      <div v-else class="bg-gradient-to-br from-ocean-700 to-ocean-500 h-full flex items-center justify-center text-white text-5xl">
        🤿
      </div>
    </div>

    <div class="p-4 flex flex-col gap-2 flex-1">
      <div class="flex gap-2 flex-wrap">
        <span
          v-for="badge in (offer.badges || [])"
          :key="badge"
          class="text-xs bg-ocean-100 text-ocean-700 px-2 py-0.5 rounded-full"
        >
          {{ badge }}
        </span>
        <span v-if="offer.tag" class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">
          {{ offer.tag }}
        </span>
      </div>

      <h3 class="font-semibold text-gray-800 line-clamp-2 leading-snug">{{ offer.title }}</h3>

      <p class="text-sm text-gray-500 flex items-center gap-1">
        📍 {{ offer.location }}
      </p>

      <div class="mt-auto flex items-center justify-between pt-2 border-t border-gray-100">
        <span class="text-sm text-amber-500 font-medium">
          ★ {{ offer.rating }} <span class="text-gray-400">({{ offer.reviews }})</span>
        </span>
        <span class="text-ocean-700 font-bold">NT$ {{ offer.price.toLocaleString() }}</span>
      </div>
    </div>
  </RouterLink>
</template>
