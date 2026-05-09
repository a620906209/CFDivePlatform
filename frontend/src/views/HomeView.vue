<template>
  <main>
    <section class="bg-gradient-to-br from-ocean-900 to-ocean-600 text-white py-28 px-4 text-center">
      <p class="text-ocean-100 text-sm uppercase tracking-widest mb-4">探索台灣最美的海底世界</p>
      <h1 class="text-5xl font-bold mb-6 leading-tight">
        找到你的<br class="sm:hidden" />完美潛水課程
      </h1>
      <p class="text-ocean-100 text-lg max-w-xl mx-auto mb-10">
        連結優質潛水教練與愛好者，無論初學者還是進階玩家，都能找到最適合的課程體驗。
      </p>
      <RouterLink
        to="/courses"
        class="inline-block bg-white text-ocean-800 font-semibold px-8 py-3 rounded-full text-lg hover:bg-ocean-50 transition shadow-lg"
      >
        探索課程 →
      </RouterLink>
    </section>

    <section class="max-w-6xl mx-auto px-4 py-16 grid sm:grid-cols-3 gap-8 text-center">
      <div class="p-6 bg-white rounded-2xl shadow">
        <div class="text-4xl mb-3">🏅</div>
        <h3 class="font-semibold text-gray-800 mb-1">認證教練</h3>
        <p class="text-sm text-gray-500">所有教練皆具備 PADI / SSI 國際認證</p>
      </div>
      <div class="p-6 bg-white rounded-2xl shadow">
        <div class="text-4xl mb-3">🗺️</div>
        <h3 class="font-semibold text-gray-800 mb-1">全台潛點</h3>
        <p class="text-sm text-gray-500">墾丁、小琉球、綠島、蘭嶼等多地課程</p>
      </div>
      <div class="p-6 bg-white rounded-2xl shadow">
        <div class="text-4xl mb-3">🛡️</div>
        <h3 class="font-semibold text-gray-800 mb-1">安心保障</h3>
        <p class="text-sm text-gray-500">完整保險與安全設備，讓你無後顧之憂</p>
      </div>
    </section>
  </main>
</template>
